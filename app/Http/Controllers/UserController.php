<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\Like;

class UserController extends Controller
{
    // Student accounts are created from the student's own record, not here.
    private const ASSIGNABLE_ROLES = ['admin', 'registrar', 'teacher'];

    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $like = Like::contains($request->input('search'));
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', (string) $request->input('role')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'roleLabels' => $this->roleLabels()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(), 'roleOptions' => $this->roleOptions()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
        ]);

        $password = Str::password(12, true, true, false, false);

        $user = new User();
        $user->forceFill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'password' => Hash::make($password),
            'is_active' => true,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ])->save();

        AuditLogger::log('user.created', $user, "Created {$user->role} account for {$user->name}", null, [
            'name' => $user->name, 'email' => $user->email, 'role' => $user->role,
        ]);

        Notify::temporaryPassword($user, true);

        return redirect()->route('users.index')
            ->with('success', 'User created. A link to choose a password was emailed; give them the temporary password below if they need to sign in now.')
            ->with('temp_password', $password)
            ->with('temp_for', $user->email);
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user, 'roleOptions' => $this->roleOptions()]);
    }

    public function update(Request $request, User $user)
    {
        $canChangeRole = $user->role !== 'student';

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
        ];
        if ($canChangeRole) {
            $rules['role'] = ['required', Rule::in(self::ASSIGNABLE_ROLES)];
        }

        $data = $request->validate($rules);

        if ($canChangeRole && $data['role'] !== $user->role) {
            if ($user->is($request->user())) {
                return back()->withErrors(['role' => 'You cannot change your own role.'])->withInput();
            }
            if ($user->role === 'admin' && $this->isLastActiveAdmin($user)) {
                return back()->withErrors(['role' => 'This is the last active administrator.'])->withInput();
            }
        }

        $old = $user->only(['name', 'email', 'role']);
        $data['email'] = strtolower($data['email']);
        $user->forceFill($data)->save();

        // A student's sign-in email is also the email on their record: keep them the same.
        if ($user->role === 'student' && $user->wasChanged('email')) {
            \App\Models\Student::withTrashed()->where('user_id', $user->id)->first()
                ?->forceFill(['email' => $user->email, 'pending_email' => null, 'pending_email_at' => null])->save();
        }

        // A changed email or role takes effect at the next sign-in, on every device.
        if ($user->wasChanged(['email', 'role']) && ! $user->is($request->user())) {
            $this->endSessions($user);
        }

        AuditLogger::log('user.updated', $user, "Updated account of {$user->name}", $old, $user->only(['name', 'email', 'role']));

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function toggleStatus(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->is_active && $user->role === 'admin' && $this->isLastActiveAdmin($user)) {
            return back()->with('error', 'You cannot deactivate the last active administrator.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        if (! $user->is_active) {
            $this->endSessions($user);
        }

        AuditLogger::log(
            $user->is_active ? 'user.activated' : 'user.deactivated',
            $user,
            ($user->is_active ? 'Activated' : 'Deactivated') . " account of {$user->name}",
        );

        return back()->with('success', $user->is_active ? 'Account activated.' : 'Account deactivated.');
    }

    public function resetPassword(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Use "Change Password" to change your own password.');
        }

        $password = Str::password(12, true, true, false, false);

        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => true,
        ])->save();

        $this->endSessions($user);

        AuditLogger::log('user.password_reset', $user, "Reset password of {$user->name}");

        Notify::temporaryPassword($user, false);

        return redirect()->route('users.index')
            ->with('success', 'Password reset. A link to choose a new password was emailed; give them the temporary password below if they need to sign in now.')
            ->with('temp_password', $password)
            ->with('temp_for', $user->email);
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return User::where('role', 'admin')
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }

    /** Sign the user out of every device. */
    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    private function roleLabels(): array
    {
        $labels = [];
        foreach (['admin', 'registrar', 'teacher', 'student'] as $role) {
            $labels[$role] = config("sidebar.{$role}.role_label", ucfirst($role));
        }

        return $labels;
    }

    private function roleOptions(): array
    {
        return collect(self::ASSIGNABLE_ROLES)
            ->mapWithKeys(fn ($role) => [$role => $this->roleLabels()[$role]])
            ->all();
    }
}
