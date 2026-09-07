<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Http\Controllers\DashboardController;

/*
 * A starting dashboard, built only from tables this package owns.
 *
 * Registered under isproject.dashboard so it never collides with an
 * application's own "dashboard" route name. Point your menu at it, or at your
 * own screen — nothing else in the framework links here.
 */
Route::middleware((array) config('isproject.dashboard.middleware', ['web', 'auth']))
    ->prefix((string) config('isproject.dashboard.path', 'dashboard'))
    ->name('isproject.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    });
