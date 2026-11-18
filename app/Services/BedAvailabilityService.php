<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\BedAvailabilitySnapshot;
use App\Models\BedCategory;
use App\Models\BedStatusHistory;
use App\Models\Hospital;
use App\Models\Referral;
use App\Models\SystemSetting;
use App\Models\TransferRecord;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BedAvailabilityService
{
    private ?int $freshMinutes = null;

    private ?int $agingMinutes = null;

    public function __construct(private readonly HospitalAccessService $access) {}

    public function freshMinutes(): int
    {
        return $this->freshMinutes ??= SystemSetting::freshnessMinutes();
    }

    public function agingMinutes(): int
    {
        return $this->agingMinutes ??= SystemSetting::agingMinutes();
    }

    public function freshness(?CarbonInterface $lastUpdated): string
    {
        if (! $lastUpdated) {
            return 'unknown';
        }

        $minutes = (int) abs($lastUpdated->diffInMinutes(now()));

        if ($minutes <= $this->freshMinutes()) {
            return 'fresh';
        }

        if ($minutes <= $this->agingMinutes()) {
            return 'aging';
        }

        return 'stale';
    }

    public function freshnessLabel(string $state): string
    {
        return match ($state) {
            'fresh' => 'Fresh',
            'aging' => 'Aging',
            'stale' => 'Stale',
            default => 'No report',
        };
    }

    public function freshnessDescription(string $state): string
    {
        return match ($state) {
            'fresh' => 'Updated within '.$this->freshMinutes().' minutes',
            'aging' => 'Updated '.$this->freshMinutes().'–'.$this->agingMinutes().' minutes ago',
            'stale' => 'Not confirmed for more than '.$this->agingMinutes().' minutes',
            default => 'No bed update has been recorded',
        };
    }

    /**
     * Current counts derive from bed-level status. Status is never entered as a total.
     */
    public function statusCounts(int $hospitalId): array
    {
        $rows = Bed::query()
            ->where('hospital_id', $hospitalId)
            ->where('active', true)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [
            'total' => (int) $rows->sum(),
            'available' => (int) ($rows[Bed::STATUS_AVAILABLE] ?? 0),
            'occupied' => (int) ($rows[Bed::STATUS_OCCUPIED] ?? 0),
            'reserved' => (int) ($rows[Bed::STATUS_RESERVED] ?? 0),
            'cleaning' => (int) ($rows[Bed::STATUS_CLEANING] ?? 0),
            'out_of_service' => (int) ($rows[Bed::STATUS_OUT_OF_SERVICE] ?? 0),
        ];

        $counts['operational'] = $counts['total'] - $counts['out_of_service'];

        return $counts;
    }

    public function hospitalSummary(Hospital|int $hospital): array
    {
        $hospital = $hospital instanceof Hospital
            ? $hospital
            : Hospital::query()->with('municipality.province.region')->findOrFail($hospital);

        $counts = $this->statusCounts($hospital->id);

        $latest = BedStatusHistory::query()
            ->where('hospital_id', $hospital->id)
            ->with('changer:id,name')
            ->latest('changed_at')
            ->first();

        $lastUpdated = $latest?->changed_at;
        $freshness = $this->freshness($lastUpdated);

        return [
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->name,
            'hospital_code' => $hospital->hospital_code,
            'ownership_type' => $hospital->ownership_type,
            'facility_type' => $hospital->facility_type,
            'status' => $hospital->status,
            'province' => $hospital->province?->name,
            'region' => $hospital->region?->short_name ?? $hospital->region?->name,
            'municipality' => $hospital->municipality?->name,
            'counts' => $counts,
            'last_updated_at' => $lastUpdated?->toIso8601String(),
            'last_updated_by' => $latest?->changer?->name,
            'freshness' => $freshness,
            'freshness_label' => $this->freshnessLabel($freshness),
        ];
    }

    public function categoryBreakdown(int $hospitalId, ?string $categorySlug = null): array
    {
        $rows = Bed::query()
            ->join('bed_categories', 'beds.bed_category_id', '=', 'bed_categories.id')
            ->where('beds.hospital_id', $hospitalId)
            ->where('beds.active', true)
            ->when($categorySlug, fn (Builder $q) => $q->where('bed_categories.slug', $categorySlug))
            ->selectRaw('bed_categories.id as category_id, bed_categories.name, bed_categories.slug, bed_categories.is_er, beds.status, count(*) as aggregate')
            ->groupBy('bed_categories.id', 'bed_categories.name', 'bed_categories.slug', 'bed_categories.is_er', 'beds.status')
            ->get();

        $categories = BedCategory::query()->where('active', true)->orderBy('sort_order')->get();

        return $categories->map(function (BedCategory $category) use ($rows) {
            $categoryRows = $rows->where('category_id', $category->id);
            $statusCounts = $categoryRows->pluck('aggregate', 'status');
            $total = (int) $categoryRows->sum('aggregate');
            $outOfService = (int) ($statusCounts[Bed::STATUS_OUT_OF_SERVICE] ?? 0);

            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_er' => $category->is_er,
                'total' => $total,
                'available' => (int) ($statusCounts[Bed::STATUS_AVAILABLE] ?? 0),
                'occupied' => (int) ($statusCounts[Bed::STATUS_OCCUPIED] ?? 0),
                'reserved' => (int) ($statusCounts[Bed::STATUS_RESERVED] ?? 0),
                'cleaning' => (int) ($statusCounts[Bed::STATUS_CLEANING] ?? 0),
                'out_of_service' => $outOfService,
                'operational' => $total - $outOfService,
            ];
        })->filter(fn (array $category) => $category['total'] > 0)->values()->all();
    }

    public function hospitalAvailability(Hospital|int $hospital): array
    {
        $summary = $this->hospitalSummary($hospital);

        $wards = DB::table('beds')
            ->join('rooms', 'beds.room_id', '=', 'rooms.id')
            ->join('wards', 'rooms.ward_id', '=', 'wards.id')
            ->where('beds.hospital_id', $summary['hospital_id'])
            ->where('beds.active', true)
            ->selectRaw('wards.id, wards.name, wards.ward_type, beds.status, count(*) as aggregate')
            ->groupBy('wards.id', 'wards.name', 'wards.ward_type', 'beds.status')
            ->get()
            ->groupBy('id')
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'id' => $first->id,
                    'name' => $first->name,
                    'ward_type' => $first->ward_type,
                    'total' => (int) $rows->sum('aggregate'),
                    'available' => (int) $rows->where('status', Bed::STATUS_AVAILABLE)->sum('aggregate'),
                    'occupied' => (int) $rows->where('status', Bed::STATUS_OCCUPIED)->sum('aggregate'),
                    'reserved' => (int) $rows->where('status', Bed::STATUS_RESERVED)->sum('aggregate'),
                    'cleaning' => (int) $rows->where('status', Bed::STATUS_CLEANING)->sum('aggregate'),
                    'out_of_service' => (int) $rows->where('status', Bed::STATUS_OUT_OF_SERVICE)->sum('aggregate'),
                ];
            })
            ->values()
            ->all();

        $summary['categories'] = $this->categoryBreakdown($summary['hospital_id']);
        $summary['wards'] = $wards;
        $summary['is_stale'] = $summary['freshness'] === 'stale';

        return $summary;
    }

    /**
     * Filters used by the network/regional dashboard, directory and search.
     */
    public function filteredHospitalsQuery(User $user, array $filters = []): Builder
    {
        return $this->access->directoryHospitalsQuery($user)
            ->with(['municipality.province.region', 'services'])
            ->when($filters['region_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('municipality.province', fn (Builder $p) => $p->where('region_id', $v)))
            ->when($filters['province_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('municipality', fn (Builder $m) => $m->where('province_id', $v)))
            ->when($filters['municipality_id'] ?? null, fn (Builder $q, $v) => $q->where('municipality_id', $v))
            ->when($filters['ownership_type'] ?? null, fn (Builder $q, $v) => $q->where('ownership_type', $v))
            ->when($filters['facility_type'] ?? null, fn (Builder $q, $v) => $q->where('facility_type', $v))
            ->when($filters['hospital_id'] ?? null, fn (Builder $q, $v) => $q->whereKey($v))
            ->when($filters['service_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('services', fn (Builder $s) => $s->where('services.id', $v)))
            ->when($filters['search'] ?? null, function (Builder $q, $v) {
                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', '%'.$v.'%')
                    ->orWhere('hospital_code', 'like', '%'.$v.'%')
                    ->orWhere('short_name', 'like', '%'.$v.'%'));
            })
            ->when($filters['category'] ?? null, function (Builder $q, $v) {
                $q->whereHas('beds', fn (Builder $b) => $b->where('active', true)
                    ->where('status', Bed::STATUS_AVAILABLE)
                    ->whereHas('category', fn (Builder $c) => $c->where('slug', $v)));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v), function (Builder $q) {
                $q->whereIn('status', [Hospital::STATUS_OPERATIONAL, Hospital::STATUS_LIMITED]);
            })
            ->orderBy('name');
    }

    public function networkSummary(User $user, array $filters = []): array
    {
        $hospitals = $this->filteredHospitalsQuery($user, $filters)->limit(200)->get();
        $ids = $hospitals->pluck('id')->all();

        $counts = $this->aggregateStatusCounts($ids);
        $categoryBreakdown = $this->aggregateCategoryBreakdown($ids, $filters['category'] ?? null);
        $summaries = $hospitals->map(fn (Hospital $hospital) => $this->hospitalSummary($hospital))->values();
        $freshnessCounts = $summaries->countBy('freshness');

        $erAvailable = Bed::query()
            ->whereIn('hospital_id', $ids)
            ->where('active', true)
            ->where('status', Bed::STATUS_AVAILABLE)
            ->whereHas('category', fn (Builder $q) => $q->where('is_er', true))
            ->count();

        $referralCounts = $this->referralCounts($ids, $user);

        return [
            'scope' => [
                'label' => $this->access->scopeLabel($user),
                'is_network' => true,
            ],
            'totals' => [
                'hospitals' => $hospitals->count(),
                'available' => $counts['available'],
                'available_er' => $erAvailable,
                'available_inpatient' => max(0, $counts['available'] - $erAvailable),
                'occupied' => $counts['occupied'],
                'reserved' => $counts['reserved'],
                'cleaning' => $counts['cleaning'],
                'out_of_service' => $counts['out_of_service'],
                'total_beds' => $counts['total'],
                'operational' => $counts['operational'],
                'pending_referrals' => $referralCounts['pending'],
                'stale_reports' => (int) ($freshnessCounts['stale'] ?? 0),
                'aging_reports' => (int) ($freshnessCounts['aging'] ?? 0),
                'fresh_reports' => (int) ($freshnessCounts['fresh'] ?? 0),
                'freshness' => $this->freshness($summaries->max(fn ($s) => $s['last_updated_at'] ? Carbon::parse($s['last_updated_at']) : null)),
            ],
            'categories' => $categoryBreakdown,
            'hospitals' => $summaries,
            'referrals' => $referralCounts,
            'freshness' => [
                'fresh' => (int) ($freshnessCounts['fresh'] ?? 0),
                'aging' => (int) ($freshnessCounts['aging'] ?? 0),
                'stale' => (int) ($freshnessCounts['stale'] ?? 0),
                'unknown' => (int) ($freshnessCounts['unknown'] ?? 0),
                'policy' => [
                    'fresh_minutes' => $this->freshMinutes(),
                    'aging_minutes' => $this->agingMinutes(),
                ],
            ],
            'attention' => $summaries->filter(fn ($s) => in_array($s['freshness'], ['stale', 'aging'], true))
                ->sortBy(fn ($s) => $s['freshness'] === 'stale' ? 0 : 1)
                ->values()
                ->all(),
        ];
    }

    public function hospitalDashboard(Hospital $hospital): array
    {
        $availability = $this->hospitalAvailability($hospital);

        $incoming = Referral::query()
            ->where('receiving_hospital_id', $hospital->id)
            ->whereIn('status', [Referral::STATUS_SUBMITTED, Referral::STATUS_UNDER_REVIEW, Referral::STATUS_MORE_INFO, Referral::STATUS_ACCEPTED])
            ->count();

        $outgoing = Referral::query()
            ->where('referring_hospital_id', $hospital->id)
            ->whereNotIn('status', [Referral::STATUS_COMPLETED, Referral::STATUS_CANCELLED, Referral::STATUS_DECLINED, Referral::STATUS_EXPIRED])
            ->count();

        $transfers = TransferRecord::query()
            ->whereHas('referral', fn (Builder $q) => $q->where('receiving_hospital_id', $hospital->id)->orWhere('referring_hospital_id', $hospital->id))
            ->whereIn('status', ['scheduled', 'ready', 'in_transit'])
            ->count();

        return [
            ...$availability,
            'referrals' => [
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'active_transfers' => $transfers,
            ],
        ];
    }

    public function directory(User $user, array $filters = []): Collection
    {
        $hospitals = $this->filteredHospitalsQuery($user, $filters)->limit(100)->get();

        return $hospitals->map(fn (Hospital $hospital) => [
            ...$this->hospitalSummary($hospital),
            'short_name' => $hospital->short_name,
            'address' => $hospital->address,
            'phone' => $hospital->phone,
            'email' => $hospital->email,
            'location' => $hospital->location_label,
            'province_id' => $hospital->municipality?->province_id,
            'region_id' => $hospital->province?->region_id,
            'categories' => $this->categoryBreakdown($hospital->id),
            'services' => $hospital->services->pluck('name')->values()->all(),
        ]);
    }

    public function availabilityRows(User $user, array $filters = []): array
    {
        $hospitals = $this->filteredHospitalsQuery($user, $filters)->limit(100)->get();
        $rows = [];

        foreach ($hospitals as $hospital) {
            $summary = $this->hospitalSummary($hospital);
            $categories = $this->categoryBreakdown($hospital->id, $filters['category'] ?? null);

            foreach ($categories as $category) {
                $rows[] = [
                    'hospital_id' => $hospital->id,
                    'hospital_name' => $hospital->name,
                    'province' => $summary['province'],
                    'region' => $summary['region'],
                    'ownership_type' => $hospital->ownership_type,
                    'category' => $category['slug'],
                    'category_name' => $category['name'],
                    'is_er' => $category['is_er'],
                    'available' => $category['available'],
                    'occupied' => $category['occupied'],
                    'reserved' => $category['reserved'],
                    'cleaning' => $category['cleaning'],
                    'out_of_service' => $category['out_of_service'],
                    'total' => $category['total'],
                    'last_updated_at' => $summary['last_updated_at'],
                    'freshness' => $summary['freshness'],
                ];
            }
        }

        return $rows;
    }

    public function captureSnapshot(Hospital $hospital): BedAvailabilitySnapshot
    {
        $categories = $this->categoryBreakdown($hospital->id);
        $counts = $this->statusCounts($hospital->id);

        return BedAvailabilitySnapshot::create([
            'hospital_id' => $hospital->id,
            'category_counts' => $categories,
            'total_beds' => $counts['total'],
            'available_beds' => $counts['available'],
            'captured_at' => now(),
        ]);
    }

    public function aggregateStatusCounts(array $hospitalIds): array
    {
        if ($hospitalIds === []) {
            return [
                'total' => 0, 'available' => 0, 'occupied' => 0, 'reserved' => 0,
                'cleaning' => 0, 'out_of_service' => 0, 'operational' => 0,
            ];
        }

        $rows = Bed::query()
            ->whereIn('hospital_id', $hospitalIds)
            ->where('active', true)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $rows->sum();
        $outOfService = (int) ($rows[Bed::STATUS_OUT_OF_SERVICE] ?? 0);

        return [
            'total' => $total,
            'available' => (int) ($rows[Bed::STATUS_AVAILABLE] ?? 0),
            'occupied' => (int) ($rows[Bed::STATUS_OCCUPIED] ?? 0),
            'reserved' => (int) ($rows[Bed::STATUS_RESERVED] ?? 0),
            'cleaning' => (int) ($rows[Bed::STATUS_CLEANING] ?? 0),
            'out_of_service' => $outOfService,
            'operational' => $total - $outOfService,
        ];
    }

    public function aggregateCategoryBreakdown(array $hospitalIds, ?string $categorySlug = null): array
    {
        if ($hospitalIds === []) {
            return [];
        }

        $rows = Bed::query()
            ->join('bed_categories', 'beds.bed_category_id', '=', 'bed_categories.id')
            ->whereIn('beds.hospital_id', $hospitalIds)
            ->where('beds.active', true)
            ->when($categorySlug, fn (Builder $q) => $q->where('bed_categories.slug', $categorySlug))
            ->selectRaw('bed_categories.id as category_id, bed_categories.name, bed_categories.slug, bed_categories.is_er, beds.status, count(*) as aggregate')
            ->groupBy('bed_categories.id', 'bed_categories.name', 'bed_categories.slug', 'bed_categories.is_er', 'beds.status')
            ->get();

        $categories = BedCategory::query()
            ->where('active', true)
            ->when($categorySlug, fn (Builder $q) => $q->where('slug', $categorySlug))
            ->orderBy('sort_order')
            ->get();

        return $categories->map(function (BedCategory $category) use ($rows) {
            $categoryRows = $rows->where('category_id', $category->id);
            $statusCounts = $categoryRows->pluck('aggregate', 'status');
            $total = (int) $categoryRows->sum('aggregate');
            $outOfService = (int) ($statusCounts[Bed::STATUS_OUT_OF_SERVICE] ?? 0);

            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_er' => $category->is_er,
                'total' => $total,
                'available' => (int) ($statusCounts[Bed::STATUS_AVAILABLE] ?? 0),
                'occupied' => (int) ($statusCounts[Bed::STATUS_OCCUPIED] ?? 0),
                'reserved' => (int) ($statusCounts[Bed::STATUS_RESERVED] ?? 0),
                'cleaning' => (int) ($statusCounts[Bed::STATUS_CLEANING] ?? 0),
                'out_of_service' => $outOfService,
                'operational' => $total - $outOfService,
            ];
        })->filter(fn (array $category) => $category['total'] > 0)->values()->all();
    }

    public function referralCounts(array $hospitalIds, User $user): array
    {
        if ($hospitalIds === []) {
            return ['pending' => 0, 'incoming' => 0, 'outgoing' => 0, 'completed_this_month' => 0];
        }

        $pendingStatuses = [
            Referral::STATUS_SUBMITTED, Referral::STATUS_UNDER_REVIEW, Referral::STATUS_MORE_INFO,
            Referral::STATUS_ACCEPTED, Referral::STATUS_TRANSFER_SCHEDULED, Referral::STATUS_IN_TRANSIT,
        ];

        $base = Referral::query()->whereIn('receiving_hospital_id', $hospitalIds);
        $outgoing = Referral::query()->whereIn('referring_hospital_id', $hospitalIds);

        return [
            'pending' => (clone $base)->whereIn('status', $pendingStatuses)->count()
                + (clone $outgoing)->whereIn('status', $pendingStatuses)->count(),
            'incoming' => (clone $base)->whereIn('status', $pendingStatuses)->count(),
            'outgoing' => (clone $outgoing)->whereIn('status', $pendingStatuses)->count(),
            'completed_this_month' => (clone $base)->where('status', Referral::STATUS_COMPLETED)
                ->where('closed_at', '>=', now()->startOfMonth())->count(),
        ];
    }
}
