<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'sies:create-admin {--name= : Full name} {--email= : Sign-in email}';

    protected $description = 'Create an administrator account (used for the first real account on a new server)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email address');
        // The password is never taken from the command line, so it does not end up in shell history.
        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User();
        $user->forceFill([
            'name' => $name,
            'email' => strtolower($email),
            'role' => 'admin',
            'password' => Hash::make($password),
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ])->save();

        AuditLogger::log('user.created', $user, "Created administrator account for {$user->name} from the command line", null, [
            'name' => $user->name, 'email' => $user->email, 'role' => 'admin',
        ]);

        $this->info("Administrator {$user->email} created. You can sign in now.");

        return self::SUCCESS;
    }
}
