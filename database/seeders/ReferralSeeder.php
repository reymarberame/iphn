<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\BedReservation;
use App\Models\BedStatusHistory;
use App\Models\Hospital;
use App\Models\Referral;
use App\Models\ReferralStatusHistory;
use App\Models\Service;
use App\Models\TransferRecord;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReferralSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $hospitals = Hospital::query()->pluck('id', 'hospital_code');
        $users = User::query()->pluck('id', 'email');
        $services = Service::query()->pluck('id', 'name');

        $referrals = [];

        $referrals[] = $this->createReferral([
            'referral_number' => 'REF-2026-0001',
            'referring_hospital_id' => $hospitals['BPMC-1013'],
            'receiving_hospital_id' => $hospitals['VCMC-1015'],
            'patient_reference' => 'DEMO-2026-0001',
            'patient_name' => 'Maria Santos',
            'patient_age' => 34,
            'patient_sex' => 'female',
            'urgency' => 'urgent',
            'requested_service_id' => $services['Emergency Medicine'],
            'referral_reason' => 'Acute abdominal pain with guarding and fever. Needs surgical evaluation and possible emergency laparotomy beyond the referring hospital capacity.',
            'contact_name' => 'BPMC Referral Coordinator',
            'contact_phone' => '0917 100 0008',
            'created_by' => $users['referral@bhn.test'],
        ], [
            ['status' => Referral::STATUS_DRAFT, 'at' => $now->copy()->subHours(4), 'by' => $users['referral@bhn.test'], 'reason' => 'Referral created'],
            ['status' => Referral::STATUS_SUBMITTED, 'at' => $now->copy()->subHours(3)->subMinutes(50), 'by' => $users['referral@bhn.test'], 'reason' => 'Referral submitted to receiving hospital'],
        ]);

        $referrals[] = $this->createReferral([
            'referral_number' => 'REF-2026-0002',
            'referring_hospital_id' => $hospitals['BPMC-1013'],
            'receiving_hospital_id' => $hospitals['VCMC-1015'],
            'patient_reference' => 'DEMO-2026-0002',
            'patient_name' => 'Jose Dela Cruz',
            'patient_age' => 58,
            'patient_sex' => 'male',
            'urgency' => 'emergency',
            'requested_service_id' => $services['Cardiology'],
            'referral_reason' => 'ST-elevation myocardial infarction requiring primary percutaneous coronary intervention. Referring hospital has no catheterization laboratory.',
            'contact_name' => 'BPMC Referral Coordinator',
            'contact_phone' => '0917 100 0008',
            'created_by' => $users['referral@bhn.test'],
            'coordinator_id' => $users['admission@vcmc.bhn.test'],
            'response_note' => 'Accepted for urgent PCI. Proceed with transfer coordination and hold a monitored bed.',
        ], [
            ['status' => Referral::STATUS_DRAFT, 'at' => $now->copy()->subHours(8), 'by' => $users['referral@bhn.test'], 'reason' => 'Referral created'],
            ['status' => Referral::STATUS_SUBMITTED, 'at' => $now->copy()->subHours(7)->subMinutes(50), 'by' => $users['referral@bhn.test'], 'reason' => 'Referral submitted to receiving hospital'],
            ['status' => Referral::STATUS_UNDER_REVIEW, 'at' => $now->copy()->subHours(7)->subMinutes(30), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Receiving hospital started review'],
            ['status' => Referral::STATUS_ACCEPTED, 'at' => $now->copy()->subHours(7), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Accepted for urgent PCI'],
        ]);

        $referrals[] = $this->createReferral([
            'referral_number' => 'REF-2026-0003',
            'referring_hospital_id' => $hospitals['VCMC-1015'],
            'receiving_hospital_id' => $hospitals['BPMC-1013'],
            'patient_reference' => 'DEMO-2026-0003',
            'patient_name' => 'Liza Fernandez',
            'patient_age' => 27,
            'patient_sex' => 'female',
            'urgency' => 'routine',
            'requested_service_id' => $services['Internal Medicine'],
            'referral_reason' => 'Uncontrolled hypertension with persistent headache and visual disturbance. Requires admission for workup closer to the patient family support network.',
            'contact_name' => 'VCMC Referral Coordinator',
            'contact_phone' => '0917 100 0010',
            'created_by' => $users['admission@vcmc.bhn.test'],
        ], [
            ['status' => Referral::STATUS_DRAFT, 'at' => $now->copy()->subHours(2), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Referral created'],
            ['status' => Referral::STATUS_SUBMITTED, 'at' => $now->copy()->subHours(1)->subMinutes(50), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Referral submitted to receiving hospital'],
            ['status' => Referral::STATUS_UNDER_REVIEW, 'at' => $now->copy()->subMinutes(90), 'by' => $users['hospitaladmin@bhn.test'], 'reason' => 'Receiving hospital started review'],
        ]);

        $referrals[] = $this->createReferral([
            'referral_number' => 'REF-2026-0004',
            'referring_hospital_id' => $hospitals['MDH-1017'],
            'receiving_hospital_id' => $hospitals['BPMC-1013'],
            'patient_reference' => 'DEMO-2026-0004',
            'patient_name' => 'Ramon Villanueva',
            'patient_age' => 45,
            'patient_sex' => 'male',
            'urgency' => 'emergency',
            'requested_service_id' => $services['Trauma Care'],
            'referral_reason' => 'Multiple rib fractures and suspected hemothorax after a motorcycle collision. Requires thoracic surgical assessment and ICU monitoring.',
            'contact_name' => 'Maramag District Referral Coordinator',
            'contact_phone' => '0917 100 0013',
            'created_by' => $users['referral@mdh.bhn.test'],
            'coordinator_id' => $users['hospitaladmin@bhn.test'],
            'response_note' => 'Accepted for trauma care. ICU bed held pending arrival.',
        ], [
            ['status' => Referral::STATUS_DRAFT, 'at' => $now->copy()->subHours(26), 'by' => $users['referral@mdh.bhn.test'], 'reason' => 'Referral created'],
            ['status' => Referral::STATUS_SUBMITTED, 'at' => $now->copy()->subHours(25), 'by' => $users['referral@mdh.bhn.test'], 'reason' => 'Referral submitted to receiving hospital'],
            ['status' => Referral::STATUS_UNDER_REVIEW, 'at' => $now->copy()->subHours(24), 'by' => $users['hospitaladmin@bhn.test'], 'reason' => 'Receiving hospital started review'],
            ['status' => Referral::STATUS_ACCEPTED, 'at' => $now->copy()->subHours(23), 'by' => $users['hospitaladmin@bhn.test'], 'reason' => 'Accepted for trauma care'],
            ['status' => Referral::STATUS_TRANSFER_SCHEDULED, 'at' => $now->copy()->subHours(22), 'by' => $users['referral@mdh.bhn.test'], 'reason' => 'Transfer scheduled'],
        ]);

        $referrals[] = $this->createReferral([
            'referral_number' => 'REF-2026-0005',
            'referring_hospital_id' => $hospitals['QMH-1020'],
            'receiving_hospital_id' => $hospitals['VCMC-1015'],
            'patient_reference' => 'DEMO-2026-0005',
            'patient_name' => 'Grace Lim',
            'patient_age' => 62,
            'patient_sex' => 'female',
            'urgency' => 'urgent',
            'requested_service_id' => $services['Cardiology'],
            'referral_reason' => 'Recurrent ventricular tachycardia requiring electrophysiology review and continuous cardiac monitoring.',
            'contact_name' => 'Quezon Municipal Referral Coordinator',
            'contact_phone' => '0917 100 0014',
            'created_by' => $users['referral@qmh.bhn.test'],
            'coordinator_id' => $users['admission@vcmc.bhn.test'],
            'response_note' => 'Accepted for cardiac monitoring and electrophysiology evaluation.',
        ], [
            ['status' => Referral::STATUS_DRAFT, 'at' => $now->copy()->subHours(50), 'by' => $users['referral@qmh.bhn.test'], 'reason' => 'Referral created'],
            ['status' => Referral::STATUS_SUBMITTED, 'at' => $now->copy()->subHours(49), 'by' => $users['referral@qmh.bhn.test'], 'reason' => 'Referral submitted to receiving hospital'],
            ['status' => Referral::STATUS_UNDER_REVIEW, 'at' => $now->copy()->subHours(48), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Receiving hospital started review'],
            ['status' => Referral::STATUS_ACCEPTED, 'at' => $now->copy()->subHours(47), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Accepted for cardiac monitoring'],
            ['status' => Referral::STATUS_TRANSFER_SCHEDULED, 'at' => $now->copy()->subHours(46), 'by' => $users['referral@qmh.bhn.test'], 'reason' => 'Transfer scheduled'],
            ['status' => Referral::STATUS_IN_TRANSIT, 'at' => $now->copy()->subHours(4), 'by' => $users['referral@qmh.bhn.test'], 'reason' => 'Patient departed referring hospital'],
        ]);

        $referrals[] = $this->createReferral([
            'referral_number' => 'REF-2026-0006',
            'referring_hospital_id' => $hospitals['VCMC-1015'],
            'receiving_hospital_id' => $hospitals['BDH-1014'],
            'patient_reference' => 'DEMO-2026-0006',
            'patient_name' => 'Andres Bautista',
            'patient_age' => 70,
            'patient_sex' => 'male',
            'urgency' => 'urgent',
            'requested_service_id' => $services['Cardiology'],
            'referral_reason' => 'Post-operative arrhythmia needing continuous telemetry and cardiology review.',
            'contact_name' => 'VCMC Referral Coordinator',
            'contact_phone' => '0917 100 0010',
            'created_by' => $users['admission@vcmc.bhn.test'],
            'decline_reason' => 'No monitored bed available at the requested time. Please refer to another facility.',
        ], [
            ['status' => Referral::STATUS_DRAFT, 'at' => $now->copy()->subHours(36), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Referral created'],
            ['status' => Referral::STATUS_SUBMITTED, 'at' => $now->copy()->subHours(35), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Referral submitted to receiving hospital'],
            ['status' => Referral::STATUS_UNDER_REVIEW, 'at' => $now->copy()->subHours(34), 'by' => $users['admin@bdh.bhn.test'], 'reason' => 'Receiving hospital started review'],
            ['status' => Referral::STATUS_DECLINED, 'at' => $now->copy()->subHours(33), 'by' => $users['admin@bdh.bhn.test'], 'reason' => 'No monitored bed available at the requested time'],
        ]);

        $referrals[] = $this->createReferral([
            'referral_number' => 'REF-2026-0007',
            'referring_hospital_id' => $hospitals['BPMC-1013'],
            'receiving_hospital_id' => $hospitals['BDH-1014'],
            'patient_reference' => 'DEMO-2026-0007',
            'patient_name' => 'Cristina Reyes',
            'patient_age' => 51,
            'patient_sex' => 'female',
            'urgency' => 'routine',
            'requested_service_id' => $services['Oncology'],
            'referral_reason' => 'Newly diagnosed breast mass requiring oncology assessment and treatment planning.',
            'contact_name' => 'BPMC Referral Coordinator',
            'contact_phone' => '0917 100 0008',
            'created_by' => $users['referral@bhn.test'],
            'response_note' => 'Please attach the latest histopathology report and recent complete blood count.',
        ], [
            ['status' => Referral::STATUS_DRAFT, 'at' => $now->copy()->subHours(12), 'by' => $users['referral@bhn.test'], 'reason' => 'Referral created'],
            ['status' => Referral::STATUS_SUBMITTED, 'at' => $now->copy()->subHours(11), 'by' => $users['referral@bhn.test'], 'reason' => 'Referral submitted to receiving hospital'],
            ['status' => Referral::STATUS_MORE_INFO, 'at' => $now->copy()->subHours(9), 'by' => $users['admin@bdh.bhn.test'], 'reason' => 'Please attach the latest histopathology report and recent complete blood count.'],
        ]);

        $referrals[] = $this->createReferral([
            'referral_number' => 'REF-2026-0008',
            'referring_hospital_id' => $hospitals['DCMH-1018'],
            'receiving_hospital_id' => $hospitals['VCMC-1015'],
            'patient_reference' => 'DEMO-2026-0008',
            'patient_name' => 'Noel Garcia',
            'patient_age' => 8,
            'patient_sex' => 'male',
            'urgency' => 'emergency',
            'requested_service_id' => $services['Pediatrics'],
            'referral_reason' => 'Severe dengue with warning signs and increasing hematocrit. Requires pediatric intensive monitoring and fluid management.',
            'contact_name' => 'Don Carlos Municipal Hospital Admin',
            'contact_phone' => '0917 100 0015',
            'created_by' => $users['admin@dcmh.bhn.test'],
            'coordinator_id' => $users['admission@vcmc.bhn.test'],
            'response_note' => 'Accepted for pediatric admission. Bed held and patient received.',
        ], [
            ['status' => Referral::STATUS_DRAFT, 'at' => $now->copy()->subHours(72), 'by' => $users['admin@dcmh.bhn.test'], 'reason' => 'Referral created'],
            ['status' => Referral::STATUS_SUBMITTED, 'at' => $now->copy()->subHours(71), 'by' => $users['admin@dcmh.bhn.test'], 'reason' => 'Referral submitted to receiving hospital'],
            ['status' => Referral::STATUS_UNDER_REVIEW, 'at' => $now->copy()->subHours(70), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Receiving hospital started review'],
            ['status' => Referral::STATUS_ACCEPTED, 'at' => $now->copy()->subHours(68), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Accepted for pediatric admission'],
            ['status' => Referral::STATUS_TRANSFER_SCHEDULED, 'at' => $now->copy()->subHours(66), 'by' => $users['admin@dcmh.bhn.test'], 'reason' => 'Transfer scheduled'],
            ['status' => Referral::STATUS_IN_TRANSIT, 'at' => $now->copy()->subHours(20), 'by' => $users['admin@dcmh.bhn.test'], 'reason' => 'Patient departed referring hospital'],
            ['status' => Referral::STATUS_ARRIVED, 'at' => $now->copy()->subHours(18), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Receiving hospital confirmed arrival'],
        ]);

        $referrals[] = $this->createReferral([
            'referral_number' => 'REF-2026-0009',
            'referring_hospital_id' => $hospitals['BPMC-1013'],
            'receiving_hospital_id' => $hospitals['VCMC-1015'],
            'patient_reference' => 'DEMO-2026-0009',
            'patient_name' => 'Teresa Mendoza',
            'patient_age' => 66,
            'patient_sex' => 'female',
            'urgency' => 'urgent',
            'requested_service_id' => $services['Neurology'],
            'referral_reason' => 'Acute ischemic stroke outside the thrombolysis window with progressive weakness. Requires neurology admission and rehabilitation planning.',
            'contact_name' => 'BPMC Referral Coordinator',
            'contact_phone' => '0917 100 0008',
            'created_by' => $users['referral@bhn.test'],
            'coordinator_id' => $users['admission@vcmc.bhn.test'],
            'response_note' => 'Accepted and completed. Patient transferred to the neurology ward.',
        ], [
            ['status' => Referral::STATUS_DRAFT, 'at' => $now->copy()->subDays(6), 'by' => $users['referral@bhn.test'], 'reason' => 'Referral created'],
            ['status' => Referral::STATUS_SUBMITTED, 'at' => $now->copy()->subDays(6)->addHour(), 'by' => $users['referral@bhn.test'], 'reason' => 'Referral submitted to receiving hospital'],
            ['status' => Referral::STATUS_UNDER_REVIEW, 'at' => $now->copy()->subDays(5)->subHours(20), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Receiving hospital started review'],
            ['status' => Referral::STATUS_ACCEPTED, 'at' => $now->copy()->subDays(5), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Accepted for neurology admission'],
            ['status' => Referral::STATUS_TRANSFER_SCHEDULED, 'at' => $now->copy()->subDays(5)->addHour(), 'by' => $users['referral@bhn.test'], 'reason' => 'Transfer scheduled'],
            ['status' => Referral::STATUS_IN_TRANSIT, 'at' => $now->copy()->subDays(4), 'by' => $users['referral@bhn.test'], 'reason' => 'Patient departed referring hospital'],
            ['status' => Referral::STATUS_ARRIVED, 'at' => $now->copy()->subDays(4)->addHours(2), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Receiving hospital confirmed arrival'],
            ['status' => Referral::STATUS_COMPLETED, 'at' => $now->copy()->subDays(4)->addHours(3), 'by' => $users['admission@vcmc.bhn.test'], 'reason' => 'Transfer completed'],
        ]);

        $referrals[] = $this->createReferral([
            'referral_number' => 'REF-2026-0010',
            'referring_hospital_id' => $hospitals['BPMC-1013'],
            'receiving_hospital_id' => $hospitals['VCMC-1015'],
            'patient_reference' => 'DEMO-2026-0010',
            'patient_name' => 'Paolo Aquino',
            'patient_age' => 39,
            'patient_sex' => 'male',
            'urgency' => 'routine',
            'requested_service_id' => $services['General Surgery'],
            'referral_reason' => 'Recurrent inguinal hernia requiring elective surgical repair. Draft being prepared while records are completed.',
            'contact_name' => 'BPMC Referral Coordinator',
            'contact_phone' => '0917 100 0008',
            'created_by' => $users['referral@bhn.test'],
        ], [
            ['status' => Referral::STATUS_DRAFT, 'at' => $now->copy()->subHours(1), 'by' => $users['referral@bhn.test'], 'reason' => 'Referral created'],
        ]);

        $this->attachReservation($referrals[1], $hospitals['VCMC-1015'], $users['admission@vcmc.bhn.test'], $now->copy()->subMinutes(30), $now->copy()->addMinutes(90));

        $this->attachReservation($referrals[3], $hospitals['BPMC-1013'], $users['hospitaladmin@bhn.test'], $now->copy()->subHours(21), $now->copy()->addMinutes(60));

        $this->attachReservation($referrals[4], $hospitals['VCMC-1015'], $users['admission@vcmc.bhn.test'], $now->copy()->subHours(46), $now->copy()->addHours(3));

        $this->attachReservation($referrals[7], $hospitals['VCMC-1015'], $users['admission@vcmc.bhn.test'], $now->copy()->subHours(66), $now->copy()->subHours(20), true, $now->copy()->subHours(18));

        $this->attachReservation($referrals[8], $hospitals['VCMC-1015'], $users['admission@vcmc.bhn.test'], $now->copy()->subDays(5)->addHour(), $now->copy()->subDays(4), true, $now->copy()->subDays(4)->addHours(2));

        $this->attachTransfer($referrals[3], [
            'status' => TransferRecord::STATUS_SCHEDULED,
            'transport_type' => 'ambulance',
            'vehicle_reference' => 'MDH-AMB-02',
            'escort_contact' => '0917 200 0004',
            'departure_at' => $now->copy()->addHours(2),
            'estimated_arrival_at' => $now->copy()->addHours(4),
            'coordinator_id' => $users['hospitaladmin@bhn.test'],
            'created_by' => $users['referral@mdh.bhn.test'],
            'notes' => 'Trauma team notified. ICU bed held.',
        ]);

        $this->attachTransfer($referrals[4], [
            'status' => TransferRecord::STATUS_IN_TRANSIT,
            'transport_type' => 'ambulance',
            'vehicle_reference' => 'QMH-AMB-01',
            'escort_contact' => '0917 200 0005',
            'departure_at' => $now->copy()->subHours(4),
            'estimated_arrival_at' => $now->copy()->addHour(),
            'coordinator_id' => $users['admission@vcmc.bhn.test'],
            'created_by' => $users['referral@qmh.bhn.test'],
            'notes' => 'Continuous cardiac monitoring en route.',
        ]);

        $this->attachTransfer($referrals[7], [
            'status' => TransferRecord::STATUS_ARRIVED,
            'transport_type' => 'ambulance',
            'vehicle_reference' => 'DCMH-AMB-03',
            'escort_contact' => '0917 200 0008',
            'departure_at' => $now->copy()->subHours(20),
            'estimated_arrival_at' => $now->copy()->subHours(18),
            'arrival_at' => $now->copy()->subHours(18),
            'coordinator_id' => $users['admission@vcmc.bhn.test'],
            'created_by' => $users['admin@dcmh.bhn.test'],
            'notes' => 'Patient received by pediatric team.',
        ]);

        $this->attachTransfer($referrals[8], [
            'status' => TransferRecord::STATUS_COMPLETED,
            'transport_type' => 'ambulance',
            'vehicle_reference' => 'BPMC-AMB-01',
            'escort_contact' => '0917 200 0009',
            'departure_at' => $now->copy()->subDays(4),
            'estimated_arrival_at' => $now->copy()->subDays(4)->addHours(2),
            'arrival_at' => $now->copy()->subDays(4)->addHours(2),
            'coordinator_id' => $users['admission@vcmc.bhn.test'],
            'created_by' => $users['referral@bhn.test'],
            'notes' => 'Completed without incident.',
        ]);

        $notifications = app(NotificationService::class);
        $notifications->receivingHospitalEvent($referrals[0], 'New emergency referral', 'Bukidnon Provincial Medical Center submitted '.$referrals[0]->referral_number.' for emergency review.');
        $notifications->bedReserved($referrals[1], $referrals[1]->activeReservation()->first()?->bed, null);
        $staleHospital = Hospital::query()->where('hospital_code', 'MDH-1017')->first();
        if ($staleHospital) {
            $notifications->staleAvailability($staleHospital, 'more than 90 minutes ago');
        }
    }

    private function createReferral(array $attributes, array $timeline): Referral
    {
        $statuses = array_column($timeline, 'status');
        $submitted = collect($timeline)->firstWhere('status', Referral::STATUS_SUBMITTED);
        $accepted = collect($timeline)->firstWhere('status', Referral::STATUS_ACCEPTED);
        $closed = collect($timeline)->first(fn (array $entry) => in_array($entry['status'], [
            Referral::STATUS_DECLINED, Referral::STATUS_COMPLETED, Referral::STATUS_CANCELLED, Referral::STATUS_EXPIRED,
        ], true));

        $referral = Referral::query()->firstOrCreate(
            ['referral_number' => $attributes['referral_number']],
            [
                ...$attributes,
                'status' => end($statuses),
                'submitted_at' => $submitted['at'] ?? null,
                'accepted_at' => $accepted['at'] ?? null,
                'closed_at' => $closed['at'] ?? null,
            ]
        );

        if (! $referral->wasRecentlyCreated) {
            return $referral;
        }

        $referral->forceFill([
            'created_at' => $timeline[0]['at'],
            'updated_at' => end($timeline)['at'],
        ])->saveQuietly();

        $previous = null;

        foreach ($timeline as $entry) {
            ReferralStatusHistory::query()->create([
                'referral_id' => $referral->id,
                'old_status' => $previous,
                'new_status' => $entry['status'],
                'reason' => $entry['reason'],
                'changed_by' => $entry['by'],
                'changed_at' => $entry['at'],
            ]);

            $previous = $entry['status'];
        }

        return $referral->fresh(['referringHospital', 'receivingHospital', 'requestedService']);
    }

    private function attachReservation(
        Referral $referral,
        int $hospitalId,
        int $userId,
        Carbon $reservedAt,
        Carbon $expiresAt,
        bool $consumed = false,
        ?Carbon $consumedAt = null,
    ): BedReservation {
        $bed = Bed::query()
            ->where('hospital_id', $hospitalId)
            ->where('active', true)
            ->where('status', Bed::STATUS_AVAILABLE)
            ->orderBy('id')
            ->firstOrFail();

        $bed->forceFill([
            'status' => $consumed ? Bed::STATUS_OCCUPIED : Bed::STATUS_RESERVED,
            'updated_by' => $userId,
            'updated_at' => $consumed ? ($consumedAt ?? $reservedAt) : $reservedAt,
        ])->save();

        BedStatusHistory::query()->create([
            'bed_id' => $bed->id,
            'hospital_id' => $bed->hospital_id,
            'old_status' => Bed::STATUS_AVAILABLE,
            'new_status' => Bed::STATUS_RESERVED,
            'reason' => 'Reserved for referral '.$referral->referral_number,
            'changed_by' => $userId,
            'changed_at' => $reservedAt,
        ]);

        if ($consumed) {
            BedStatusHistory::query()->create([
                'bed_id' => $bed->id,
                'hospital_id' => $bed->hospital_id,
                'old_status' => Bed::STATUS_RESERVED,
                'new_status' => Bed::STATUS_OCCUPIED,
                'reason' => 'Patient arrival confirmed for referral '.$referral->referral_number,
                'changed_by' => $userId,
                'changed_at' => $consumedAt ?? $reservedAt,
            ]);
        }

        return BedReservation::query()->create([
            'referral_id' => $referral->id,
            'bed_id' => $bed->id,
            'hospital_id' => $bed->hospital_id,
            'status' => $consumed ? BedReservation::STATUS_CONSUMED : BedReservation::STATUS_ACTIVE,
            'reserved_by' => $userId,
            'reserved_at' => $reservedAt,
            'expires_at' => $expiresAt,
            'notes' => $consumed ? 'Patient arrival confirmed' : 'Held for referral '.$referral->referral_number,
        ]);
    }

    private function attachTransfer(Referral $referral, array $attributes): TransferRecord
    {
        return TransferRecord::query()->firstOrCreate(
            ['referral_id' => $referral->id],
            $attributes
        );
    }
}
