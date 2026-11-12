<?php

use App\Http\Controllers\AbsenceExcuseController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Middleware\StudentPortal;
use Illuminate\Support\Facades\Route;

// The student portal. Read-only except for one thing: a student may send an excuse letter for an
// absence or late arrival (the teacher decides it). Students change their password on /account/password.
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
        Route::post('attendance/{class}/excuse', [AbsenceExcuseController::class, 'store'])
            ->whereNumber('class')->middleware('throttle:20,1')->name('attendance.excuse');
    });

// An excuse letter's attached file: its student, the class's teacher, the registrar and administrators.
Route::get('excuse-letters/{excuse}/file', [AbsenceExcuseController::class, 'file'])
    ->middleware(['auth', 'role:admin,registrar,teacher,student'])->whereNumber('excuse')->name('excuses.file');
