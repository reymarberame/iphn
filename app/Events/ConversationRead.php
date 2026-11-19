<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public User $reader,
        public int $lastReadMessageId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->conversation->participants()
            ->whereKeyNot($this->reader->id)
            ->pluck('users.id')
            ->map(fn ($userId) => new PrivateChannel('user.'.$userId))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'conversation.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'reader_id' => $this->reader->id,
            'reader_name' => $this->reader->name,
            'last_read_message_id' => $this->lastReadMessageId,
            'read_at' => now()->toIso8601String(),
        ];
    }
}
