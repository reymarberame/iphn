<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatService
{
    /** @var array<int, array<string, mixed>> */
    private array $userCache = [];

    public function unreadCount(User $user): int
    {
        return $this->unreadCountForUserId($user->id);
    }

    public function unreadCountForUserId(int $userId): int
    {
        return (int) Message::query()
            ->join('conversation_participants as cp', function ($join) use ($userId) {
                $join->on('cp.conversation_id', '=', 'messages.conversation_id')
                    ->where('cp.user_id', '=', $userId);
            })
            ->where('messages.sender_id', '!=', $userId)
            ->whereNull('messages.deleted_at')
            ->whereRaw('messages.id > COALESCE(cp.last_read_message_id, 0)')
            ->count();
    }

    /**
     * @return Collection<int, int> conversation id => unread message count
     */
    public function unreadByConversation(User $user): Collection
    {
        return Message::query()
            ->join('conversation_participants as cp', function ($join) use ($user) {
                $join->on('cp.conversation_id', '=', 'messages.conversation_id')
                    ->where('cp.user_id', '=', $user->id);
            })
            ->where('messages.sender_id', '!=', $user->id)
            ->whereNull('messages.deleted_at')
            ->whereRaw('messages.id > COALESCE(cp.last_read_message_id, 0)')
            ->selectRaw('messages.conversation_id as conversation_id, COUNT(*) as aggregate')
            ->groupBy('messages.conversation_id')
            ->pluck('aggregate', 'conversation_id')
            ->map(fn ($count) => (int) $count);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function conversationsFor(User $user): Collection
    {
        $unread = $this->unreadByConversation($user);

        return Conversation::query()
            ->forUser($user)
            ->with([
                'participants' => fn ($query) => $query
                    ->whereKeyNot($user->id)
                    ->with([
                        'activeMemberships.role:id,name',
                        'activeMemberships.hospital:id,name,short_name',
                    ]),
                'latestMessage',
            ])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Conversation $conversation) => $this->summary(
                $conversation,
                $user,
                (int) ($unread[$conversation->id] ?? 0),
            ))
            ->values();
    }

    public function findForUser(int $conversationId, User $user): ?Conversation
    {
        return Conversation::query()
            ->forUser($user)
            ->with([
                'participants' => fn ($query) => $query
                    ->whereKeyNot($user->id)
                    ->with([
                        'activeMemberships.role:id,name',
                        'activeMemberships.hospital:id,name,short_name',
                    ]),
            ])
            ->find($conversationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Conversation $conversation, User $user, ?int $unreadCount = null): array
    {
        $conversation->loadMissing([
            'participants' => fn ($query) => $query
                ->whereKeyNot($user->id)
                ->with([
                    'activeMemberships.role:id,name',
                    'activeMemberships.hospital:id,name,short_name',
                ]),
            'latestMessage',
        ]);

        $other = $conversation->participants->first();

        return [
            'id' => $conversation->id,
            'other_user' => $other ? $this->userSummary($other) : null,
            'other_last_read_message_id' => (int) ($other?->pivot?->last_read_message_id ?? 0),
            'last_message' => $conversation->latestMessage ? $this->messagePayload($conversation->latestMessage) : null,
            'last_message_at' => ($conversation->last_message_at ?? $conversation->created_at)?->toIso8601String(),
            'unread_count' => $unreadCount ?? $this->unreadCountForConversation($conversation, $user),
            'created_at' => $conversation->created_at?->toIso8601String(),
        ];
    }

    public function unreadCountForConversation(Conversation $conversation, User $user): int
    {
        return (int) Message::query()
            ->join('conversation_participants as cp', function ($join) use ($user) {
                $join->on('cp.conversation_id', '=', 'messages.conversation_id')
                    ->where('cp.user_id', '=', $user->id);
            })
            ->where('messages.conversation_id', $conversation->id)
            ->where('messages.sender_id', '!=', $user->id)
            ->whereNull('messages.deleted_at')
            ->whereRaw('messages.id > COALESCE(cp.last_read_message_id, 0)')
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function messagesFor(Conversation $conversation): array
    {
        return $conversation->messages()
            ->with('sender:id,name')
            ->latest('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $message) => $this->messagePayload($message))
            ->all();
    }

    public function findOrCreateDirect(User $user, User $other): Conversation
    {
        $key = Conversation::directKeyFor($user->id, $other->id);

        try {
            $conversation = Conversation::query()->firstOrCreate(['direct_key' => $key]);
        } catch (QueryException) {
            // A simultaneous request created the conversation first.
            $conversation = Conversation::query()->where('direct_key', $key)->firstOrFail();
        }

        $conversation->participants()->syncWithoutDetaching([$user->id, $other->id]);

        return $conversation;
    }

    /**
     * Advance the participant's read cursor. Returns the new cursor when it
     * moved forward, or null when there was nothing new to read.
     */
    public function markRead(Conversation $conversation, User $user): ?int
    {
        $latestId = (int) $conversation->messages()->max('id');

        if ($latestId === 0) {
            return null;
        }

        $participant = $conversation->participants()
            ->whereKey($user->id)
            ->first();

        $current = (int) ($participant?->pivot?->last_read_message_id ?? 0);

        if ($latestId <= $current) {
            return null;
        }

        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_message_id' => $latestId,
            'last_read_at' => now(),
        ]);

        return $latestId;
    }

    /**
     * @return array<string, mixed>
     */
    public function messagePayload(Message $message): array
    {
        $deleted = $message->isDeleted();

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'body' => $deleted ? null : $message->body,
            'deleted' => $deleted,
            'edited_at' => $message->edited_at?->toIso8601String(),
            'deleted_at' => $message->deleted_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function userSummary(User $user): array
    {
        if (isset($this->userCache[$user->id])) {
            return $this->userCache[$user->id];
        }

        $user->loadMissing([
            'activeMemberships.role:id,name',
            'activeMemberships.hospital:id,name,short_name',
        ]);

        $membership = $user->activeMemberships->first();
        $hospital = $membership?->hospital;

        return $this->userCache[$user->id] = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $membership?->role?->name,
            'hospital' => $hospital?->short_name ?: $hospital?->name,
            'hospital_name' => $hospital?->name,
        ];
    }

    public function participantIds(Conversation $conversation): array
    {
        return DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
