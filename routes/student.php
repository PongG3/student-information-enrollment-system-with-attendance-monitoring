<?php

use App\Http\Controllers\StudentPortalController;
use App\Http\Middleware\StudentPortal;
use Illuminate\Support\Facades\Route;

// The student portal. Read-only: there are only GET routes here, so nothing can be changed.
// (Students change their password on the shared /account/password page.)
Route::middleware(['auth', 'role:student', StudentPortal::class])
    ->prefix('student')->name('student.')
    ->group(function () {
        Route::get('dashboard', [StudentPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('profile', [StudentPortalController::class, 'profile'])->name('profile');
        Route::get('courses', [StudentPortalController::class, 'courses'])->name('courses');
        Route::get('enrollments', [StudentPortalController::class, 'enrollments'])->name('enrollments');
        Route::get('schedule', [StudentPortalController::class, 'schedule'])->name('schedule');
        Route::get('attendance', [StudentPortalController::class, 'attendance'])->name('attendance');
        Route::get('attendance/{class}', [StudentPortalController::class, 'attendanceClass'])
            ->whereNumber('class')->name('attendance.show');
    });
