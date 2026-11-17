<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityApiController;
use App\Http\Controllers\Api\BedApiController;
use App\Http\Controllers\Api\HospitalApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\ReferralApiController;
use App\Http\Controllers\Api\ReportApiController;
use App\Http\Controllers\Api\TransferApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.auth.login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1')->name('api.auth.forgot');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('api.auth.reset');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

        Route::get('/reference/municipalities', [HospitalApiController::class, 'municipalities'])->name('api.reference.municipalities');
        Route::get('/reference/services', [HospitalApiController::class, 'serviceOptions'])->name('api.reference.services');

        Route::get('/hospitals', [HospitalApiController::class, 'index'])->name('api.hospitals.index');
        Route::get('/hospitals/{hospital}', [HospitalApiController::class, 'show'])->name('api.hospitals.show');
        Route::post('/hospitals', [HospitalApiController::class, 'store'])->name('api.hospitals.store');
        Route::patch('/hospitals/{hospital}', [HospitalApiController::class, 'update'])->name('api.hospitals.update');
        Route::get('/hospitals/{hospital}/services', [HospitalApiController::class, 'services'])->name('api.hospitals.services');
        Route::get('/hospitals/{hospital}/availability', [HospitalApiController::class, 'availability'])->name('api.hospitals.availability');

        Route::get('/availability', [AvailabilityApiController::class, 'index'])->name('api.availability.index');
        Route::get('/availability/summary', [AvailabilityApiController::class, 'summary'])->name('api.availability.summary');
        Route::post('/availability/snapshots', [AvailabilityApiController::class, 'snapshots'])->name('api.availability.snapshots');

        Route::get('/beds', [BedApiController::class, 'index'])->name('api.beds.index');
        Route::post('/beds', [BedApiController::class, 'store'])->name('api.beds.store');
        Route::post('/beds/bulk-status', [BedApiController::class, 'bulkStatus'])->name('api.beds.bulk-status');
        Route::get('/beds/{bed}', [BedApiController::class, 'show'])->name('api.beds.show');
        Route::patch('/beds/{bed}/status', [BedApiController::class, 'updateStatus'])->name('api.beds.status');
        Route::patch('/beds/{bed}/activation', [BedApiController::class, 'toggle'])->name('api.beds.toggle');
        Route::get('/beds/{bed}/history', [BedApiController::class, 'history'])->name('api.beds.history');

        Route::get('/referrals', [ReferralApiController::class, 'index'])->name('api.referrals.index');
        Route::post('/referrals', [ReferralApiController::class, 'store'])->name('api.referrals.store');
        Route::get('/referrals/{referral}', [ReferralApiController::class, 'show'])->name('api.referrals.show');
        Route::post('/referrals/{referral}/submit', [ReferralApiController::class, 'submit'])->name('api.referrals.submit');
        Route::post('/referrals/{referral}/start-review', [ReferralApiController::class, 'startReview'])->name('api.referrals.start-review');
        Route::post('/referrals/{referral}/accept', [ReferralApiController::class, 'accept'])->name('api.referrals.accept');
        Route::post('/referrals/{referral}/decline', [ReferralApiController::class, 'decline'])->name('api.referrals.decline');
        Route::post('/referrals/{referral}/request-information', [ReferralApiController::class, 'requestInformation'])->name('api.referrals.request-information');
        Route::post('/referrals/{referral}/cancel', [ReferralApiController::class, 'cancel'])->name('api.referrals.cancel');
        Route::post('/referrals/{referral}/assign-coordinator', [ReferralApiController::class, 'assignCoordinator'])->name('api.referrals.assign-coordinator');
        Route::post('/referrals/{referral}/reserve-bed', [ReferralApiController::class, 'reserveBed'])->name('api.referrals.reserve-bed');
        Route::post('/referrals/{referral}/transfer', [ReferralApiController::class, 'scheduleTransfer'])->name('api.referrals.transfer');
        Route::post('/referrals/{referral}/departure', [ReferralApiController::class, 'departure'])->name('api.referrals.departure');
        Route::post('/referrals/{referral}/arrival', [ReferralApiController::class, 'arrival'])->name('api.referrals.arrival');
        Route::post('/referrals/{referral}/complete', [ReferralApiController::class, 'complete'])->name('api.referrals.complete');
        Route::post('/referrals/{referral}/documents', [ReferralApiController::class, 'uploadDocument'])->name('api.referrals.documents.store');

        Route::get('/transfers', [TransferApiController::class, 'index'])->name('api.transfers.index');
        Route::get('/transfers/{transfer}', [TransferApiController::class, 'show'])->name('api.transfers.show');
        Route::post('/transfers/{transfer}/departure', [TransferApiController::class, 'departure'])->name('api.transfers.departure');
        Route::post('/transfers/{transfer}/arrival', [TransferApiController::class, 'arrival'])->name('api.transfers.arrival');
        Route::post('/transfers/{transfer}/complete', [TransferApiController::class, 'complete'])->name('api.transfers.complete');

        Route::get('/reports/overview', [ReportApiController::class, 'overview'])->name('api.reports.overview');
        Route::get('/reports/availability', [ReportApiController::class, 'availability'])->name('api.reports.availability');
        Route::get('/reports/referrals', [ReportApiController::class, 'referrals'])->name('api.reports.referrals');
        Route::get('/reports/transfers', [ReportApiController::class, 'transfers'])->name('api.reports.transfers');

        Route::get('/notifications', [NotificationApiController::class, 'index'])->name('api.notifications.index');
        Route::post('/notifications/read-all', [NotificationApiController::class, 'markAllRead'])->name('api.notifications.read-all');
        Route::post('/notifications/{id}/read', [NotificationApiController::class, 'markRead'])->name('api.notifications.read');

        Route::prefix('admin')->name('api.admin.')->group(function () {
            Route::get('/hospitals', [AdminApiController::class, 'hospitals'])->name('hospitals.index');
            Route::get('/users', [AdminApiController::class, 'users'])->name('users.index');
            Route::patch('/users/{user}/status', [AdminApiController::class, 'updateUserStatus'])->name('users.status');
            Route::get('/audit', [AdminApiController::class, 'audit'])->name('audit.index');
            Route::get('/settings', [AdminApiController::class, 'settings'])->name('settings.show');
            Route::put('/settings', [AdminApiController::class, 'updateSettings'])->name('settings.update');
        });
    });
});
