<?php

namespace App\Http\Middleware;

use App\Models\BedCategory;
use App\Models\Region;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ChatService;
use App\Services\HospitalAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public const REFERENCE_CACHE_KEY = 'bhn.reference-data.v1';

    public const SETTINGS_CACHE_KEY = 'bhn.settings.v1';

    protected $rootView = 'app';

    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly ChatService $chat,
    ) {}

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $user ? $this->access->contextFor($user) : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'notifications' => $user ? fn () => $this->notifications($user) : null,
            'chat' => $user ? fn () => ['unread_count' => $this->chat->unreadCount($user)] : null,
            'reference' => $user ? fn () => $this->referenceData() : null,
            'settings' => fn () => $this->settings($user !== null),
            'reverb' => fn () => $this->reverb($request),
        ];
    }

    /**
     * Browser WebSocket settings for Reverb. The host falls back to the
     * current request so the app keeps working behind a single HTTPS proxy.
     */
    private function reverb(Request $request): ?array
    {
        $key = config('bhn.reverb.key');

        if (blank($key)) {
            return null;
        }

        $scheme = config('bhn.reverb.scheme') ?: $request->getScheme();

        return [
            'key' => $key,
            'host' => config('bhn.reverb.host') ?: $request->getHost(),
            'port' => (int) (config('bhn.reverb.port') ?: ($scheme === 'https' ? 443 : 80)),
            'scheme' => $scheme,
        ];
    }

    private function notifications(User $user): array
    {
        $recent = $user->notifications()->latest()->limit(8)->get();

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $recent->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? '/notifications',
                'type' => $notification->data['type'] ?? 'system',
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function referenceData(): array
    {
        return Cache::remember(self::REFERENCE_CACHE_KEY, now()->addMinutes(10), function () {
            return [
                'regions' => Region::query()
                    ->with(['provinces:id,region_id,name'])
                    ->orderBy('name')
                    ->get(['id', 'name', 'short_name'])
                    ->map(fn (Region $region) => [
                        'id' => $region->id,
                        'name' => $region->name,
                        'short_name' => $region->short_name,
                        'provinces' => $region->provinces->map(fn ($province) => [
                            'id' => $province->id,
                            'name' => $province->name,
                        ])->values()->all(),
                    ])
                    ->values()
                    ->all(),
                'categories' => BedCategory::query()
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'slug', 'is_er'])
                    ->toArray(),
                'services' => Service::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->toArray(),
            ];
        });
    }

    private function settings(bool $loadFromDatabase): array
    {
        $settings = $loadFromDatabase
            ? Cache::remember(self::SETTINGS_CACHE_KEY, now()->addMinutes(10), fn () => SystemSetting::query()->pluck('value', 'key')->all())
            : [];

        return [
            'network_name' => $settings['network_name'] ?? 'Bukidnon Hospital Network',
            'freshness_fresh_minutes' => (int) ($settings['freshness_fresh_minutes'] ?? 15),
            'freshness_aging_minutes' => (int) ($settings['freshness_aging_minutes'] ?? 60),
            'reservation_ttl_minutes' => (int) ($settings['reservation_ttl_minutes'] ?? 120),
            'disclaimer' => $settings['availability_disclaimer'] ?? 'Availability is reported by each hospital and does not confirm admission or acceptance of a referral.',
            'support_email' => $settings['support_email'] ?? 'support@bhn.test',
        ];
    }
}
