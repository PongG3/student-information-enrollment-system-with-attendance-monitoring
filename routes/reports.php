<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Reports: the administrator and registrar see everything, a teacher only sees their own classes.
// Each report is checked again inside the controller, because some reports are not open to every role.
Route::middleware(['auth', 'role:admin,registrar,teacher'])
    ->prefix('reports')->name('reports.')
    ->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('{slug}/print', [ReportController::class, 'print'])->where('slug', '[a-z-]+')->name('print');
        Route::get('{slug}/pdf', [ReportController::class, 'pdf'])->where('slug', '[a-z-]+')->name('pdf');
        Route::get('{slug}/excel', [ReportController::class, 'excel'])->where('slug', '[a-z-]+')->name('excel');
        Route::get('{slug}', [ReportController::class, 'show'])->where('slug', '[a-z-]+')->name('show');
    });
