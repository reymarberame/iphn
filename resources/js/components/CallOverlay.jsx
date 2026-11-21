import React, { useEffect, useRef } from 'react';
import { Mic, MicOff, Phone, PhoneOff, Video, VideoOff } from 'lucide-react';
import { Avatar } from '../components/ui';
import { classNames } from '../lib/utils';

function formatDuration(seconds = 0) {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

export default function CallOverlay({ call, media, onAccept, onDecline, onHangup, onToggleMute, onToggleCamera }) {
    const remoteRef = useRef(null);
    const localRef = useRef(null);

    useEffect(() => {
        if (remoteRef.current) {
            remoteRef.current.srcObject = media?.remote || null;
        }
    }, [media?.remote]);

    useEffect(() => {
        if (localRef.current) {
            localRef.current.srcObject = media?.local || null;
        }
    }, [media?.local]);

    if (call.status === 'idle') return null;

    const isIncoming = call.status === 'incoming';
    const isActive = call.status === 'active';
    const showVideo = Boolean(call.video && (media?.remote || media?.local));

    return (
        <div className="fixed inset-0 z-[80] flex items-center justify-center bg-ink-950/70 p-4 backdrop-blur-sm">
            <div className="w-full max-w-lg overflow-hidden rounded-2xl border border-white/10 bg-ink-900 text-white shadow-2xl">
                <div className="relative aspect-video w-full overflow-hidden bg-ink-950">
                    {showVideo ? (
                        <>
                            <video
                                ref={remoteRef}
                                autoPlay
                                playsInline
                                className="h-full w-full object-cover"
                            />
                            <video
                                ref={localRef}
                                autoPlay
                                playsInline
                                muted
                                className="absolute bottom-3 right-3 h-24 w-32 rounded-lg border border-white/20 bg-ink-800 object-cover shadow-lg"
                            />
                        </>
                    ) : (
                        <div className="flex h-full flex-col items-center justify-center gap-3">
                            <Avatar name={call.peer_name || '?'} size="lg" />
                            <p className="text-sm text-white/60">
                                {isIncoming
                                    ? `Incoming ${call.video ? 'video' : 'voice'} call`
                                    : isActive
                                        ? (call.connected_at ? formatDuration(call.elapsed) : 'Connecting…')
                                        : 'Calling…'}
                            </p>
                        </div>
                    )}

                    {isActive && call.connected_at && showVideo && (
                        <span className="absolute left-3 top-3 rounded-full bg-black/50 px-2.5 py-1 text-xs font-medium">
                            {formatDuration(call.elapsed)}
                        </span>
                    )}

                    {!showVideo && isActive && call.connected_at && (
                        <span className="mt-1 text-xs text-emerald-300">Connected</span>
                    )}
                </div>

                <div className="space-y-4 p-5">
                    <div>
                        <h2 className="text-lg font-semibold">{call.peer_name || 'BHN user'}</h2>
                        <p className="text-xs text-white/50">
                            {isIncoming
                                ? 'Wants to talk with you'
                                : isActive
                                    ? (call.video ? 'Video call in progress' : 'Voice call in progress')
                                    : 'Waiting for an answer…'}
                        </p>
                    </div>

                    {isIncoming ? (
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => onAccept(false)}
                                className="btn btn-primary flex-1"
                            >
                                <Phone className="h-4 w-4" /> Answer voice
                            </button>
                            <button
                                type="button"
                                onClick={() => onAccept(true)}
                                className="btn btn-secondary flex-1"
                            >
                                <Video className="h-4 w-4" /> Answer video
                            </button>
                            <button
                                type="button"
                                onClick={onDecline}
                                className="btn btn-danger w-full"
                            >
                                <PhoneOff className="h-4 w-4" /> Decline
                            </button>
                        </div>
                    ) : (
                        <div className="flex items-center justify-center gap-3">
                            <button
                                type="button"
                                onClick={onToggleMute}
                                aria-label={call.muted ? 'Unmute' : 'Mute'}
                                className={classNames('btn btn-secondary btn-icon', call.muted && 'btn-danger')}
                            >
                                {call.muted ? <MicOff className="h-4 w-4" /> : <Mic className="h-4 w-4" />}
                            </button>
                            <button
                                type="button"
                                onClick={onToggleCamera}
                                disabled={!media?.local?.getVideoTracks?.().length}
                                aria-label={call.camera_off ? 'Turn camera on' : 'Turn camera off'}
                                className={classNames('btn btn-secondary btn-icon', call.camera_off && 'btn-danger')}
                            >
                                {call.camera_off ? <VideoOff className="h-4 w-4" /> : <Video className="h-4 w-4" />}
                            </button>
                            <button
                                type="button"
                                onClick={onHangup}
                                aria-label="End call"
                                className="btn btn-danger btn-icon"
                            >
                                <PhoneOff className="h-4 w-4" />
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
