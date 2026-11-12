<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ClassOfferingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\StaffProfileController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentRequirementController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// The school logo from System Settings (public: shown on the sign-in page).
Route::get('/school-logo', [SettingController::class, 'logo'])->name('school.logo');

// Privacy notice (Data Privacy Act, RA 10173): public, linked from the sign-in page and the student forms.
Route::view('/privacy', 'privacy')->name('privacy');

// /dashboard sends each logged-in user to their own role's dashboard.
Route::get('/dashboard', function () {
    $route = auth()->user()->role . '.dashboard';

    // A role without a dashboard cannot continue.
    if (! Route::has($route)) {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login')
            ->withErrors(['email' => 'Your account has no workspace. Please contact the registrar.']);
    }

    return redirect()->route($route);
})->middleware('auth')->name('dashboard');

// One dashboard per staff role.
foreach (['admin', 'registrar', 'teacher'] as $role) {
    Route::get("/{$role}/dashboard", DashboardController::class)
        ->middleware(['auth', "role:{$role}"])
        ->name("{$role}.dashboard");
}

// The top-bar search: staff only (teachers see only their own classes).
Route::get('/search', SearchController::class)
    ->middleware(['auth', 'role:admin,registrar,teacher'])
    ->name('search');

// Every role can change its own password.
Route::middleware(['auth', 'role:admin,registrar,teacher,student'])->group(function () {
    Route::get('/account/password', [AccountPasswordController::class, 'edit'])->name('account.password');
    Route::put('/account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');
});

// Administrator and Registrar.
Route::middleware(['auth', 'role:admin,registrar'])->group(function () {
    Route::resource('subjects', SubjectController::class)->only(['index', 'store', 'update']);

    Route::resource('classes', ClassOfferingController::class)
        ->parameters(['classes' => 'class_offering'])
        ->except(['show', 'destroy']);

    // Student records (archived students are still reachable so they can be viewed and restored).
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/export', [StudentController::class, 'export'])->name('students.export');
    Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
    // Import from CSV: upload -> preview (every row checked) -> confirm.
    Route::get('students/import', [StudentImportController::class, 'create'])->name('students.import');
    Route::get('students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::post('students/import/preview', [StudentImportController::class, 'preview'])->name('students.import.preview');
    Route::post('students/import', [StudentImportController::class, 'store'])->name('students.import.store');
    Route::post('students/import/cancel', [StudentImportController::class, 'cancel'])->name('students.import.cancel');
    Route::post('students', [StudentController::class, 'store'])->name('students.store');
    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show')->whereNumber('student')->withTrashed();
    Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit')->whereNumber('student');
    Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update')->whereNumber('student');
    Route::delete('students/{student}', [StudentController::class, 'archive'])->name('students.archive')->whereNumber('student');
    Route::post('students/{student}/restore', [StudentController::class, 'restore'])->name('students.restore')->whereNumber('student')->withTrashed();
    Route::get('students/{student}/photo', [StudentController::class, 'photo'])->name('students.photo')->whereNumber('student')->withTrashed();
    // A photo the student submitted: shown to the registrar, then approved (becomes official) or rejected.
    Route::get('students/{student}/photo/pending', [StudentController::class, 'pendingPhoto'])->name('students.photo.pending')->whereNumber('student');
    Route::post('students/{student}/photo/approve', [StudentController::class, 'approvePhoto'])->name('students.photo.approve')->whereNumber('student');
    Route::post('students/{student}/photo/reject', [StudentController::class, 'rejectPhoto'])->name('students.photo.reject')->whereNumber('student');
    Route::post('students/{student}/requirements/{requirement}', [StudentRequirementController::class, 'update'])
        ->name('students.requirements.update')->whereNumber(['student', 'requirement']);

    // Sign-in accounts for students (created from the student's own record).
    Route::post('students/{student}/account', [StudentAccountController::class, 'store'])->name('students.account.store')->whereNumber('student');
    Route::post('students/{student}/account/reset', [StudentAccountController::class, 'resetPassword'])->name('students.account.reset')->whereNumber('student');
    Route::post('students/{student}/account/status', [StudentAccountController::class, 'toggleStatus'])->name('students.account.status')->whereNumber('student');

    // Enrollment workflow: pending -> approved -> enrolled (or rejected / cancelled).
    Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('enrollments/create', [EnrollmentController::class, 'create'])->name('enrollments.create');
    Route::post('enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::post('enrollments/bulk', [EnrollmentController::class, 'bulk'])->name('enrollments.bulk');
    foreach (['approve', 'reject', 'enroll', 'cancel', 'reopen'] as $action) {
        Route::post("enrollments/{enrollment}/{$action}", [EnrollmentController::class, $action])
            ->name("enrollments.{$action}")->whereNumber('enrollment');
    }
});

// A teacher's own classes.
Route::get('/teacher/classes', [ClassOfferingController::class, 'mine'])
    ->middleware(['auth', 'role:teacher'])
    ->name('teacher.classes');

// Class details: staff see any class, a teacher only their own (checked by ClassOfferingPolicy).
Route::get('classes/{class_offering}', [ClassOfferingController::class, 'show'])
    ->middleware(['auth', 'role:admin,registrar,teacher'])
    ->whereNumber('class_offering')
    ->name('classes.show');

// Attendance: teachers record their own classes, the registrar monitors, the administrator can correct.
Route::middleware(['auth', 'role:admin,registrar,teacher'])->group(function () {
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/sheet', [AttendanceController::class, 'sheet'])->name('attendance.sheet');
    Route::get('attendance/classes/{class_offering}', [AttendanceController::class, 'summary'])
        ->whereNumber('class_offering')->name('attendance.summary');
});
Route::post('attendance/sheet', [AttendanceController::class, 'save'])
    ->middleware(['auth', 'role:admin,teacher'])->name('attendance.save');

// Staff data privacy policy: every staff member reads and agrees; the administrator writes and publishes it.
Route::middleware(['auth', 'role:admin,registrar,teacher'])->group(function () {
    Route::get('privacy-policy', [PrivacyPolicyController::class, 'show'])->name('privacy-policy.show');
    Route::post('privacy-policy/agree', [PrivacyPolicyController::class, 'agree'])->name('privacy-policy.agree');

    // My Profile for staff: department, office, contact number and photo.
    Route::get('profile', [StaffProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [StaffProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/photo', [StaffProfileController::class, 'uploadPhoto'])->middleware('throttle:10,1')->name('profile.photo');
    Route::delete('profile/photo', [StaffProfileController::class, 'removePhoto'])->name('profile.photo.remove');
});

// A staff member's photo: anyone signed in (students see their teachers' photos).
Route::get('staff/{user}/photo', [StaffProfileController::class, 'photo'])
    ->middleware(['auth', 'role:admin,registrar,teacher,student'])->whereNumber('user')->name('staff.photo');

// Administrator only.
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('privacy-policy/edit', [PrivacyPolicyController::class, 'edit'])->name('privacy-policy.edit');
    Route::put('privacy-policy', [PrivacyPolicyController::class, 'update'])->name('privacy-policy.update');

    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    Route::resource('programs', ProgramController::class)->only(['index', 'store', 'update']);
    Route::resource('holidays', HolidayController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('academic-years', AcademicYearController::class)->except(['show', 'destroy']);
    Route::post('academic-years/{academic_year}/current', [AcademicYearController::class, 'makeCurrent'])->name('academic-years.current');
    Route::post('academic-years/{academic_year}/close', [AcademicYearController::class, 'close'])->name('academic-years.close');
    Route::post('academic-years/{academic_year}/reopen', [AcademicYearController::class, 'reopen'])->name('academic-years.reopen');

    Route::resource('terms', TermController::class)->only(['create', 'store', 'edit', 'update']);
    Route::post('terms/{term}/current', [TermController::class, 'makeCurrent'])->name('terms.current');
    Route::post('terms/{term}/enrollment', [TermController::class, 'toggleEnrollment'])->name('terms.enrollment');
    Route::post('terms/{term}/close', [TermController::class, 'close'])->name('terms.close');
    Route::post('terms/{term}/reopen', [TermController::class, 'reopen'])->name('terms.reopen');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
});

require __DIR__ . '/auth.php';
require __DIR__ . '/reports.php';
require __DIR__ . '/student.php';
