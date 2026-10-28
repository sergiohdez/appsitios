<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\ClientSiteController;
use App\Http\Controllers\Client\ClientUserController;
use App\Http\Controllers\PlatformAdminAccessRequestController;
use App\Http\Controllers\PlatformAdminDashboardController;
use App\Http\Controllers\PlatformAdminMfaController;
use App\Http\Controllers\PlatformOrganizationController;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => view('auth.login'))->middleware('guest')->name('login');
Route::post('/login', [SessionController::class, 'store'])->middleware(['guest', 'throttle:5,1'])->name('login.store');
Route::get('/password/setup/{token}', [PasswordSetupController::class, 'create'])->middleware('guest')->name('password.reset');
Route::post('/password/setup', [PasswordSetupController::class, 'store'])->middleware(['guest', 'throttle:5,1'])->name('password.update');
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/', function () {
    if (auth()->user()->platformAdminAccount()->exists()) {
        return redirect()->route('platform.dashboard');
    }

    $memberships = auth()->user()->memberships()->where('is_active', true)->with('organization')->get();

    if ($memberships->count() !== 1 || ! $memberships->first()->organization?->is_active) {
        abort(403);
    }

    if ($memberships->first()->canManage()) {
        return redirect()->route('client.dashboard');
    }

    return view('dashboard', ['membership' => $memberships->first()]);
})->middleware('auth')->name('dashboard');

Route::prefix('/client')->name('client.')->middleware(['auth', 'tenant', 'client-admin'])->group(function (): void {
    Route::get('/', ClientDashboardController::class)->name('dashboard');
    Route::get('/users', [ClientUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [ClientUserController::class, 'create'])->name('users.create');
    Route::post('/users', [ClientUserController::class, 'store'])->name('users.store');
    Route::get('/users/{membershipId}/edit', [ClientUserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{membershipId}', [ClientUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{membershipId}', [ClientUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{membershipId}/invitation', [ClientUserController::class, 'resendInvitation'])
        ->middleware('throttle:5,1')->name('users.invitation');
    Route::get('/sites', [ClientSiteController::class, 'index'])->name('sites.index');
    Route::get('/sites/create', [ClientSiteController::class, 'create'])->name('sites.create');
    Route::post('/sites', [ClientSiteController::class, 'store'])->name('sites.store');
    Route::get('/sites/{siteId}/edit', [ClientSiteController::class, 'edit'])->name('sites.edit');
    Route::patch('/sites/{siteId}', [ClientSiteController::class, 'update'])->name('sites.update');
    Route::delete('/sites/{siteId}', [ClientSiteController::class, 'destroy'])->name('sites.destroy');
});

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
        Route::post('/organizations/{organization}/admin', [PlatformOrganizationController::class, 'createAdmin'])
            ->middleware('throttle:5,1')->name('organizations.admin.store');
        Route::post('/organizations/{organization}/admin-invitation', [PlatformOrganizationController::class, 'resendAdminInvitation'])
            ->middleware('throttle:5,1')->name('organizations.admin-invitation');

        Route::scopeBindings()->group(function (): void {
            Route::get('/organizations/{organization}', [PlatformOrganizationController::class, 'show'])->name('organizations.show');
            Route::get('/organizations/{organization}/requests/{accessRequest}', [PlatformAdminAccessRequestController::class, 'show'])
                ->name('organizations.requests.show');
            Route::get('/organizations/{organization}/requests/{accessRequest}/attachments/{attachment}', [PlatformAdminAccessRequestController::class, 'downloadAttachment'])
                ->name('organizations.requests.attachments.download');
        });
    });
});
