<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Http\Controllers\Auth\GoogleController;
use IsProject\Framework\Http\Controllers\Auth\LoginController;
use IsProject\Framework\Http\Controllers\Auth\PasswordResetController;
use IsProject\Framework\Http\Controllers\Auth\ProfileController;
use IsProject\Framework\Http\Controllers\Auth\RegisterController;

/*
 * The framework's own authentication.
 *
 * Route names are Laravel's conventional ones — login, logout, register,
 * password.*, profile.* — so anything expecting them (including the middleware
 * that redirects a guest to route('login')) keeps working. Set
 * isproject.auth.enabled to false if you use Breeze, Jetstream or Fortify
 * instead; these routes are then never registered.
 */

Route::middleware('web')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store']);

        Route::get('register', [RegisterController::class, 'create'])->name('register');
        Route::post('register', [RegisterController::class, 'store']);

        Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.update');

        Route::get('auth/google/redirect', [GoogleController::class, 'redirect'])->name('isproject.auth.google.redirect');
        Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('isproject.auth.google.callback');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
        Route::delete('profile/social/{account}', [ProfileController::class, 'unlink'])->name('profile.social.unlink');
    });
});
