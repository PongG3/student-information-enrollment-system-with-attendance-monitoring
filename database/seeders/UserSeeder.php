<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Never create fake accounts on a real server.
        if (app()->environment('production')) {
            $this->command?->warn('Skipped: fake accounts are not created in production.');

            return;
        }

        // FAKE accounts for local testing only.
        $users = [
            ['System Administrator', 'admin@sies.test', 'admin'],
            ['Registrar Admin', 'registrar@sies.test', 'registrar'],
            ['Maria Santos', 'teacher@sies.test', 'teacher'],
            ['Alex Rivera', 'student@sies.test', 'student'],
        ];

        foreach ($users as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => $name,
                'password' => Hash::make('Sies-Test-2026!'),
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
