<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * The page behind the "Choose my password" email link. The link is signed (so it cannot be changed or made up),
 * expires, and is tied to the account's current password, so it works once: setting a password ends it.
 */
class SetPasswordController extends Controller
{
    public function show(Request $request, User $user)
    {
        $this->check($request, $user);

        return view('auth.set-password', ['user' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $this->check($request, $user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();
        AuditLogger::log('password.set_by_link', $user, "{$user->name} chose a password from the emailed link");

        return redirect()->route('login')->with('status', 'Your password is set. Sign in with it.');
    }

    private function check(Request $request, User $user): void
    {
        abort_unless($user->is_active && hash_equals(Notify::fingerprint($user), (string) $request->query('v')), 403, 'This link has already been used or is no longer valid. Ask the registrar for a new one.');
    }
}
