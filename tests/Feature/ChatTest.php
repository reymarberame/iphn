<?php

namespace Tests\Feature;

use App\Events\ConversationRead;
use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsNetwork;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use BuildsNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccess();
    }

    public function test_messages_page_lists_active_network_users_and_existing_conversations(): void
    {
        $sender = $this->user('hospital_staff', $this->hospital());
        $recipient = $this->user('hospital_staff', $this->hospital());
        $inactive = $this->user('hospital_staff', $this->hospital(), ['status' => 'inactive']);

        $conversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $recipient->id])
            ->assertOk()
            ->assertJsonPath('conversation.other_user.id', $recipient->id)
            ->json('conversation.id');

        $this->actingAs($sender)
            ->get('/messages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Messages/Index')
                ->has('conversations', 1)
                ->where('conversations.0.id', $conversationId)
                ->where('conversations.0.other_user.id', $recipient->id)
                ->where('unreadCount', 0)
                ->where('chat.unread_count', 0)
                ->has('contacts', 1)
                ->where('contacts.0.id', $recipient->id)
                ->etc());
    }

    public function test_message_is_delivered_to_the_recipient_and_creates_an_unread_badge(): void
    {
        $sender = $this->user('hospital_staff', $this->hospital());
        $recipient = $this->user('hospital_staff', $this->hospital());

        $conversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $recipient->id])
            ->json('conversation.id');

        $messageId = $this->actingAs($sender)
            ->postJson("/messages/{$conversationId}/send", ['body' => 'Can you accept the referral?'])
            ->assertOk()
            ->assertJsonPath('message.body', 'Can you accept the referral?')
            ->assertJsonPath('conversation.other_user.id', $recipient->id)
            ->json('message.id');

        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_id' => $sender->id,
            'body' => 'Can you accept the referral?',
        ]);

        $this->actingAs($recipient)
            ->get('/messages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Messages/Index')
                ->where('conversations.0.id', $conversationId)
                ->where('conversations.0.unread_count', 1)
                ->where('conversations.0.last_message.body', 'Can you accept the referral?')
                ->where('unreadCount', 1)
                ->where('chat.unread_count', 1)
                ->etc());

        $this->actingAs($sender)
            ->get('/messages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.0.unread_count', 0)
                ->where('unreadCount', 0)
                ->etc());
    }

    public function test_opening_a_conversation_marks_it_read(): void
    {
        $sender = $this->user('hospital_staff', $this->hospital());
        $recipient = $this->user('hospital_staff', $this->hospital());

        $conversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $recipient->id])
            ->json('conversation.id');

        $messageId = $this->actingAs($sender)
            ->postJson("/messages/{$conversationId}/send", ['body' => 'Please review when available.'])
            ->json('message.id');

        $this->actingAs($recipient)
            ->getJson("/messages/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('conversation.unread_count', 0)
            ->assertJsonPath('messages.0.id', $messageId)
            ->assertJsonPath('unread_count', 0);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $recipient->id,
            'last_read_message_id' => $messageId,
        ]);

        $this->actingAs($recipient)
            ->get('/messages')
            ->assertInertia(fn (Assert $page) => $page
                ->where('unreadCount', 0)
                ->where('chat.unread_count', 0)
                ->etc());
    }

    public function test_opening_a_conversation_from_the_messages_url_marks_it_read_and_broadcasts(): void
    {
        $sender = $this->user('hospital_staff', $this->hospital());
        $recipient = $this->user('hospital_staff', $this->hospital());

        $conversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $recipient->id])
            ->json('conversation.id');

        $messageId = $this->actingAs($sender)
            ->postJson("/messages/{$conversationId}/send", ['body' => 'Opening this thread should mark it read.'])
            ->json('message.id');

        Event::fake([ConversationRead::class]);

        $this->actingAs($recipient)
            ->get("/messages?conversation={$conversationId}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Messages/Index')
                ->where('activeConversation.id', $conversationId)
                ->where('activeConversation.unread_count', 0)
                ->where('unreadCount', 0)
                ->has('messages', 1)
                ->where('messages.0.id', $messageId)
                ->etc());

        Event::assertDispatched(ConversationRead::class, function (ConversationRead $event) use ($recipient, $sender, $messageId) {
            return $event->reader->id === $recipient->id
                && $event->lastReadMessageId === $messageId
                && collect($event->broadcastOn())->pluck('name')->all() === ['private-user.'.$sender->id];
        });
    }

    public function test_a_user_outside_the_conversation_cannot_read_or_send_messages(): void
    {
        $sender = $this->user('hospital_staff', $this->hospital());
        $recipient = $this->user('hospital_staff', $this->hospital());
        $stranger = $this->user('hospital_staff', $this->hospital());

        $conversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $recipient->id])
            ->json('conversation.id');

        $this->actingAs($stranger)->getJson("/messages/{$conversationId}")->assertForbidden();
        $this->actingAs($stranger)
            ->postJson("/messages/{$conversationId}/send", ['body' => 'Should not send'])
            ->assertForbidden();
        $this->actingAs($stranger)
            ->postJson("/messages/{$conversationId}/read")
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_conversation_creation_is_idempotent_for_the_same_pair(): void
    {
        $first = $this->user('hospital_staff', $this->hospital());
        $second = $this->user('hospital_staff', $this->hospital());

        $firstId = $this->actingAs($first)
            ->postJson('/messages', ['user_id' => $second->id])
            ->json('conversation.id');

        $secondId = $this->actingAs($second)
            ->postJson('/messages', ['user_id' => $first->id])
            ->json('conversation.id');

        $this->assertSame($firstId, $secondId);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
        $this->assertSame(
            Conversation::directKeyFor($first->id, $second->id),
            Conversation::query()->firstOrFail()->direct_key,
        );
    }

    public function test_a_user_cannot_start_a_conversation_with_self_or_an_inactive_account(): void
    {
        $user = $this->user('hospital_staff', $this->hospital());
        $inactive = $this->user('hospital_staff', $this->hospital(), ['status' => 'inactive']);

        $this->actingAs($user)
            ->postJson('/messages', ['user_id' => $user->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');

        $this->actingAs($user)
            ->postJson('/messages', ['user_id' => $inactive->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_message_body_is_required_and_length_limited(): void
    {
        $sender = $this->user('hospital_staff', $this->hospital());
        $recipient = $this->user('hospital_staff', $this->hospital());

        $conversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $recipient->id])
            ->json('conversation.id');

        $this->actingAs($sender)
            ->postJson("/messages/{$conversationId}/send", ['body' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($sender)
            ->postJson("/messages/{$conversationId}/send", ['body' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_sending_a_message_broadcasts_to_both_participants_private_channels(): void
    {
        Event::fake([MessageSent::class]);

        $sender = $this->user('hospital_staff', $this->hospital());
        $recipient = $this->user('hospital_staff', $this->hospital());

        $conversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $recipient->id])
            ->json('conversation.id');

        $this->actingAs($sender)
            ->postJson("/messages/{$conversationId}/send", ['body' => 'Broadcast me'])
            ->assertOk();

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($sender, $recipient) {
            $channels = collect($event->broadcastOn())->pluck('name')->sort()->values()->all();
            $payload = $event->broadcastWith();

            return $channels === collect(['private-user.'.$sender->id, 'private-user.'.$recipient->id])->sort()->values()->all()
                && $payload['sender']['id'] === $sender->id
                && $payload['recipient']['id'] === $recipient->id
                && $payload['body'] === 'Broadcast me';
        });
    }

    public function test_marking_messages_read_broadcasts_a_read_receipt_to_the_sender(): void
    {
        $sender = $this->user('hospital_staff', $this->hospital());
        $recipient = $this->user('hospital_staff', $this->hospital());

        $conversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $recipient->id])
            ->json('conversation.id');

        $messageId = $this->actingAs($sender)
            ->postJson("/messages/{$conversationId}/send", ['body' => 'Read receipt test'])
            ->json('message.id');

        Event::fake([ConversationRead::class]);

        $this->actingAs($recipient)
            ->postJson("/messages/{$conversationId}/read")
            ->assertOk()
            ->assertJsonPath('last_read_message_id', $messageId)
            ->assertJsonPath('unread_count', 0);

        Event::assertDispatched(ConversationRead::class, function (ConversationRead $event) use ($recipient, $messageId, $sender) {
            return $event->reader->id === $recipient->id
                && $event->lastReadMessageId === $messageId
                && collect($event->broadcastOn())->pluck('name')->all() === ['private-user.'.$sender->id];
        });
    }

    public function test_conversation_summaries_are_scoped_to_the_authenticated_user(): void
    {
        $first = $this->user('hospital_staff', $this->hospital());
        $second = $this->user('hospital_staff', $this->hospital());
        $other = $this->user('hospital_staff', $this->hospital());

        $this->actingAs($first)->postJson('/messages', ['user_id' => $second->id])->assertOk();
        $this->actingAs($first)->postJson('/messages', ['user_id' => $other->id])->assertOk();

        $this->actingAs($second)
            ->get('/messages')
            ->assertInertia(fn (Assert $page) => $page
                ->has('conversations', 1)
                ->where('conversations.0.other_user.id', $first->id)
                ->has('contacts', 2)
                ->etc()
            );
    }

    public function test_sender_can_edit_their_own_message(): void
    {
        [$sender, $recipient, $conversationId, $messageId] = $this->conversationWithMessage();

        $response = $this->actingAs($sender)
            ->patchJson("/messages/{$conversationId}/messages/{$messageId}", ['body' => '  Updated coordination note  '])
            ->assertOk()
            ->assertJsonPath('message.id', $messageId)
            ->assertJsonPath('message.body', 'Updated coordination note')
            ->assertJsonPath('message.deleted', false)
            ->assertJsonPath('conversation.last_message.body', 'Updated coordination note');

        $this->assertNotNull($response->json('message.edited_at'));

        $message = Message::query()->findOrFail($messageId);

        $this->assertSame('Updated coordination note', $message->body);
        $this->assertNotNull($message->edited_at);
        $this->assertNull($message->deleted_at);

        $this->actingAs($recipient)
            ->getJson("/messages/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('messages.0.id', $messageId)
            ->assertJsonPath('messages.0.body', 'Updated coordination note')
            ->assertJsonPath('messages.0.deleted', false)
            ->assertJsonPath('messages.0.edited_at', $message->edited_at->toIso8601String());
    }

    public function test_editing_without_changes_does_not_set_an_edit_timestamp_or_broadcast(): void
    {
        [$sender, , $conversationId, $messageId] = $this->conversationWithMessage();

        Event::fake([MessageUpdated::class]);

        $this->actingAs($sender)
            ->patchJson("/messages/{$conversationId}/messages/{$messageId}", ['body' => 'Original message'])
            ->assertOk()
            ->assertJsonPath('message.edited_at', null);

        Event::assertNotDispatched(MessageUpdated::class);
        $this->assertNull(Message::query()->findOrFail($messageId)->edited_at);
    }

    public function test_only_the_sender_can_edit_or_delete_a_message(): void
    {
        [$sender, $recipient, $conversationId, $messageId] = $this->conversationWithMessage();
        $stranger = $this->user('hospital_staff', $this->hospital());

        $this->actingAs($recipient)
            ->patchJson("/messages/{$conversationId}/messages/{$messageId}", ['body' => 'Recipient edit'])
            ->assertForbidden();

        $this->actingAs($recipient)
            ->deleteJson("/messages/{$conversationId}/messages/{$messageId}")
            ->assertForbidden();

        $this->actingAs($stranger)
            ->patchJson("/messages/{$conversationId}/messages/{$messageId}", ['body' => 'Stranger edit'])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->deleteJson("/messages/{$conversationId}/messages/{$messageId}")
            ->assertForbidden();

        $message = Message::query()->findOrFail($messageId);

        $this->assertSame('Original message', $message->body);
        $this->assertNull($message->edited_at);
        $this->assertNull($message->deleted_at);
    }

    public function test_editing_requires_a_non_empty_body_within_the_limit_and_is_blocked_after_deletion(): void
    {
        [$sender, , $conversationId, $messageId] = $this->conversationWithMessage();

        $this->actingAs($sender)
            ->patchJson("/messages/{$conversationId}/messages/{$messageId}", ['body' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($sender)
            ->patchJson("/messages/{$conversationId}/messages/{$messageId}", ['body' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($sender)
            ->deleteJson("/messages/{$conversationId}/messages/{$messageId}")
            ->assertOk();

        $this->actingAs($sender)
            ->patchJson("/messages/{$conversationId}/messages/{$messageId}", ['body' => 'Trying to edit a tombstone'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $message = Message::query()->findOrFail($messageId);

        $this->assertNull($message->body);
        $this->assertNotNull($message->deleted_at);
    }

    public function test_sender_can_delete_their_own_message_and_the_tombstone_is_returned_everywhere(): void
    {
        [$sender, $recipient, $conversationId, $messageId] = $this->conversationWithMessage();

        $response = $this->actingAs($sender)
            ->deleteJson("/messages/{$conversationId}/messages/{$messageId}")
            ->assertOk()
            ->assertJsonPath('message.id', $messageId)
            ->assertJsonPath('message.body', null)
            ->assertJsonPath('message.deleted', true)
            ->assertJsonPath('conversation.last_message.id', $messageId)
            ->assertJsonPath('conversation.last_message.deleted', true)
            ->assertJsonPath('conversation.unread_count', 0);

        $this->assertNotNull($response->json('message.deleted_at'));

        $message = Message::query()->findOrFail($messageId);

        $this->assertNull($message->body);
        $this->assertNotNull($message->deleted_at);

        $this->actingAs($recipient)
            ->get('/messages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.0.unread_count', 0)
                ->where('conversations.0.last_message.id', $messageId)
                ->where('conversations.0.last_message.body', null)
                ->where('conversations.0.last_message.deleted', true)
                ->where('unreadCount', 0)
                ->where('chat.unread_count', 0)
                ->etc());

        $this->actingAs($recipient)
            ->getJson("/messages/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('messages.0.id', $messageId)
            ->assertJsonPath('messages.0.body', null)
            ->assertJsonPath('messages.0.deleted', true)
            ->assertJsonPath('unread_count', 0);
    }

    public function test_deleting_one_of_several_unread_messages_only_decrements_unread_counts(): void
    {
        [$sender, $recipient, $conversationId, $firstId] = $this->conversationWithMessage('First note');

        $secondId = $this->actingAs($sender)
            ->postJson("/messages/{$conversationId}/send", ['body' => 'Second note'])
            ->json('message.id');

        $this->actingAs($sender)
            ->deleteJson("/messages/{$conversationId}/messages/{$firstId}")
            ->assertOk();

        $this->actingAs($recipient)
            ->get('/messages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.0.unread_count', 1)
                ->where('conversations.0.last_message.id', $secondId)
                ->where('conversations.0.last_message.body', 'Second note')
                ->where('unreadCount', 1)
                ->where('chat.unread_count', 1)
                ->etc());

        $this->actingAs($recipient)
            ->getJson("/messages/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('messages.0.id', $firstId)
            ->assertJsonPath('messages.0.deleted', true)
            ->assertJsonPath('messages.1.id', $secondId)
            ->assertJsonPath('messages.1.body', 'Second note')
            ->assertJsonPath('unread_count', 0);
    }

    public function test_editing_a_message_broadcasts_the_updated_payload_to_both_participants(): void
    {
        [$sender, $recipient, $conversationId, $messageId] = $this->conversationWithMessage();

        Event::fake([MessageUpdated::class]);

        $this->actingAs($sender)
            ->patchJson("/messages/{$conversationId}/messages/{$messageId}", ['body' => 'Edited body'])
            ->assertOk();

        Event::assertDispatched(MessageUpdated::class, function (MessageUpdated $event) use ($sender, $recipient, $messageId) {
            $channels = collect($event->broadcastOn())->pluck('name')->sort()->values()->all();
            $payload = $event->broadcastWith();
            $expectedChannels = collect(['private-user.'.$sender->id, 'private-user.'.$recipient->id])->sort()->values()->all();

            return $channels === $expectedChannels
                && $event->message->id === $messageId
                && $payload['message']['body'] === 'Edited body'
                && $payload['message']['deleted'] === false
                && $payload['message']['edited_at'] !== null
                && $payload['last_message']['id'] === $messageId
                && $payload['last_message']['body'] === 'Edited body'
                && $payload['last_message_at'] !== null;
        });
    }

    public function test_deleting_a_message_broadcasts_a_tombstone_with_refreshed_unread_counts(): void
    {
        [$sender, $recipient, $conversationId, $messageId] = $this->conversationWithMessage();

        Event::fake([MessageDeleted::class]);

        $this->actingAs($sender)
            ->deleteJson("/messages/{$conversationId}/messages/{$messageId}")
            ->assertOk();

        Event::assertDispatched(MessageDeleted::class, function (MessageDeleted $event) use ($sender, $recipient, $messageId) {
            $channels = collect($event->broadcastOn())->pluck('name')->sort()->values()->all();
            $payload = $event->broadcastWith();
            $expectedChannels = collect(['private-user.'.$sender->id, 'private-user.'.$recipient->id])->sort()->values()->all();

            return $channels === $expectedChannels
                && $event->message->id === $messageId
                && $payload['message']['body'] === null
                && $payload['message']['deleted'] === true
                && $payload['last_message']['id'] === $messageId
                && $payload['last_message']['deleted'] === true
                && (array) $payload['unread_counts'] === [$sender->id => 0, $recipient->id => 0]
                && (array) $payload['conversation_unread_counts'] === [$sender->id => 0, $recipient->id => 0];
        });
    }

    public function test_deletion_is_idempotent_and_only_broadcasts_once(): void
    {
        [$sender, , $conversationId, $messageId] = $this->conversationWithMessage();

        Event::fake([MessageDeleted::class]);

        $this->actingAs($sender)
            ->deleteJson("/messages/{$conversationId}/messages/{$messageId}")
            ->assertOk()
            ->assertJsonPath('message.deleted', true);

        $deletedAt = Message::query()->findOrFail($messageId)->deleted_at;

        $this->actingAs($sender)
            ->deleteJson("/messages/{$conversationId}/messages/{$messageId}")
            ->assertOk()
            ->assertJsonPath('message.deleted', true);

        Event::assertDispatchedTimes(MessageDeleted::class, 1);
        $this->assertEquals($deletedAt, Message::query()->findOrFail($messageId)->deleted_at);
    }

    public function test_a_message_cannot_be_edited_or_deleted_through_another_conversation(): void
    {
        [$sender, , $conversationId, $messageId] = $this->conversationWithMessage();
        $other = $this->user('hospital_staff', $this->hospital());

        $otherConversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $other->id])
            ->json('conversation.id');

        $this->actingAs($sender)
            ->patchJson("/messages/{$otherConversationId}/messages/{$messageId}", ['body' => 'Wrong thread'])
            ->assertNotFound();

        $this->actingAs($sender)
            ->deleteJson("/messages/{$otherConversationId}/messages/{$messageId}")
            ->assertNotFound();

        $this->assertNotSame($conversationId, $otherConversationId);
        $message = Message::query()->findOrFail($messageId);

        $this->assertSame('Original message', $message->body);
        $this->assertNull($message->deleted_at);
    }

    /**
     * @return array{0: User, 1: User, 2: int, 3: int}
     */
    private function conversationWithMessage(string $body = 'Original message'): array
    {
        $sender = $this->user('hospital_staff', $this->hospital());
        $recipient = $this->user('hospital_staff', $this->hospital());

        $conversationId = $this->actingAs($sender)
            ->postJson('/messages', ['user_id' => $recipient->id])
            ->json('conversation.id');

        $messageId = $this->actingAs($sender)
            ->postJson("/messages/{$conversationId}/send", ['body' => $body])
            ->json('message.id');

        return [$sender, $recipient, $conversationId, $messageId];
    }
}
