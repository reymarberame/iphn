<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\HospitalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingController extends Controller
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $this->access->hasPermission($user, 'settings.manage') || ! $this->access->isNetworkUser($user)) {
            abort(403, 'Only network administrators can change platform settings.');
        }

        $settings = SystemSetting::query()->pluck('value', 'key');

        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'network_name' => $settings['network_name'] ?? 'Bukidnon Hospital Network',
                'freshness_fresh_minutes' => (int) ($settings['freshness_fresh_minutes'] ?? 15),
                'freshness_aging_minutes' => (int) ($settings['freshness_aging_minutes'] ?? 60),
                'reservation_ttl_minutes' => (int) ($settings['reservation_ttl_minutes'] ?? 120),
                'availability_disclaimer' => $settings['availability_disclaimer'] ?? config('bhn.disclaimer'),
                'support_email' => $settings['support_email'] ?? 'support@bhn.test',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->access->hasPermission($user, 'settings.manage') || ! $this->access->isNetworkUser($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'network_name' => ['required', 'string', 'max:120'],
            'freshness_fresh_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'freshness_aging_minutes' => ['required', 'integer', 'min:10', 'max:240', 'gt:freshness_fresh_minutes'],
            'reservation_ttl_minutes' => ['required', 'integer', 'min:30', 'max:720'],
            'availability_disclaimer' => ['required', 'string', 'max:1000'],
            'support_email' => ['required', 'email', 'max:120'],
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::put($key, (string) $value, $user->id);
        }

        Cache::forget(HandleInertiaRequests::SETTINGS_CACHE_KEY);

        $this->audit->record('settings.updated', null, $validated, null, $user);

        return back()->with('success', 'Platform settings updated. Freshness thresholds apply immediately.');
    }
}
