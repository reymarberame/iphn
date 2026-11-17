<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\HospitalAccessService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly ReportService $reports,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        if (! $this->canReport($request)) {
            return $this->error('You do not have permission to view reports.', 403);
        }

        return $this->success(
            $this->reports->overview($request->user(), $request->only(['date_from', 'date_to', 'hospital_id']))
        );
    }

    public function availability(Request $request): JsonResponse
    {
        if (! $this->canReport($request)) {
            return $this->error('You do not have permission to view reports.', 403);
        }

        $rows = $this->reports->availabilityRows($request->user());

        return $this->success($rows, 'Availability report rows.', 200, ['count' => $rows->count()]);
    }

    public function referrals(Request $request): JsonResponse
    {
        if (! $this->canReport($request)) {
            return $this->error('You do not have permission to view reports.', 403);
        }

        if ($this->access->isNetworkUser($request->user())) {
            return $this->error('Patient-linked referral rows are restricted to the referring and receiving hospitals.', 403);
        }

        [$from, $to] = $this->period($request);
        $rows = $this->reports->referralRows($request->user(), $from, $to);

        return $this->success($rows, 'Referral report rows.', 200, [
            'count' => $rows->count(),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function transfers(Request $request): JsonResponse
    {
        if (! $this->canReport($request)) {
            return $this->error('You do not have permission to view reports.', 403);
        }

        if ($this->access->isNetworkUser($request->user())) {
            return $this->error('Patient-linked transfer rows are restricted to the referring and receiving hospitals.', 403);
        }

        [$from, $to] = $this->period($request);
        $rows = $this->reports->transferRows($request->user(), $from, $to);

        return $this->success($rows, 'Transfer report rows.', 200, [
            'count' => $rows->count(),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    private function canReport(Request $request): bool
    {
        $this->access->loadContext($request->user());

        return $this->access->hasPermission($request->user(), 'reports.view')
            || $this->access->hasPermission($request->user(), 'reports.national');
    }

    private function period(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->subDays(29)->startOfDay();
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
