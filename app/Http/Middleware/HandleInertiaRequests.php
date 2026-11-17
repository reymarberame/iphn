<?php

namespace App\Http\Middleware;

use App\Models\BedCategory;
use App\Models\Region;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Services\HospitalAccessService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private readonly HospitalAccessService $access) {}

    public function share(Request $request): array
    {
        $user = $request->user();
        $context = $user ? $this->access->contextFor($user) : null;

        $settings = $user ? SystemSetting::query()->pluck('value', 'key') : collect();
        $notifications = null;
        $reference = null;

        if ($user) {
            $reference = [
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
                        ])->values(),
                    ])
                    ->values(),
                'categories' => BedCategory::query()
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'slug', 'is_er']),
                'services' => Service::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ];

            $recent = $user->notifications()->latest()->limit(8)->get();
            $notifications = [
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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $context,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'notifications' => $notifications,
            'reference' => $reference,
            'settings' => [
                'network_name' => $settings['network_name'] ?? 'Inter-Provincial Hospital Network',
                'freshness_fresh_minutes' => (int) ($settings['freshness_fresh_minutes'] ?? 15),
                'freshness_aging_minutes' => (int) ($settings['freshness_aging_minutes'] ?? 60),
                'reservation_ttl_minutes' => (int) ($settings['reservation_ttl_minutes'] ?? 120),
                'disclaimer' => $settings['availability_disclaimer'] ?? 'Availability is reported by each hospital and does not confirm admission or acceptance of a referral.',
                'support_email' => $settings['support_email'] ?? 'support@iphn.test',
            ],
        ];
    }
}
