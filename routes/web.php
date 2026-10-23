<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\PlatformAdminAccessRequestController;
use App\Http\Controllers\PlatformAdminDashboardController;
use App\Http\Controllers\PlatformAdminMfaController;
use App\Http\Controllers\PlatformOrganizationController;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => view('auth.login'))->middleware('guest')->name('login');
Route::post('/login', [SessionController::class, 'store'])->middleware(['guest', 'throttle:5,1'])->name('login.store');
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/', function () {
    if (auth()->user()->platformAdminAccount()->exists()) {
        return redirect()->route('platform.dashboard');
    }

    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::prefix('/platform')->name('platform.')->middleware(['auth', 'platform-admin'])->group(function (): void {
    Route::get('/mfa/setup', [PlatformAdminMfaController::class, 'showSetup'])->name('mfa.setup');
    Route::post('/mfa/setup', [PlatformAdminMfaController::class, 'storeSetup'])->middleware('throttle:5,1')->name('mfa.setup.store');
    Route::get('/mfa/challenge', [PlatformAdminMfaController::class, 'showChallenge'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [PlatformAdminMfaController::class, 'verifyChallenge'])->middleware('throttle:5,1')->name('mfa.challenge.verify');
    Route::get('/mfa/recovery-codes', [PlatformAdminMfaController::class, 'showRecoveryCodes'])->name('mfa.recovery-codes');

    Route::middleware('platform-admin.mfa')->group(function (): void {
        Route::get('/', PlatformAdminDashboardController::class)->name('dashboard');
        Route::get('/security', [PlatformAdminMfaController::class, 'security'])->name('security');
        Route::post('/security/recovery-codes', [PlatformAdminMfaController::class, 'rotateRecoveryCodes'])
            ->middleware('throttle:5,1')->name('security.recovery-codes');
        Route::post('/security/reset-mfa', [PlatformAdminMfaController::class, 'resetMfa'])
            ->middleware('throttle:5,1')->name('security.reset-mfa');

        Route::get('/organizations', [PlatformOrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizations/create', [PlatformOrganizationController::class, 'create'])->name('organizations.create');
        Route::post('/organizations', [PlatformOrganizationController::class, 'store'])->name('organizations.store');
        Route::get('/organizations/{organization}/edit', [PlatformOrganizationController::class, 'edit'])->name('organizations.edit');
        Route::patch('/organizations/{organization}', [PlatformOrganizationController::class, 'update'])->name('organizations.update');

        Route::scopeBindings()->group(function (): void {
            Route::get('/organizations/{organization}', [PlatformOrganizationController::class, 'show'])->name('organizations.show');
            Route::get('/organizations/{organization}/requests/{accessRequest}', [PlatformAdminAccessRequestController::class, 'show'])
                ->name('organizations.requests.show');
            Route::get('/organizations/{organization}/requests/{accessRequest}/attachments/{attachment}', [PlatformAdminAccessRequestController::class, 'downloadAttachment'])
                ->name('organizations.requests.attachments.download');
        });
    });
});
