<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── SPA shell — Vue router handles /game/*, /pro, etc. ───────────────────────
Route::get('/', fn() => Inertia::render('App'))->name('home');
Route::get('/game/{any}', fn() => Inertia::render('App'))->where('any', '.*');
Route::get('/join/{token}', fn() => Inertia::render('App'));
Route::get('/pro', fn() => Inertia::render('App'));

// ── Billing (requires auth) ───────────────────────────────────────────────────
Route::middleware('auth')->prefix('billing')->name('billing.')->group(function () {
    Route::get('/',           [BillingController::class, 'index'])->name('index');
    Route::post('/subscribe', [BillingController::class, 'subscribe'])->name('subscribe');
    Route::post('/cancel',    [BillingController::class, 'cancel'])->name('cancel');
});

// ── Profile (Breeze default) ──────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
