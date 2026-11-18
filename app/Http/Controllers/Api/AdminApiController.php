<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Hospital;
use App\Models\LoginLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HospitalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function hospitals(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if (! $this->access->hasPermission($user, 'hospitals.manage') && ! $this->access->hasPermission($user, 'hospitals.register')) {
            return $this->error('You do not have permission to manage hospital profiles.', 403);
        }

        $hospitals = $this->access->managementHospitals($user)->map(fn (Hospital $hospital) => [
            'id' => $hospital->id,
            'hospital_code' => $hospital->hospital_code,
            'name' => $hospital->name,
            'short_name' => $hospital->short_name,
            'ownership_type' => $hospital->ownership_type,
            'status' => $hospital->status,
            'location' => $hospital->location_label,
            'province' => $hospital->province?->name,
            'region' => $hospital->region?->short_name,
        ])->values();

        return $this->success($hospitals, 'Managed hospitals retrieved.', 200, ['count' => $hospitals->count()]);
    }

    public function users(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->access->loadContext($actor);

        if (! $this->access->hasPermission($actor, 'users.manage')) {
            return $this->error('You do not have permission to manage users.', 403);
        }

        $query = User::query()
            ->with(['roles:id,name,slug', 'memberships.hospital:id,name,short_name', 'memberships.role:id,name,slug'])
            ->when($request->query('search'), function ($builder, $search) {
                $builder->where(fn ($inner) => $inner
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'));
            })
            ->when($request->query('status'), fn ($builder, $status) => $builder->where('status', $status));

        if (! $this->access->isNetworkUser($actor) || (! $this->access->isSuperAdmin($actor) && $actor->adminScopes->isNotEmpty())) {
            $query->whereHas('memberships', fn ($builder) => $builder->whereIn('hospital_id', $this->access->managementHospitalIds($actor)));
        }

        $paginator = $query->orderBy('name')->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        $items = collect($paginator->items())->map(fn (User $user) => $this->userPayload($user))->values();

        return $this->success($items, 'Users retrieved.', 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function updateUserStatus(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
        ]);

        if ($validated['status'] !== 'active' && $request->user()->id === $user->id) {
            throw ValidationException::withMessages(['status' => 'You cannot suspend or deactivate your own account.']);
        }

        $user->forceFill(['status' => $validated['status']])->save();

        $this->audit->record('user.status.updated', $user, [
            'status' => $user->status,
        ], null, $request->user());

        return $this->success($this->userPayload($user->fresh(['roles', 'memberships.hospital', 'memberships.role'])), 'User status updated.');
    }

    public function audit(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if (! $this->access->hasPermission($user, 'audit.view')) {
            return $this->error('You do not have permission to review audit records.', 403);
        }

        $hospitalIds = $this->access->managementHospitalIds($user);
        $isNetwork = $this->access->isNetworkUser($user);

        $query = AuditLog::query()
            ->with(['user:id,name,email', 'hospital:id,name,short_name'])
            ->when($isNetwork, function ($builder) use ($hospitalIds) {
                $builder->where(fn ($inner) => $inner->whereIn('hospital_id', $hospitalIds)->orWhereNull('hospital_id'));
            }, fn ($builder) => $builder->whereIn('hospital_id', $hospitalIds))
            ->when($request->query('action'), fn ($builder, $action) => $builder->where('action', 'like', $action.'%'))
            ->when($request->query('hospital_id'), fn ($builder, $hospitalId) => $builder->where('hospital_id', $hospitalId))
            ->latest('created_at');

        $paginator = $query->paginate(min(100, max(1, (int) $request->query('per_page', 25))), ['*'], 'page');

        $items = collect($paginator->items())->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'action_label' => Str::headline(str_replace('.', ' ', $log->action)),
            'user' => $log->user?->name ?? 'System',
            'user_email' => $log->user?->email,
            'hospital' => $log->hospital?->short_name ?? $log->hospital?->name,
            'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
            'subject_id' => $log->subject_id,
            'changes' => $log->changes,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->values();

        $loginQuery = LoginLog::query();

        if (! $isNetwork) {
            $emails = User::query()
                ->whereHas('memberships', fn ($builder) => $builder->whereIn('hospital_id', $hospitalIds))
                ->pluck('email');

            $loginQuery->whereIn('email', $emails);
        }

        $logins = $loginQuery->latest('created_at')->limit(20)->get()->map(fn (LoginLog $log) => [
            'id' => $log->id,
            'email' => $log->email,
            'success' => $log->success,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->values();

        return $this->success([
            'logs' => $items,
            'logins' => $logins,
        ], 'Audit records retrieved.', 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        if (! $this->access->hasPermission($request->user(), 'settings.manage') || ! $this->access->isNetworkUser($request->user())) {
            return $this->error('Only network administrators can view platform settings.', 403);
        }

        $settings = SystemSetting::query()->pluck('value', 'key');

        return $this->success([
            'network_name' => $settings['network_name'] ?? 'Bukidnon Hospital Network',
            'freshness_fresh_minutes' => (int) ($settings['freshness_fresh_minutes'] ?? 15),
            'freshness_aging_minutes' => (int) ($settings['freshness_aging_minutes'] ?? 60),
            'reservation_ttl_minutes' => (int) ($settings['reservation_ttl_minutes'] ?? 120),
            'availability_disclaimer' => $settings['availability_disclaimer'] ?? config('bhn.disclaimer'),
            'support_email' => $settings['support_email'] ?? 'support@bhn.test',
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        if (! $this->access->hasPermission($request->user(), 'settings.manage') || ! $this->access->isNetworkUser($request->user())) {
            return $this->error('Only network administrators can change platform settings.', 403);
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
            SystemSetting::put($key, (string) $value, $request->user()->id);
        }

        $this->audit->record('settings.updated', null, $validated, null, $request->user());

        return $this->success($validated, 'Platform settings updated.');
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $user->roles->pluck('name')->values(),
            'memberships' => $user->memberships->map(fn ($membership) => [
                'hospital_id' => $membership->hospital_id,
                'hospital' => $membership->hospital?->short_name ?? $membership->hospital?->name,
                'role' => $membership->role?->name,
                'status' => $membership->status,
            ])->values(),
        ];
    }
}
