<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Http\Controllers\AuditController;
use IsProject\Framework\Http\Middleware\AuthorizeSettings;

/*
 * Read-only by design: there is no route that edits or deletes a single audit
 * row. Pruning old rows wholesale is a deliberate, logged act — see
 * `php artisan isproject:audit-prune`.
 */
Route::middleware(array_merge(
    (array) config('isproject.audit.middleware', ['web', 'auth']),
    [AuthorizeSettings::class],
))
    ->prefix((string) config('isproject.audit.path', 'audit'))
    ->name('isproject.audit.')
    ->group(function () {
        Route::get('/', [AuditController::class, 'index'])->name('index');
        Route::get('{audit}', [AuditController::class, 'show'])->name('show');
    });
