<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Http\Controllers\GeneratorController;
use IsProject\Framework\Http\Middleware\EnsureGeneratorIsEnabled;

Route::middleware(array_merge(
    (array) config('isproject.generator.middleware', ['web']),
    [EnsureGeneratorIsEnabled::class],
))
    ->prefix((string) config('isproject.generator.path', 'isproject/generator'))
    ->name('isproject.generator.')
    ->group(function () {
        Route::get('/', [GeneratorController::class, 'index'])->name('index');
        Route::post('/', [GeneratorController::class, 'store'])->name('store');
        Route::post('/archivable', [GeneratorController::class, 'archivable'])->name('archivable');
        Route::delete('/', [GeneratorController::class, 'destroy'])->name('destroy');
    });
