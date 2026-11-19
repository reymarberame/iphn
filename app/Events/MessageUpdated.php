<?php

namespace App\Events;

use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing('conversation.participants');
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->message->conversation->participants
            ->map(fn ($participant) => new PrivateChannel('user.'.$participant->id))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $chat = app(ChatService::class);
        $conversation = $this->message->conversation;
        $conversation->loadMissing('latestMessage');

        return [
            'message' => $chat->messagePayload($this->message),
            'last_message' => $conversation->latestMessage
                ? $chat->messagePayload($conversation->latestMessage)
                : null,
            'last_message_at' => ($conversation->last_message_at ?? $conversation->created_at)?->toIso8601String(),
        ];
    }
}
