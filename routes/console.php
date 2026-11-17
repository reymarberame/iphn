<?php

use App\Services\BedReservationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reservations:expire', function (BedReservationService $reservations) {
    $count = $reservations->expireOverdue();

    $this->info($count.' overdue bed reservation(s) expired.');
})->purpose('Release bed holds whose reservation window has elapsed');

Schedule::command('reservations:expire')->everyFiveMinutes()->withoutOverlapping();
