<?php

namespace App\Support;

class RealtimeTopics
{
    /**
     * Derive an unguessable public Realtime topic for a user.
     *
     * The topic is deterministic so the server can broadcast to the same
     * channel the browser subscribes to, without sending a JWT to the client.
     */
    public static function forUser(int $userId): string
    {
        return 'chat.'.substr(
            hash_hmac('sha256', (string) $userId, (string) config('app.key')),
            0,
            32
        );
    }
}
