<?php

namespace App\Services;

use App\Models\Hospital;
use App\Models\Referral;
use App\Models\TransferRecord;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly BedAvailabilityService $availability,
    ) {}

    public function overview(User $user, array $filters = []): array
    {
        $this->access->loadContext($user);
        $hospitalIds = $this->access->managementHospitalIds($user);
        $isNetwork = $this->access->isNetworkUser($user);

        if (! empty($filters['hospital_id']) && in_array((int) $filters['hospital_id'], $hospitalIds, true)) {
            $hospitalIds = [(int) $filters['hospital_id']];
        }

        $from = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->subDays(29)->startOfDay();
        $to = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $hospitals = Hospital::query()
            ->whereIn('id', $hospitalIds)
            ->with(['municipality.province.region'])
            ->orderBy('name')
            ->get();

        $summaries = $hospitals->map(fn (Hospital $hospital) => $this->availability->hospitalSummary($hospital))->values();

        $counts = $this->availability->aggregateStatusCounts($hospitalIds);
        $categories = $this->availability->aggregateCategoryBreakdown($hospitalIds);

        $referralQuery = Referral::query()
            ->where(fn ($builder) => $builder
                ->whereIn('referring_hospital_id', $hospitalIds)
                ->orWhereIn('receiving_hospital_id', $hospitalIds))
            ->whereBetween('created_at', [$from, $to]);

        $referralStatusCounts = (clone $referralQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $referralByStatus = collect(Referral::STATUS_LABELS)->map(fn ($label, $status) => [
            'status' => $status,
            'label' => $label,
            'count' => (int) ($referralStatusCounts[$status] ?? 0),
        ])->values();

        $referralTotal = (int) $referralStatusCounts->sum();
        $acceptedCount = (int) $referralStatusCounts->only([
            Referral::STATUS_ACCEPTED, Referral::STATUS_TRANSFER_SCHEDULED,
            Referral::STATUS_IN_TRANSIT, Referral::STATUS_ARRIVED, Referral::STATUS_COMPLETED,
        ])->sum();
        $declinedCount = (int) ($referralStatusCounts[Referral::STATUS_DECLINED] ?? 0);
        $completedCount = (int) ($referralStatusCounts[Referral::STATUS_COMPLETED] ?? 0);

        $transferQuery = TransferRecord::query()
            ->whereHas('referral', fn ($builder) => $builder
                ->whereIn('referring_hospital_id', $hospitalIds)
                ->orWhereIn('receiving_hospital_id', $hospitalIds))
            ->whereBetween('created_at', [$from, $to]);

        $transferStatusCounts = (clone $transferQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $transferByStatus = collect(TransferRecord::STATUS_LABELS)->map(fn ($label, $status) => [
            'status' => $status,
            'label' => $label,
            'count' => (int) ($transferStatusCounts[$status] ?? 0),
        ])->values();

        $completedTransfers = (clone $transferQuery)
            ->whereNotNull('departure_at')
            ->whereNotNull('arrival_at')
            ->get(['departure_at', 'arrival_at']);

        $averageTransferMinutes = $completedTransfers->isEmpty()
            ? null
            : (int) round($completedTransfers->avg(fn (TransferRecord $transfer) => abs($transfer->departure_at->diffInMinutes($transfer->arrival_at))));

        $compliance = $summaries->map(function (array $summary) {
            $minutes = $summary['last_updated_at']
                ? (int) abs(Carbon::parse($summary['last_updated_at'])->diffInMinutes(now()))
                : null;

            return [
                'hospital_id' => $summary['hospital_id'],
                'hospital_name' => $summary['hospital_name'],
                'freshness' => $summary['freshness'],
                'freshness_label' => $summary['freshness_label'],
                'last_updated_at' => $summary['last_updated_at'],
                'last_updated_by' => $summary['last_updated_by'],
                'minutes_since_update' => $minutes,
            ];
        })->sortByDesc(fn ($row) => $row['minutes_since_update'] ?? 999999)->values();

        $freshCount = $compliance->where('freshness', 'fresh')->count();
        $complianceRate = $compliance->isEmpty() ? 0 : (int) round(($freshCount / $compliance->count()) * 100);

        $trendStart = now()->subDays(13)->startOfDay();
        $trendCounts = Referral::query()
            ->where(fn ($builder) => $builder
                ->whereIn('referring_hospital_id', $hospitalIds)
                ->orWhereIn('receiving_hospital_id', $hospitalIds))
            ->where('created_at', '>=', $trendStart)
            ->selectRaw('DATE(created_at) as day, count(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $trend = collect(range(0, 13))->map(function (int $offset) use ($trendCounts) {
            $day = now()->subDays(13 - $offset)->toDateString();

            return [
                'date' => $day,
                'label' => Carbon::parse($day)->format('M j'),
                'count' => (int) ($trendCounts[$day] ?? 0),
            ];
        })->values();

        return [
            'scope' => [
                'label' => $this->access->scopeLabel($user),
                'is_network' => $isNetwork,
                'hospital_count' => count($hospitalIds),
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $from->format('M j, Y').' – '.$to->format('M j, Y'),
            ],
            'totals' => [
                'beds' => $counts['total'],
                'available' => $counts['available'],
                'occupied' => $counts['occupied'],
                'reserved' => $counts['reserved'],
                'cleaning' => $counts['cleaning'],
                'out_of_service' => $counts['out_of_service'],
                'operational' => $counts['operational'],
                'referrals' => $referralTotal,
                'accepted' => $acceptedCount,
                'declined' => $declinedCount,
                'completed' => $completedCount,
                'avg_transfer_minutes' => $averageTransferMinutes,
                'fresh_reports' => $freshCount,
                'compliance_rate' => $complianceRate,
            ],
            'categories' => $categories,
            'referral_statuses' => $referralByStatus,
            'transfer_statuses' => $transferByStatus,
            'rates' => [
                'acceptance_rate' => $referralTotal > 0 ? (int) round(($acceptedCount / $referralTotal) * 100) : 0,
                'decline_rate' => $referralTotal > 0 ? (int) round(($declinedCount / $referralTotal) * 100) : 0,
                'completion_rate' => $acceptedCount > 0 ? (int) round(($completedCount / $acceptedCount) * 100) : 0,
            ],
            'hospitals' => $summaries,
            'compliance' => $compliance,
            'trend' => $trend,
        ];
    }

    public function availabilityRows(User $user): Collection
    {
        $ids = $this->access->managementHospitalIds($user);

        return Hospital::query()
            ->whereIn('id', $ids)
            ->with(['municipality.province.region'])
            ->orderBy('name')
            ->get()
            ->flatMap(function (Hospital $hospital) {
                $summary = $this->availability->hospitalSummary($hospital);

                return collect($this->availability->categoryBreakdown($hospital->id))
                    ->map(fn ($category) => [
                        'hospital' => $hospital->name,
                        'code' => $hospital->hospital_code,
                        'province' => $summary['province'],
                        'category' => $category['name'],
                        'total' => $category['total'],
                        'available' => $category['available'],
                        'occupied' => $category['occupied'],
                        'reserved' => $category['reserved'],
                        'cleaning' => $category['cleaning'],
                        'out_of_service' => $category['out_of_service'],
                        'last_updated_at' => $summary['last_updated_at'],
                        'freshness' => $summary['freshness'],
                    ]);
            })
            ->values();
    }

    public function referralRows(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $ids = $this->access->managementHospitalIds($user);

        return Referral::query()
            ->where(fn ($builder) => $builder
                ->whereIn('referring_hospital_id', $ids)
                ->orWhereIn('receiving_hospital_id', $ids))
            ->whereBetween('created_at', [$from, $to])
            ->with(['referringHospital:id,name', 'receivingHospital:id,name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Referral $referral) => [
                'referral_number' => $referral->referral_number,
                'referring_hospital' => $referral->referringHospital?->name,
                'receiving_hospital' => $referral->receivingHospital?->name,
                'patient_reference' => $referral->patient_reference,
                'urgency' => $referral->urgencyLabel(),
                'status' => $referral->statusLabel(),
                'submitted_at' => $referral->submitted_at?->toDateTimeString(),
                'accepted_at' => $referral->accepted_at?->toDateTimeString(),
                'closed_at' => $referral->closed_at?->toDateTimeString(),
                'created_at' => $referral->created_at?->toDateTimeString(),
            ]);
    }

    public function transferRows(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $ids = $this->access->managementHospitalIds($user);

        return TransferRecord::query()
            ->whereHas('referral', fn ($builder) => $builder
                ->whereIn('referring_hospital_id', $ids)
                ->orWhereIn('receiving_hospital_id', $ids))
            ->whereBetween('created_at', [$from, $to])
            ->with(['referral.referringHospital:id,name', 'referral.receivingHospital:id,name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (TransferRecord $transfer) => [
                'referral_number' => $transfer->referral?->referral_number,
                'referring_hospital' => $transfer->referral?->referringHospital?->name,
                'receiving_hospital' => $transfer->referral?->receivingHospital?->name,
                'status' => $transfer->statusLabel(),
                'transport' => TransferRecord::TRANSPORT_LABELS[$transfer->transport_type] ?? $transfer->transport_type,
                'departure_at' => $transfer->departure_at?->toDateTimeString(),
                'estimated_arrival_at' => $transfer->estimated_arrival_at?->toDateTimeString(),
                'arrival_at' => $transfer->arrival_at?->toDateTimeString(),
                'duration_minutes' => $transfer->departure_at && $transfer->arrival_at
                    ? (int) round(abs($transfer->departure_at->diffInMinutes($transfer->arrival_at)))
                    : null,
            ]);
    }
}
