<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Http\Controllers\MenuController;
use IsProject\Framework\Http\Middleware\AuthorizeSettings;

Route::middleware(array_merge(
    (array) config('isproject.menu_admin.middleware', ['web', 'auth']),
    [AuthorizeSettings::class],
))
    ->prefix((string) config('isproject.menu_admin.path', 'menu'))
    ->name('isproject.menu.')
    ->group(function () {
        Route::get('/', [MenuController::class, 'index'])->name('index');
        Route::get('create', [MenuController::class, 'create'])->name('create');
        Route::post('/', [MenuController::class, 'store'])->name('store');

        // Static paths before {item}, so "reorder" is never read as an id.
        Route::post('reorder', [MenuController::class, 'reorder'])->name('reorder');
        Route::post('import', [MenuController::class, 'import'])->name('import');
        Route::post('adopt', [MenuController::class, 'adopt'])->name('adopt');
        Route::delete('reset', [MenuController::class, 'reset'])->name('reset');

        Route::get('{item}/edit', [MenuController::class, 'edit'])->name('edit');
        Route::put('{item}', [MenuController::class, 'update'])->name('update');
        Route::delete('{item}', [MenuController::class, 'destroy'])->name('destroy');
        Route::post('{item}/toggle', [MenuController::class, 'toggle'])->name('toggle');
        Route::post('{item}/move', [MenuController::class, 'move'])->name('move');
    });
