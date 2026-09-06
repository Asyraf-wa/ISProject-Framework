<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Http\Controllers\RoleController;
use IsProject\Framework\Http\Controllers\UserController;
use IsProject\Framework\Http\Middleware\AuthorizeSettings;

Route::middleware(array_merge(
    (array) config('isproject.access.middleware', ['web', 'auth']),
    [AuthorizeSettings::class],
))
    ->prefix((string) config('isproject.access.path', 'access'))
    ->name('isproject.')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Sits above roles/{role} so "sync" is not read as a role id.
        Route::post('roles/sync', [RoleController::class, 'sync'])->name('roles.sync');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
