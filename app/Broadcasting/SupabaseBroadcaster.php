<?php

namespace App\Broadcasting;

use App\Support\RealtimeTopics;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Broadcasts Laravel events through Supabase Realtime's REST API.
 *
 * Events keep broadcasting to Laravel "user.{id}" channels; this broadcaster
 * translates them into unguessable public Supabase topics.
 */
class SupabaseBroadcaster extends Broadcaster
{
    public function __construct(
        private readonly string $url,
        private readonly string $secret,
    ) {}

    public function auth($request)
    {
        return null;
    }

    public function validAuthenticationResponse($request, $result)
    {
        return [];
    }

    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $messages = [];

        foreach ($channels as $channel) {
            $name = is_object($channel) && property_exists($channel, 'name')
                ? $channel->name
                : (string) $channel;

            if (! Str::startsWith($name, 'user.')) {
                continue;
            }

            $userId = (int) Str::after($name, 'user.');

            if ($userId <= 0) {
                continue;
            }

            $messages[] = [
                'topic' => RealtimeTopics::forUser($userId),
                'event' => $event,
            ];
        }

        if ($messages === []) {
            return;
        }

        try {
            foreach ($messages as $message) {
                $url = rtrim($this->url, '/')
                    .'/realtime/v1/api/broadcast/'.rawurlencode($message['topic'])
                    .'/events/'.rawurlencode($message['event']);

                $response = Http::withHeaders([
                    'apikey' => $this->secret,
                    'Authorization' => 'Bearer '.$this->secret,
                    'Content-Type' => 'application/json',
                ])->timeout(8)->post($url, $payload);

                if (! $response->successful()) {
                    Log::warning('Supabase Realtime broadcast failed', [
                        'topic' => $message['topic'],
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Supabase Realtime broadcast error', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
