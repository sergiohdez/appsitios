<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\PlatformAdminDashboardController;
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

Route::get('/platform', PlatformAdminDashboardController::class)
    ->middleware(['auth', 'platform-admin'])
    ->name('platform.dashboard');
