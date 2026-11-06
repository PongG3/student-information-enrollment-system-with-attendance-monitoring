<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $first = Str::before($user->name, ' ');

        $greeting = match ($user->role) {
            'student' => "Welcome back, {$first}!",
            'teacher' => "Hello, {$first}!",
            'registrar' => 'Good day, Registrar!',
            default => 'Good day, Administrator!',
        };

        return view('dashboard', ['greeting' => $greeting]);
    }
}
