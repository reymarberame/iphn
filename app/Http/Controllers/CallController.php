<?php

namespace App\Http\Controllers;

use App\Events\CallSignal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CallController extends Controller
{
    /**
     * Relay a WebRTC signaling message to another participant of the same
     * conversation. Nothing is persisted; the payload only travels over
     * Supabase Realtime.
     */
    public function signal(Request $request): JsonResponse
    {
        $sender = $request->user();

        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'conversation_id' => ['required', 'integer'],
            'call_id' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['offer', 'answer', 'ice', 'end', 'decline', 'busy'])],
            'data' => ['sometimes', 'array'],
        ]);

        $recipient = User::query()->findOrFail($validated['to_user_id']);

        abort_unless($recipient->isActive(), 403, 'The other user is not available.');
        abort_if($recipient->id === $sender->id, 422);

        $sharedConversation = DB::table('conversation_participants as sender')
            ->join('conversation_participants as recipient', 'recipient.conversation_id', '=', 'sender.conversation_id')
            ->where('sender.conversation_id', $validated['conversation_id'])
            ->where('sender.user_id', $sender->id)
            ->where('recipient.user_id', $recipient->id)
            ->exists();

        abort_unless($sharedConversation, 403, 'You can only call users in your conversations.');

        $data = $validated['data'] ?? [];

        // WebRTC descriptions are objects ({type, sdp}), not plain strings.
        $sdp = $data['sdp'] ?? null;

        try {
            event(new CallSignal(
                toUserId: $recipient->id,
                fromUserId: $sender->id,
                fromName: $sender->name,
                conversationId: $validated['conversation_id'],
                callId: $validated['call_id'],
                type: $validated['type'],
                data: [
                    'sdp' => is_string($sdp) || is_array($sdp) ? $sdp : null,
                    'candidate' => is_array($data['candidate'] ?? null) ? $data['candidate'] : null,
                    'video' => (bool) ($data['video'] ?? false),
                ],
            ));
        } catch (\Throwable $exception) {
            Log::error('Call signal broadcast failed', [
                'from' => $sender->id,
                'to' => $recipient->id,
                'type' => $validated['type'],
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['ok' => false, 'message' => 'Signal delivery failed.'], 502);
        }

        return response()->json(['ok' => true]);
    }
}
