<?php

declare(strict_types=1);

use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\Webhooks\PixelforgeWebhookController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request): RedirectResponse {
    return $request->user() !== null
        ? redirect()->route('generations.index')
        : redirect()->route('login');
});

Route::post('/webhooks/pixelforge', PixelforgeWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.pixelforge');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:auth');
    Route::post('/login/demo', [AuthenticatedSessionController::class, 'demoLogin'])
        ->middleware('throttle:auth')
        ->name('login.demo');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:auth');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::post('/generations', [GenerationController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('generations.store');
    Route::get('/generations/status', [GenerationController::class, 'status'])
        ->middleware('throttle:generation-sync')
        ->name('generations.status');
    Route::post('/generations/{generation}/sync', [GenerationController::class, 'sync'])
        ->middleware('throttle:generation-sync')
        ->name('generations.sync');

    Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('/credits', [CreditController::class, 'store'])
        ->middleware('throttle:credits')
        ->name('credits.store');
    Route::post('/credits/drain', [CreditController::class, 'drain'])
        ->middleware('throttle:credits')
        ->name('credits.drain');

    Route::get('/admin/reports', [AdminReportController::class, 'index'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.reports');
});

Route::fallback(function (): never {
    abort(404);
});
