<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->notifications()
            ->latest()
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        $items = collect($query->items())->map(fn ($notification) => [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? 'Notification',
            'message' => $notification->data['message'] ?? '',
            'url' => $notification->data['url'] ?? null,
            'type' => $notification->data['type'] ?? 'system',
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ])->values();

        return $this->success($items, 'Notifications retrieved.', 200, [
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'pagination' => [
                'current_page' => $query->currentPage(),
                'per_page' => $query->perPage(),
                'total' => $query->total(),
                'last_page' => $query->lastPage(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if (! $notification) {
            return $this->error('Notification not found.', 404);
        }

        $notification->markAsRead();

        return $this->success(['read_at' => $notification->read_at?->toIso8601String()], 'Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->success(['unread_count' => 0], 'All notifications marked as read.');
    }
}
