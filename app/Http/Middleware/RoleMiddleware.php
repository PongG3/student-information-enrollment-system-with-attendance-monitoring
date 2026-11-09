<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Inactive accounts are logged out immediately.
        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account is inactive. Please contact the registrar.']);
        }

        // After an administrator resets a password, the user must choose a new one first.
        if ($user->must_change_password && ! $request->routeIs('account.password', 'account.password.update')) {
            return redirect()->route('account.password')
                ->with('warning', 'Please set a new password to continue.');
        }

        // Wrong role: the server refuses, whatever the menu shows.
        abort_unless(in_array($user->role, $roles, true), 403);

        return $next($request);
    }
}
