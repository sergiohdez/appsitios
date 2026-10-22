<?php

use App\Http\Controllers\Api\AccessRequestAttachmentController;
use App\Http\Controllers\Api\AccessRequestController;
use App\Http\Controllers\Api\SiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'tenant', 'throttle:120,1'])->group(function (): void {
    Route::get('/me', fn () => response()->json(['data' => auth()->user()->only('id', 'name', 'email')]))
        ->name('api.me');
    Route::get('/sites', [SiteController::class, 'index'])->name('api.sites.index');
    Route::post('/sites', [SiteController::class, 'store'])->name('api.sites.store');

    Route::get('/access-requests', [AccessRequestController::class, 'index'])
        ->name('api.access-requests.index');
    Route::post('/access-requests', [AccessRequestController::class, 'store'])
        ->name('api.access-requests.store');
    Route::get('/access-requests/{requestId}', [AccessRequestController::class, 'show'])
        ->whereUlid('requestId')
        ->name('api.access-requests.show');
    Route::post('/access-requests/{requestId}/approve', [AccessRequestController::class, 'approve'])
        ->whereUlid('requestId')
        ->name('api.access-requests.approve');
    Route::post('/access-requests/{requestId}/reject', [AccessRequestController::class, 'reject'])
        ->whereUlid('requestId')
        ->name('api.access-requests.reject');
    Route::get('/access-requests/{requestId}/attachments/{attachmentId}', AccessRequestAttachmentController::class)
        ->whereUlid('requestId')
        ->whereUlid('attachmentId')
        ->name('api.access-requests.attachments.show');
});
