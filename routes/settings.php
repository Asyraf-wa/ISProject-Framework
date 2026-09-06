<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Http\Controllers\SettingsController;
use IsProject\Framework\Http\Middleware\AuthorizeSettings;

Route::middleware(array_merge(
    (array) config('isproject.settings.middleware', ['web', 'auth']),
    [AuthorizeSettings::class],
))
    ->prefix((string) config('isproject.settings.path', 'settings'))
    ->name('isproject.settings.')
    ->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::put('/', [SettingsController::class, 'update'])->name('update');
        Route::post('/cache', [SettingsController::class, 'clearCache'])->name('cache');
    });
