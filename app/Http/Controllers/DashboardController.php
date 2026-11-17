<?php

namespace App\Http\Controllers;

use App\Models\BedStatusHistory;
use App\Models\Hospital;
use App\Models\Referral;
use App\Models\TransferRecord;
use App\Services\BedAvailabilityService;
use App\Services\HospitalAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly BedAvailabilityService $availability,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if ($this->access->isNetworkUser($user)) {
            return $this->networkDashboard($request, $user);
        }

        return $this->hospitalDashboard($request, $user);
    }

    private function networkDashboard(Request $request, $user): Response
    {
        $filters = $request->only(['region_id', 'province_id', 'ownership_type', 'category', 'search', 'service_id']);
        $summary = $this->availability->networkSummary($user, $filters);
        $hospitalIds = collect($summary['hospitals'])->pluck('hospital_id')->all();

        $recentReferrals = Referral::query()
            ->where(fn ($query) => $query->whereIn('receiving_hospital_id', $hospitalIds)
                ->orWhereIn('referring_hospital_id', $hospitalIds))
            ->with(['referringHospital:id,name,short_name', 'receivingHospital:id,name,short_name'])
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn (Referral $referral) => [
                'id' => $referral->id,
                'referral_number' => $referral->referral_number,
                'referring_hospital' => $referral->referringHospital?->short_name ?? $referral->referringHospital?->name,
                'receiving_hospital' => $referral->receivingHospital?->short_name ?? $referral->receivingHospital?->name,
                'status' => $referral->status,
                'status_label' => $referral->statusLabel(),
                'urgency' => $referral->urgency,
                'urgency_label' => $referral->urgencyLabel(),
                'created_at' => $referral->created_at?->toIso8601String(),
            ]);

        $recentUpdates = BedStatusHistory::query()
            ->whereIn('hospital_id', $hospitalIds)
            ->with(['bed:id,bed_code', 'hospital:id,name,short_name', 'changer:id,name'])
            ->latest('changed_at')
            ->limit(6)
            ->get()
            ->map(fn (BedStatusHistory $history) => [
                'id' => $history->id,
                'hospital' => $history->hospital?->short_name ?? $history->hospital?->name,
                'bed_code' => $history->bed?->bed_code,
                'new_status' => $history->new_status,
                'changed_by' => $history->changer?->name ?? 'System',
                'changed_at' => $history->changed_at?->toIso8601String(),
            ]);

        return Inertia::render('Dashboard', [
            'mode' => 'network',
            'summary' => $summary,
            'filters' => $filters,
            'recentReferrals' => $recentReferrals,
            'recentUpdates' => $recentUpdates,
            'demoNotice' => config('iphn.disclaimer'),
        ]);
    }

    private function hospitalDashboard(Request $request, $user): Response
    {
        $hospitals = $this->access->managementHospitals($user);

        if ($hospitals->isEmpty()) {
            return Inertia::render('Dashboard', [
                'mode' => 'unassigned',
                'message' => 'Your account is active but has no hospital assignment. Ask a network administrator to assign your role.',
                'hospitals' => [],
            ]);
        }

        $hospitalId = (int) $request->query('hospital', $hospitals->first()->id);

        if (! $hospitals->contains('id', $hospitalId)) {
            abort(403, 'You do not have access to that hospital.');
        }

        /** @var Hospital $hospital */
        $hospital = $hospitals->firstWhere('id', $hospitalId);
        $summary = $this->availability->hospitalDashboard($hospital);

        $recentUpdates = BedStatusHistory::query()
            ->where('hospital_id', $hospitalId)
            ->with(['bed:id,bed_code,hospital_id', 'changer:id,name'])
            ->latest('changed_at')
            ->limit(8)
            ->get()
            ->map(fn (BedStatusHistory $history) => [
                'id' => $history->id,
                'bed_id' => $history->bed_id,
                'bed_code' => $history->bed?->bed_code,
                'old_status' => $history->old_status,
                'new_status' => $history->new_status,
                'reason' => $history->reason,
                'changed_by' => $history->changer?->name ?? 'System',
                'changed_at' => $history->changed_at?->toIso8601String(),
            ]);

        $baseReferral = $request->user()->can('viewAny', Referral::class);

        $incoming = $baseReferral ? Referral::query()
            ->where('receiving_hospital_id', $hospitalId)
            ->whereNotIn('status', [Referral::STATUS_COMPLETED, Referral::STATUS_DECLINED, Referral::STATUS_CANCELLED, Referral::STATUS_EXPIRED])
            ->with(['referringHospital:id,name,short_name', 'requestedService:id,name'])
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn (Referral $referral) => $this->referralSummary($referral)) : collect();

        $outgoing = $baseReferral ? Referral::query()
            ->where('referring_hospital_id', $hospitalId)
            ->whereNotIn('status', [Referral::STATUS_COMPLETED, Referral::STATUS_DECLINED, Referral::STATUS_CANCELLED, Referral::STATUS_EXPIRED])
            ->with(['receivingHospital:id,name,short_name', 'requestedService:id,name'])
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn (Referral $referral) => $this->referralSummary($referral)) : collect();

        $transfers = $baseReferral ? TransferRecord::query()
            ->whereHas('referral', fn ($query) => $query->where('referring_hospital_id', $hospitalId)
                ->orWhere('receiving_hospital_id', $hospitalId))
            ->with(['referral.referringHospital:id,name,short_name', 'referral.receivingHospital:id,name,short_name'])
            ->whereIn('status', [TransferRecord::STATUS_SCHEDULED, TransferRecord::STATUS_READY, TransferRecord::STATUS_IN_TRANSIT])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (TransferRecord $transfer) => [
                'id' => $transfer->id,
                'status' => $transfer->status,
                'status_label' => $transfer->statusLabel(),
                'referral_id' => $transfer->referral_id,
                'referral_number' => $transfer->referral?->referral_number,
                'from' => $transfer->referral?->referringHospital?->short_name,
                'to' => $transfer->referral?->receivingHospital?->short_name,
                'departure_at' => $transfer->departure_at?->toIso8601String(),
                'estimated_arrival_at' => $transfer->estimated_arrival_at?->toIso8601String(),
            ]) : collect();

        return Inertia::render('Dashboard', [
            'mode' => 'hospital',
            'hospital' => [
                'id' => $hospital->id,
                'name' => $hospital->name,
                'short_name' => $hospital->short_name,
                'hospital_code' => $hospital->hospital_code,
                'ownership_type' => $hospital->ownership_type,
                'status' => $hospital->status,
                'location' => $hospital->location_label,
                'province' => $hospital->province?->name,
                'region' => $hospital->region?->short_name,
            ],
            'hospitals' => $hospitals->map(fn (Hospital $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'short_name' => $item->short_name,
                'location' => $item->location_label,
            ])->values(),
            'summary' => $summary,
            'recentUpdates' => $recentUpdates,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'transfers' => $transfers,
            'canViewReferrals' => $baseReferral,
            'canUpdateBeds' => $this->access->hasHospitalPermission($user, $hospitalId, 'beds.update'),
            'demoNotice' => config('iphn.disclaimer'),
        ]);
    }

    private function referralSummary(Referral $referral): array
    {
        return [
            'id' => $referral->id,
            'referral_number' => $referral->referral_number,
            'patient_reference' => $referral->patient_reference,
            'urgency' => $referral->urgency,
            'urgency_label' => $referral->urgencyLabel(),
            'status' => $referral->status,
            'status_label' => $referral->statusLabel(),
            'hospital' => $referral->receivingHospital?->short_name ?? $referral->referringHospital?->short_name,
            'requested_service' => $referral->requestedService?->name,
            'submitted_at' => $referral->submitted_at?->toIso8601String(),
            'created_at' => $referral->created_at?->toIso8601String(),
        ];
    }
}
