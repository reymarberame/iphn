<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Services\HospitalAccessService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly ReportService $reports,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if (! $this->access->hasPermission($user, 'reports.view') && ! $this->access->hasPermission($user, 'reports.national')) {
            abort(403, 'You do not have permission to view reports.');
        }

        $filters = $request->only(['date_from', 'date_to', 'hospital_id']);
        $overview = $this->reports->overview($user, $filters);

        $hospitals = Hospital::query()
            ->whereIn('id', $this->access->managementHospitalIds($user))
            ->orderBy('name')
            ->get(['id', 'name', 'short_name']);

        return Inertia::render('Reports/Index', [
            'overview' => $overview,
            'filters' => $filters,
            'hospitals' => $hospitals,
            'canExportReferrals' => ! $this->access->isNetworkUser($user),
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if (! $this->access->hasPermission($user, 'reports.view') && ! $this->access->hasPermission($user, 'reports.national')) {
            abort(403);
        }

        $from = $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : now()->subDays(29)->startOfDay();
        $to = $request->filled('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : now()->endOfDay();

        return match ($type) {
            'availability' => $this->csv('bhn-bed-availability-'.now()->format('Ymd').'.csv', $this->reports->availabilityRows($user), [
                'hospital', 'code', 'province', 'category', 'total', 'available', 'occupied', 'reserved', 'cleaning', 'out_of_service', 'last_updated_at', 'freshness',
            ]),
            'referrals' => $this->exportReferrals($request, $from, $to),
            'transfers' => $this->exportTransfers($request, $from, $to),
            default => abort(404),
        };
    }

    private function exportReferrals(Request $request, Carbon $from, Carbon $to): StreamedResponse
    {
        if ($this->access->isNetworkUser($request->user())) {
            abort(403, 'Patient-linked referral rows are restricted to the referring and receiving hospitals.');
        }

        return $this->csv('bhn-referrals-'.now()->format('Ymd').'.csv', $this->reports->referralRows($request->user(), $from, $to), [
            'referral_number', 'referring_hospital', 'receiving_hospital', 'patient_reference', 'urgency', 'status',
            'submitted_at', 'accepted_at', 'closed_at', 'created_at',
        ]);
    }

    private function exportTransfers(Request $request, Carbon $from, Carbon $to): StreamedResponse
    {
        if ($this->access->isNetworkUser($request->user())) {
            abort(403, 'Patient-linked transfer rows are restricted to the referring and receiving hospitals.');
        }

        return $this->csv('bhn-transfers-'.now()->format('Ymd').'.csv', $this->reports->transferRows($request->user(), $from, $to), [
            'referral_number', 'referring_hospital', 'receiving_hospital', 'status', 'transport',
            'departure_at', 'estimated_arrival_at', 'arrival_at', 'duration_minutes',
        ]);
    }

    private function csv(string $filename, iterable $rows, array $headers): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $headers) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);

            foreach ($rows as $row) {
                $data = is_array($row) ? $row : (array) $row;
                fputcsv($output, array_map(fn ($key) => $data[$key] ?? '', $headers));
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
