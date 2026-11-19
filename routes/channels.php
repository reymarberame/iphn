<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Chat uses a private channel per user so messages and unread badges reach
| the recipient on any page. The channel name must match the authenticated
| user id; inactive accounts are rejected even if a session is still open.
|
*/

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->isActive() && (int) $user->id === $userId;
});
