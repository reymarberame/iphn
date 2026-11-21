<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\HospitalAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditController extends Controller
{
    public function __construct(private readonly HospitalAccessService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if (! $this->access->hasPermission($user, 'audit.view')) {
            abort(403, 'You do not have permission to review audit records.');
        }

        $hospitalIds = $this->access->managementHospitalIds($user);
        $isNetwork = $this->access->isNetworkUser($user);

        $filters = $request->only(['search', 'action', 'hospital_id', 'tab']);

        $logsQuery = AuditLog::query()
            ->with(['user:id,name,email', 'hospital:id,name,short_name'])
            ->when($isNetwork, function ($builder) use ($hospitalIds) {
                $builder->where(fn ($inner) => $inner
                    ->whereIn('hospital_id', $hospitalIds)
                    ->orWhereNull('hospital_id'));
            }, fn ($builder) => $builder->whereIn('hospital_id', $hospitalIds))
            ->when($filters['action'] ?? null, fn ($builder, $action) => $builder->whereLike('action', $action.'%'))
            ->when($filters['hospital_id'] ?? null, fn ($builder, $hospitalId) => $builder->where('hospital_id', $hospitalId))
            ->when($filters['search'] ?? null, function ($builder, $search) {
                $builder->where(fn ($inner) => $inner
                    ->whereLike('action', '%'.$search.'%')
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->whereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')));
            })
            ->latest('created_at');

        $logs = $logsQuery->paginate(25, ['*'], 'logs_page')->withQueryString()->through(fn (AuditLog $log) => [
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
        ]);

        $loginQuery = LoginLog::query();

        if (! $isNetwork) {
            $emails = User::query()
                ->whereHas('memberships', fn ($builder) => $builder->whereIn('hospital_id', $hospitalIds))
                ->pluck('email');

            $loginQuery->whereIn('email', $emails);
        }

        $logins = $loginQuery
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (LoginLog $log) => [
                'id' => $log->id,
                'email' => $log->email,
                'success' => $log->success,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
            'logins' => $logins,
            'filters' => $filters,
            'hospitals' => $this->access->managementHospitals($user)
                ->map(fn ($hospital) => ['id' => $hospital->id, 'name' => $hospital->name, 'short_name' => $hospital->short_name])
                ->values(),
            'isNetworkUser' => $isNetwork,
        ]);
    }
}
