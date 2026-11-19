<?php

namespace App\Http\Controllers;

use App\Events\ConversationRead;
use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $conversations = $this->chat->conversationsFor($user);
        $active = null;
        $messages = [];

        $activeId = (int) $request->query('conversation', 0);

        if ($activeId > 0 && ($conversation = $this->chat->findForUser($activeId, $user))) {
            $advanced = $this->chat->markRead($conversation, $user);

            if ($advanced) {
                ConversationRead::dispatch($conversation, $user, $advanced);
            }

            $conversation->refresh();

            $active = $this->chat->summary($conversation, $user, 0);
            $messages = $this->chat->messagesFor($conversation);

            $conversations = $conversations
                ->map(fn ($item) => (int) $item['id'] === $conversation->id ? $active : $item)
                ->sortByDesc(fn ($item) => $item['last_message_at'] ?? '')
                ->values();
        }

        $contacts = User::query()
            ->where('status', 'active')
            ->whereKeyNot($user->id)
            ->with([
                'activeMemberships.role:id,name',
                'activeMemberships.hospital:id,name,short_name',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (User $contact) => $this->chat->userSummary($contact))
            ->values();

        return Inertia::render('Messages/Index', [
            'conversations' => $conversations,
            'contacts' => $contacts,
            'activeConversation' => $active,
            'messages' => $messages,
            'unreadCount' => $this->chat->unreadCount($user),
        ]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorize('view', $conversation);

        $advanced = $this->chat->markRead($conversation, $user);

        if ($advanced) {
            ConversationRead::dispatch($conversation, $user, $advanced);
        }

        $conversation->refresh();

        return response()->json([
            'conversation' => $this->chat->summary($conversation, $user, 0),
            'messages' => $this->chat->messagesFor($conversation),
            'unread_count' => $this->chat->unreadCount($user),
        ]);
    }

    public function storeConversation(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('status', 'active'),
                Rule::notIn([$user->id]),
            ],
        ]);

        $other = User::query()->findOrFail($validated['user_id']);
        $conversation = $this->chat->findOrCreateDirect($user, $other);
        $conversation->refresh();

        $advanced = $this->chat->markRead($conversation, $user);

        if ($advanced) {
            ConversationRead::dispatch($conversation, $user, $advanced);
        }

        return response()->json([
            'conversation' => $this->chat->summary($conversation, $user, 0),
            'messages' => $this->chat->messagesFor($conversation),
            'unread_count' => $this->chat->unreadCount($user),
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorize('sendMessage', $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $body = trim($validated['body']);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Enter a message before sending.',
            ]);
        }

        $message = DB::transaction(function () use ($conversation, $user, $body) {
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => $body,
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();
            $this->chat->markRead($conversation, $user);

            return $message;
        });

        $message->load('sender');
        MessageSent::dispatch($message);

        $conversation->refresh();

        return response()->json([
            'message' => $this->chat->messagePayload($message),
            'conversation' => $this->chat->summary($conversation, $user, 0),
            'unread_count' => $this->chat->unreadCount($user),
        ]);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorize('view', $conversation);

        $advanced = $this->chat->markRead($conversation, $user);

        if ($advanced) {
            ConversationRead::dispatch($conversation, $user, $advanced);
        }

        return response()->json([
            'last_read_message_id' => $advanced ?? (int) $conversation->messages()->max('id'),
            'unread_count' => $this->chat->unreadCount($user),
        ]);
    }

    public function updateMessage(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $user = $request->user();
        $this->authorize('view', $conversation);
        $this->ensureMessageBelongsToConversation($conversation, $message);

        abort_if($message->sender_id !== $user->id, 403, 'You can only edit your own messages.');

        if ($message->isDeleted()) {
            throw ValidationException::withMessages([
                'body' => 'This message was deleted and can no longer be edited.',
            ]);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $body = trim($validated['body']);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Enter a message before saving.',
            ]);
        }

        if ($body !== $message->body) {
            $message->forceFill([
                'body' => $body,
                'edited_at' => now(),
            ])->save();

            MessageUpdated::dispatch($message);
        }

        $conversation->refresh();

        return response()->json([
            'message' => $this->chat->messagePayload($message),
            'conversation' => $this->chat->summary($conversation, $user),
            'unread_count' => $this->chat->unreadCount($user),
        ]);
    }

    public function destroyMessage(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $user = $request->user();
        $this->authorize('view', $conversation);
        $this->ensureMessageBelongsToConversation($conversation, $message);

        abort_if($message->sender_id !== $user->id, 403, 'You can only delete your own messages.');

        if (! $message->isDeleted()) {
            $message->forceFill([
                'body' => null,
                'deleted_at' => now(),
            ])->save();

            MessageDeleted::dispatch($message);
        }

        $conversation->refresh();

        return response()->json([
            'message' => $this->chat->messagePayload($message),
            'conversation' => $this->chat->summary($conversation, $user),
            'unread_count' => $this->chat->unreadCount($user),
        ]);
    }

    private function ensureMessageBelongsToConversation(Conversation $conversation, Message $message): void
    {
        abort_if($message->conversation_id !== $conversation->id, 404);
    }
}
