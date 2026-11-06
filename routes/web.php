<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// /dashboard sends each logged-in user to their own role's dashboard.
Route::get('/dashboard', fn () => redirect()->route(auth()->user()->role . '.dashboard'))
    ->middleware('auth')
    ->name('dashboard');

// One group per role, built from config/sidebar.php.
foreach (config('sidebar') as $role => $workspace) {
    Route::middleware(['auth', 'role:' . $role])
        ->prefix($role)
        ->name($role . '.')
        ->group(function () use ($workspace) {
            foreach ($workspace['items'] as $item) {
                if ($item['slug'] === 'dashboard') {
                    Route::get('dashboard', DashboardController::class)->name('dashboard');
                } else {
                    Route::view($item['slug'], 'pages.placeholder', ['title' => $item['label']])
                        ->name($item['slug']);
                }
            }
        });
}

require __DIR__ . '/auth.php';
