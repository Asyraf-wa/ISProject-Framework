<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Http\Controllers\ActivityController;

Route::middleware((array) config('isproject.activity.middleware', ['web', 'auth']))
    ->prefix((string) config('isproject.activity.path', 'activity'))
    ->name('isproject.activity.')
    ->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->name('index');
        Route::get('{activity}', [ActivityController::class, 'show'])
            ->where('activity', '[0-9]+')
            ->name('show');
    });
