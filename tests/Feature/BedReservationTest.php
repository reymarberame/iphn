<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\BedReservation;
use App\Models\Referral;
use App\Services\BedReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsNetwork;
use Tests\TestCase;

class BedReservationTest extends TestCase
{
    use BuildsNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccess();
    }

    public function test_accepted_referral_can_hold_an_available_bed(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_ACCEPTED);
        $bed = $this->bed($receiving);

        $reservation = app(BedReservationService::class)->reserve($referral, $bed, $receiver);

        $this->assertSame(BedReservation::STATUS_ACTIVE, $reservation->status);
        $this->assertSame($receiver->id, $reservation->reserved_by);
        $this->assertSame(Bed::STATUS_RESERVED, $bed->fresh()->status);
        $this->assertNotNull($reservation->expires_at);
    }

    public function test_the_same_bed_cannot_be_double_booked(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);

        $first = $this->referral($referring, $receiving, $creator, Referral::STATUS_ACCEPTED);
        $second = $this->referral($referring, $receiving, $creator, Referral::STATUS_ACCEPTED);
        $bed = $this->bed($receiving);
        $service = app(BedReservationService::class);

        $service->reserve($first, $bed, $receiver);

        try {
            $service->reserve($second, $bed->fresh(), $receiver);
            $this->fail('A second active reservation on the same bed should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bed', $exception->errors());
        }

        $this->assertSame(1, BedReservation::query()
            ->where('bed_id', $bed->id)
            ->where('status', BedReservation::STATUS_ACTIVE)
            ->count());
    }

    public function test_out_of_service_bed_cannot_be_reserved(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_ACCEPTED);
        $bed = $this->bed($receiving, ['status' => Bed::STATUS_OUT_OF_SERVICE]);

        $this->expectException(ValidationException::class);

        app(BedReservationService::class)->reserve($referral, $bed, $receiver);
    }

    public function test_bed_from_another_hospital_cannot_be_reserved(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_ACCEPTED);
        $foreignBed = $this->bed($referring);

        $this->expectException(ValidationException::class);

        app(BedReservationService::class)->reserve($referral, $foreignBed, $receiver);
    }

    public function test_referral_must_be_accepted_before_a_bed_can_be_held(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_SUBMITTED);
        $bed = $this->bed($receiving);

        $this->expectException(ValidationException::class);

        app(BedReservationService::class)->reserve($referral, $bed, $receiver);
    }

    public function test_referring_hospital_staff_cannot_reserve_a_receiving_bed(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_ACCEPTED);
        $bed = $this->bed($receiving);

        try {
            app(BedReservationService::class)->reserve($referral, $bed, $creator);
            $this->fail('Only receiving-hospital staff may reserve a bed.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(0, BedReservation::query()->count());
        $this->assertSame(Bed::STATUS_AVAILABLE, $bed->fresh()->status);
    }

    public function test_releasing_a_hold_returns_the_bed_to_available(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_ACCEPTED);
        $bed = $this->bed($receiving);
        $service = app(BedReservationService::class);

        $reservation = $service->reserve($referral, $bed, $receiver);
        $released = $service->release($reservation, $receiver, BedReservation::STATUS_RELEASED, 'Bed no longer required');

        $this->assertSame(BedReservation::STATUS_RELEASED, $released->status);
        $this->assertSame(Bed::STATUS_AVAILABLE, $bed->fresh()->status);
    }

    public function test_unrelated_staff_cannot_release_a_hold(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $outsider = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);
        $stranger = $this->user('hospital_staff', $outsider);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_ACCEPTED);
        $bed = $this->bed($receiving);
        $service = app(BedReservationService::class);

        $reservation = $service->reserve($referral, $bed, $receiver);

        try {
            $service->release($reservation, $stranger);
            $this->fail('Staff outside the receiving hospital should not release the hold.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(BedReservation::STATUS_ACTIVE, $reservation->fresh()->status);
        $this->assertSame(Bed::STATUS_RESERVED, $bed->fresh()->status);
    }
}
