<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Http\Controllers\ManualController;

Route::middleware((array) config('isproject.manual.middleware', ['web', 'auth']))
    ->prefix((string) config('isproject.manual.path', 'manual'))
    ->name('isproject.manual.')
    ->group(function () {
        Route::get('/', [ManualController::class, 'index'])->name('index');

        // Constrained to the shape a chapter slug actually has, so a request
        // for anything else is a 404 from the router rather than a lookup.
        Route::get('{chapter}', [ManualController::class, 'show'])
            ->where('chapter', '[a-z0-9-]+')
            ->name('show');
    });
