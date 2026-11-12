<?php

use App\Http\Controllers\AbsenceExcuseController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudentSelfServiceController;
use App\Http\Middleware\StudentPortal;
use Illuminate\Support\Facades\Route;

// The student portal. Mostly read-only: a student may send an excuse letter (the teacher decides it), update their
// own contact details, and submit a new photo (the registrar approves it). Passwords change on /account/password.
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

        // Self-service: contact details change at once; a new photo waits for the registrar.
        Route::put('profile/contact', [StudentSelfServiceController::class, 'updateContact'])->middleware('throttle:20,1')->name('profile.contact');
        Route::post('profile/photo', [StudentSelfServiceController::class, 'uploadPhoto'])->middleware('throttle:10,1')->name('profile.photo');
        Route::delete('profile/photo', [StudentSelfServiceController::class, 'cancelPhoto'])->name('profile.photo.cancel');
        Route::get('photo', [StudentSelfServiceController::class, 'photo'])->name('photo');
    });

// An excuse letter's attached file: its student, the class's teacher, the registrar and administrators.
Route::get('excuse-letters/{excuse}/file', [AbsenceExcuseController::class, 'file'])
    ->middleware(['auth', 'role:admin,registrar,teacher,student'])->whereNumber('excuse')->name('excuses.file');
