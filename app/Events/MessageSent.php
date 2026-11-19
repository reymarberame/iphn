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

class MessageSent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing([
            'sender.activeMemberships.role:id,name',
            'sender.activeMemberships.hospital:id,name,short_name',
            'conversation.participants.activeMemberships.role:id,name',
            'conversation.participants.activeMemberships.hospital:id,name,short_name',
        ]);
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
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $chat = app(ChatService::class);
        $sender = $this->message->sender;
        $recipient = $this->message->conversation->participants
            ->first(fn ($participant) => $participant->id !== $sender->id);

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toIso8601String(),
            'sender' => $chat->userSummary($sender),
            'recipient' => $recipient ? $chat->userSummary($recipient) : null,
        ];
    }
}
