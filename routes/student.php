<?php

use App\Http\Controllers\AbsenceExcuseController;
use App\Http\Controllers\RequirementUploadController;
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
        Route::get('registration-form', [\App\Http\Controllers\RegistrationFormController::class, 'mine'])->middleware('throttle:10,1')->name('registration-form');
        Route::get('personal-data', [\App\Http\Controllers\PersonalDataController::class, 'mine'])->middleware('throttle:5,1')->name('personal-data');
        Route::post('requirements/{requirement}', [RequirementUploadController::class, 'store'])
            ->whereNumber('requirement')->middleware('throttle:20,1')->name('requirements.upload');
    });

// An uploaded admission document: its student, the registrar and administrators (always downloaded).
Route::get('requirement-files/{studentRequirement}', [RequirementUploadController::class, 'file'])
    ->middleware(['auth', 'role:admin,registrar,student'])->whereNumber('studentRequirement')->name('requirements.file');
// The same scan shown inside the document viewer (PDF and images only).
Route::get('requirement-files/{studentRequirement}/view', [RequirementUploadController::class, 'view'])
    ->middleware(['auth', 'role:admin,registrar,student'])->whereNumber('studentRequirement')->name('requirements.view');

// An excuse letter's attached file: its student, the class's teacher, the registrar and administrators.
Route::get('excuse-letters/{excuse}/file', [AbsenceExcuseController::class, 'file'])
    ->middleware(['auth', 'role:admin,registrar,teacher,student'])->whereNumber('excuse')->name('excuses.file');
