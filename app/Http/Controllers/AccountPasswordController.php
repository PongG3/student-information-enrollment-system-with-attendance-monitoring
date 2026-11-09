<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountPasswordController extends Controller
{
    public function edit(Request $request)
    {
        return view('account.password', ['forced' => (bool) $request->user()->must_change_password]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(10)->letters()->numbers()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        AuditLogger::log('password.changed', $user, 'Changed own password');

        return redirect()->route('dashboard')->with('success', 'Your password was changed.');
    }
}
