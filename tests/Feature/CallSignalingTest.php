<?php

namespace Tests\Feature;

use App\Events\CallSignal;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsNetwork;
use Tests\TestCase;

class CallSignalingTest extends TestCase
{
    use BuildsNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccess();
    }

    public function test_participants_can_relay_a_call_signal(): void
    {
        Event::fake([CallSignal::class]);

        $sender = $this->user('hospital_staff', $this->hospital());
        $recipient = $this->user('hospital_staff', $this->hospital());

        $conversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $recipient->id])
            ->json('conversation.id');

        $this->actingAs($sender)
            ->postJson('/calls/signal', [
                'to_user_id' => $recipient->id,
                'conversation_id' => $conversationId,
                'call_id' => 'call-123',
                'type' => 'offer',
                'data' => ['sdp' => 'v=0...', 'video' => true],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        Event::assertDispatched(CallSignal::class, function (CallSignal $event) use ($recipient, $sender, $conversationId) {
            return $event->toUserId === $recipient->id
                && $event->fromUserId === $sender->id
                && $event->conversationId === $conversationId
                && $event->type === 'offer'
                && $event->data['sdp'] === 'v=0...';
        });
    }

    public function test_users_cannot_signal_someone_outside_their_conversations(): void
    {
        Event::fake([CallSignal::class]);

        $sender = $this->user('hospital_staff', $this->hospital());
        $outsider = $this->user('hospital_staff', $this->hospital());

        $this->actingAs($sender)
            ->postJson('/calls/signal', [
                'to_user_id' => $outsider->id,
                'conversation_id' => 999999,
                'call_id' => 'call-123',
                'type' => 'offer',
            ])
            ->assertForbidden();

        Event::assertNotDispatched(CallSignal::class);
    }

    public function test_inactive_recipients_cannot_be_called(): void
    {
        Event::fake([CallSignal::class]);

        $sender = $this->user('hospital_staff', $this->hospital());
        $inactive = $this->user('hospital_staff', $this->hospital(), ['status' => 'inactive']);

        $conversation = Conversation::create([
            'direct_key' => Conversation::directKeyFor($sender->id, $inactive->id),
        ]);
        $conversation->participants()->attach([$sender->id, $inactive->id]);

        $this->actingAs($sender)
            ->postJson('/calls/signal', [
                'to_user_id' => $inactive->id,
                'conversation_id' => $conversation->id,
                'call_id' => 'call-123',
                'type' => 'offer',
            ])
            ->assertForbidden();

        Event::assertNotDispatched(CallSignal::class);
    }
}
