<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\SetPasswordController;
use Illuminate\Support\Facades\Route;

// Sign in and sign out only. There is no public registration and no email-based
// password reset: an administrator creates accounts and resets passwords.
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

// The emailed "Choose my password" link: signed, expiring, and good for one use.
Route::middleware(['signed', 'throttle:10,1'])->group(function () {
    Route::get('set-password/{user}', [SetPasswordController::class, 'show'])->whereNumber('user')->name('password.set');
    Route::post('set-password/{user}', [SetPasswordController::class, 'update'])->whereNumber('user')->name('password.set.update');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
