<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A student account only works while it is linked to a student record that is not archived.
 * The record is found from the signed-in account, never from the address, so a student
 * can only ever see their own data.
 */
class StudentPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        // Archived (soft-deleted) records are left out on purpose.
        $student = Student::with('program')->where('user_id', $request->user()->id)->first();

        if (! $student) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'No active student record is linked to this account. Please contact the registrar.']);
        }

        $request->attributes->set('student', $student);

        return $next($request);
    }
}
