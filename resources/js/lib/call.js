const AUDIO_ONLY = { audio: true, video: false };

function normalizeSdp(sdp) {
    if (typeof sdp !== 'string') return sdp;

    // Normalize all line endings to CRLF and guarantee a trailing CRLF.
    // Chrome's SDP parser rejects descriptions without it.
    let normalized = sdp.replace(/\r\n/g, '\n').replace(/\r/g, '\n').replace(/\n/g, '\r\n');

    if (!normalized.endsWith('\r\n')) {
        normalized += '\r\n';
    }

    return normalized;
}

function withTerminatingNewline(description) {
    if (!description) return description;

    const copy = typeof description === 'string'
        ? { sdp: description }
        : { ...description };

    copy.sdp = normalizeSdp(copy.sdp);

    return copy;
}

// SDP is ASCII; base64 keeps line endings intact across JSON, PHP and Realtime.
function encodeDescription(description) {
    if (!description || typeof description.sdp !== 'string') return description;

    return {
        type: description.type,
        sdp: btoa(description.sdp),
    };
}

function decodeDescription(description) {
    if (!description || typeof description.sdp !== 'string') return description;

    try {
        return {
            type: description.type,
            sdp: atob(description.sdp),
        };
    } catch (error) {
        return description;
    }
}

function friendlyMediaError(error) {
    const name = error?.name || '';

    if (['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(name)) {
        return 'Microphone/camera access was blocked. Click the lock icon in your browser address bar, allow Microphone and Camera, then try again.';
    }

    if (['NotFoundError', 'DevicesNotFoundError'].includes(name)) {
        return 'No microphone or camera was found on this device. Connect one and try again.';
    }

    if (['NotReadableError', 'TrackStartError'].includes(name)) {
        return 'Your microphone or camera is already being used by another app. Close it and try again.';
    }

    return error?.message || 'Could not access your microphone or camera.';
}

/**
 * Manages one peer-to-peer WebRTC call at a time. Signaling is relayed through
 * Laravel (which broadcasts it over Supabase Realtime to the other user).
 */
export function createCallController({ userId, iceServers, signalUrl, onState, onMedia, onError }) {
    let pc = null;
    let localStream = null;
    let remoteStream = null;
    let pendingCandidates = [];
    let state = { status: 'idle' };
    let timer = null;

    const setState = (next) => {
        state = { ...state, ...next };
        if (onState) onState(state);
    };

    let lastError = 0;

    const reportError = (message) => {
        const now = Date.now();
        // ICE candidates can fail in bursts; don't flood the user with toasts.
        if (now - lastError < 4000) return;
        lastError = now;
        if (onError) onError(message);
    };

    const stopTimer = () => {
        if (timer) clearInterval(timer);
        timer = null;
    };

    const startTimer = (callId) => {
        stopTimer();
        const started = Date.now();
        timer = setInterval(() => {
            if (state.call_id === callId) {
                setState({ elapsed: Math.floor((Date.now() - started) / 1000) });
            }
        }, 1000);
    };

    const send = async (toUserId, conversationId, callId, type, data = {}) => {
        try {
            await window.axios.post(signalUrl, {
                to_user_id: toUserId,
                conversation_id: conversationId,
                call_id: callId,
                type,
                data,
            });
        } catch (error) {
            reportError(error?.response?.data?.message || 'Unable to reach the other user.');
        }
    };

    const flushCandidates = async () => {
        if (!pc || !pc.remoteDescription) return;
        const candidates = pendingCandidates;
        pendingCandidates = [];
        for (const candidate of candidates) {
            try {
                await pc.addIceCandidate(new RTCIceCandidate(candidate));
            } catch (error) {
                reportError('A connection detail could not be applied.');
            }
        }
    };

    const cleanup = ({ notify = false } = {}) => {
        const previous = state;
        stopTimer();

        if (pc) {
            pc.ontrack = null;
            pc.onicecandidate = null;
            pc.onconnectionstatechange = null;
            pc.close();
            pc = null;
        }

        if (localStream) {
            localStream.getTracks().forEach((track) => track.stop());
            localStream = null;
        }

        if (notify && previous.status !== 'idle' && previous.call_id && previous.peer_id) {
            send(previous.peer_id, previous.conversation_id, previous.call_id, 'end');
        }

        remoteStream = null;
        pendingCandidates = [];
        if (onMedia) onMedia({ local: null, remote: null });
        setState({ status: 'idle', call_id: null, peer_id: null, peer_name: null, conversation_id: null, video: false, elapsed: 0 });
    };

    const getMedia = async (withVideo) => {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('This browser does not support voice or video calls.');
        }

        try {
            return await navigator.mediaDevices.getUserMedia({ audio: true, video: withVideo });
        } catch (error) {
            if (!withVideo) {
                throw new Error(friendlyMediaError(error));
            }

            // Some users deny camera access but still want a voice call.
            try {
                return await navigator.mediaDevices.getUserMedia(AUDIO_ONLY);
            } catch (audioError) {
                throw new Error(friendlyMediaError(audioError));
            }
        }
    };

    const createPeer = () => {
        const connection = new RTCPeerConnection({ iceServers });
        connection.onicecandidate = (event) => {
            if (!event.candidate || !state.call_id) return;
            send(state.peer_id, state.conversation_id, state.call_id, 'ice', {
                candidate: event.candidate.toJSON(),
            });
        };
        connection.ontrack = (event) => {
            remoteStream = event.streams?.[0] || new MediaStream([event.track]);
            if (onMedia) onMedia({ local: localStream, remote: remoteStream });
        };
        connection.onconnectionstatechange = () => {
            if (connection.connectionState === 'connected') {
                if (state.status === 'active' && !state.connected_at) {
                    setState({ connected_at: new Date().toISOString() });
                    startTimer(state.call_id);
                }
                return;
            }

            if (['failed', 'closed'].includes(connection.connectionState)) {
                reportError('The call was disconnected.');
                cleanup();
            }
        };
        pc = connection;
        return connection;
    };

    const startCall = async (recipient, conversationId, withVideo = false) => {
        if (state.status !== 'idle') return;

        const callId = typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : String(Date.now());

        setState({
            status: 'outgoing',
            call_id: callId,
            peer_id: recipient.id,
            peer_name: recipient.name,
            conversation_id: conversationId,
            video: withVideo,
            elapsed: 0,
            connected_at: null,
        });

        try {
            localStream = await getMedia(withVideo);
            if (onMedia) onMedia({ local: localStream, remote: null });

            const connection = createPeer();
            localStream.getTracks().forEach((track) => connection.addTrack(track, localStream));

            const offer = await connection.createOffer();
            await connection.setLocalDescription(offer);

            await send(recipient.id, conversationId, callId, 'offer', {
                sdp: encodeDescription(connection.localDescription.toJSON()),
                video: withVideo,
            });
        } catch (error) {
            reportError(error?.message || 'Could not start the call.');
            cleanup();
        }
    };

    const acceptCall = async (withVideo = false) => {
        if (state.status !== 'incoming') return;

        const callId = state.call_id;
        const peerId = state.peer_id;
        const conversationId = state.conversation_id;
        const offer = state.offer;

        try {
            localStream = await getMedia(withVideo);
            if (onMedia) onMedia({ local: localStream, remote: null });

            const connection = createPeer();
            localStream.getTracks().forEach((track) => connection.addTrack(track, localStream));

            await connection.setRemoteDescription(withTerminatingNewline(decodeDescription(offer)));
            await flushCandidates();

            const answer = await connection.createAnswer();
            await connection.setLocalDescription(answer);

            setState({ status: 'active', video: localStream.getVideoTracks().length > 0 });
            await send(peerId, conversationId, callId, 'answer', {
                sdp: encodeDescription(connection.localDescription.toJSON()),
                video: localStream.getVideoTracks().length > 0,
            });
        } catch (error) {
            reportError(error?.message || 'Could not answer the call.');
            cleanup();
        }
    };

    const declineCall = () => {
        if (state.status !== 'incoming') return;
        const { peer_id: peerId, conversation_id: conversationId, call_id: callId } = state;
        cleanup({ notify: false });
        send(peerId, conversationId, callId, 'decline');
    };

    const hangup = () => cleanup({ notify: true });

    const onSignal = async (payload) => {
        if (!payload || !payload.call_id) return;

        const type = payload.type;
        const callId = payload.call_id;
        const from = payload.from || {};

        if (type === 'offer') {
            if (state.status !== 'idle') {
                send(from.id, payload.conversation_id || state.conversation_id || 0, callId, 'busy');
                return;
            }

            setState({
                status: 'incoming',
                call_id: callId,
                peer_id: from.id,
                peer_name: from.name || 'A BHN user',
                conversation_id: payload.conversation_id || 0,
                video: Boolean(payload.data?.video),
                offer: payload.data?.sdp,
                elapsed: 0,
                connected_at: null,
            });
            return;
        }

        // Ignore stale or unrelated signals.
        if (state.call_id !== callId) return;

        if (type === 'ice' && payload.data?.candidate) {
            if (pc && pc.remoteDescription) {
                try {
                    await pc.addIceCandidate(new RTCIceCandidate(payload.data.candidate));
                } catch (error) {
                    reportError('A connection detail could not be applied.');
                }
            } else {
                pendingCandidates.push(payload.data.candidate);
            }
            return;
        }

        if (type === 'answer' && state.status === 'outgoing' && pc && payload.data?.sdp) {
            await pc.setRemoteDescription(withTerminatingNewline(decodeDescription(payload.data.sdp)));
            await flushCandidates();
            setState({ status: 'active', video: Boolean(payload.data?.video) });
            startTimer(callId);
            return;
        }

        if (['end', 'decline', 'busy'].includes(type)) {
            if (type === 'busy') reportError(`${from.name || 'The other user'} is already in a call.`);
            cleanup();
        }
    };

    const toggleMute = () => {
        if (!localStream) return state;
        const next = !state.muted;
        localStream.getAudioTracks().forEach((track) => { track.enabled = !next; });
        setState({ muted: next });
        return state;
    };

    const toggleCamera = () => {
        if (!localStream || localStream.getVideoTracks().length === 0) return state;
        const next = !state.camera_off;
        localStream.getVideoTracks().forEach((track) => { track.enabled = !next; });
        setState({ camera_off: next });
        return state;
    };

    return {
        get state() { return state; },
        startCall,
        acceptCall,
        declineCall,
        hangup,
        toggleMute,
        toggleCamera,
        onSignal,
        destroy: () => cleanup(),
    };
}
