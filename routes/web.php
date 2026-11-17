<?php

use App\Http\Controllers\AdminAuditController;
use App\Http\Controllers\AdminHospitalController;
use App\Http\Controllers\AdminSettingController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BedBoardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/hospitals', [HospitalController::class, 'index'])->name('hospitals.index');
    Route::get('/hospitals/{hospital}', [HospitalController::class, 'show'])->name('hospitals.show');

    Route::get('/beds', [BedBoardController::class, 'index'])->name('beds.index');
    Route::post('/beds', [BedBoardController::class, 'storeBed'])->name('beds.store');
    Route::post('/beds/status', [BedBoardController::class, 'updateStatus'])->name('beds.status');
    Route::post('/beds/bulk-status', [BedBoardController::class, 'bulkStatus'])->name('beds.bulk-status');
    Route::post('/beds/wards', [BedBoardController::class, 'storeWard'])->name('beds.wards.store');
    Route::post('/beds/rooms', [BedBoardController::class, 'storeRoom'])->name('beds.rooms.store');
    Route::get('/beds/{bed}/history', [BedBoardController::class, 'history'])->name('beds.history');
    Route::patch('/beds/{bed}/activation', [BedBoardController::class, 'toggleBed'])->name('beds.toggle');

    Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
    Route::get('/referrals/create', [ReferralController::class, 'create'])->name('referrals.create');
    Route::post('/referrals', [ReferralController::class, 'store'])->name('referrals.store');
    Route::get('/referrals/{referral}', [ReferralController::class, 'show'])->name('referrals.show');
    Route::post('/referrals/{referral}/submit', [ReferralController::class, 'submit'])->name('referrals.submit');
    Route::post('/referrals/{referral}/start-review', [ReferralController::class, 'startReview'])->name('referrals.start-review');
    Route::post('/referrals/{referral}/accept', [ReferralController::class, 'accept'])->name('referrals.accept');
    Route::post('/referrals/{referral}/decline', [ReferralController::class, 'decline'])->name('referrals.decline');
    Route::post('/referrals/{referral}/request-information', [ReferralController::class, 'requestInformation'])->name('referrals.request-information');
    Route::post('/referrals/{referral}/cancel', [ReferralController::class, 'cancel'])->name('referrals.cancel');
    Route::post('/referrals/{referral}/assign-coordinator', [ReferralController::class, 'assignCoordinator'])->name('referrals.assign-coordinator');
    Route::post('/referrals/{referral}/reserve-bed', [ReferralController::class, 'reserveBed'])->name('referrals.reserve-bed');
    Route::post('/referrals/{referral}/transfer', [ReferralController::class, 'scheduleTransfer'])->name('referrals.transfer');
    Route::post('/referrals/{referral}/departure', [ReferralController::class, 'departure'])->name('referrals.departure');
    Route::post('/referrals/{referral}/arrival', [ReferralController::class, 'arrival'])->name('referrals.arrival');
    Route::post('/referrals/{referral}/complete', [ReferralController::class, 'completeTransfer'])->name('referrals.complete');
    Route::post('/referrals/{referral}/documents', [ReferralController::class, 'uploadDocument'])->name('referrals.documents.store');
    Route::get('/referrals/{referral}/documents/{document}', [ReferralController::class, 'downloadDocument'])->name('referrals.documents.download');

    Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{type}', [ReportController::class, 'export'])->name('reports.export');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/hospitals', [AdminHospitalController::class, 'index'])->name('hospitals.index');
        Route::post('/hospitals', [AdminHospitalController::class, 'store'])->name('hospitals.store');
        Route::patch('/hospitals/{hospital}', [AdminHospitalController::class, 'update'])->name('hospitals.update');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');

        Route::get('/audit', [AdminAuditController::class, 'index'])->name('audit.index');

        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
    });
});
