<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly int $toUserId,
        public readonly int $fromUserId,
        public readonly string $fromName,
        public readonly int $conversationId,
        public readonly string $callId,
        public readonly string $type,
        public readonly array $data,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->toUserId)];
    }

    public function broadcastAs(): string
    {
        return 'call.signal';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'call_id' => $this->callId,
            'from' => [
                'id' => $this->fromUserId,
                'name' => $this->fromName,
            ],
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
