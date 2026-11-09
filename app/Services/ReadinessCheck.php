<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * "Is this installation safe to put on the school network?"
 * Every check returns a level: ok, warn (should fix) or fail (must fix before go-live).
 */
class ReadinessCheck
{
    public const WEAK_DB_PASSWORDS = ['', 'sies123', 'password', 'root', 'secret', 'admin', '123456'];
    public const DEMO_PASSWORD = 'Sies-Test-2026!';
    public const PLACEHOLDER_SCHOOL_NAMES = ['', 'School', 'School Name', 'Your School'];

    /** @return array<int, array{key: string, level: string, label: string, detail: string}> */
    public static function run(): array
    {
        $checks = [];
        $add = function (string $key, string $level, string $label, string $detail) use (&$checks) {
            $checks[] = compact('key', 'level', 'label', 'detail');
        };

        // ---- Application
        app()->environment('production')
            ? $add('env', 'ok', 'Environment', 'APP_ENV is production.')
            : $add('env', 'fail', 'Environment', "APP_ENV is '" . app()->environment() . "'. Set APP_ENV=production on the real server.");

        config('app.debug')
            ? $add('debug', 'fail', 'Debug mode', 'APP_DEBUG is true, so errors show code and passwords to visitors. Set APP_DEBUG=false.')
            : $add('debug', 'ok', 'Debug mode', 'APP_DEBUG is off.');

        config('app.key')
            ? $add('key', 'ok', 'Application key', 'APP_KEY is set.')
            : $add('key', 'fail', 'Application key', 'APP_KEY is empty. Run: php artisan key:generate');

        $https = str_starts_with((string) config('app.url'), 'https://');
        $https
            ? $add('https', 'ok', 'HTTPS address', 'APP_URL uses https://.')
            : $add('https', 'warn', 'HTTPS address', 'APP_URL is ' . config('app.url') . '. Passwords travel in plain text without HTTPS. Use an https:// address.');

        config('app.timezone') === 'Asia/Manila'
            ? $add('timezone', 'ok', 'Time zone', 'Asia/Manila.')
            : $add('timezone', 'warn', 'Time zone', "Time zone is '" . config('app.timezone') . "'. Attendance times need Asia/Manila.");

        if (app()->environment('production')) {
            app()->configurationIsCached()
                ? $add('cache', 'ok', 'Config cache', 'Configuration is cached.')
                : $add('cache', 'warn', 'Config cache', 'Run: php artisan optimize');
        }

        // ---- Sessions
        if ($https && ! config('session.secure')) {
            $add('session_secure', 'warn', 'Session cookie', 'Set SESSION_SECURE_COOKIE=true so the sign-in cookie is only sent over HTTPS.');
        } else {
            $add('session_secure', 'ok', 'Session cookie', $https ? 'Secure cookie is on.' : 'Not applicable without HTTPS.');
        }

        (int) config('session.lifetime') > 120
            ? $add('session_lifetime', 'warn', 'Session length', 'Sessions last ' . config('session.lifetime') . ' minutes. 60 to 120 is better on shared computers.')
            : $add('session_lifetime', 'ok', 'Session length', config('session.lifetime') . ' minutes of inactivity.');

        // ---- Database
        $checks = array_merge($checks, self::database((string) config('database.default')));

        // ---- Accounts
        $demo = User::where('email', 'like', '%@sies.test')->count();
        $demo > 0
            ? $add('demo_accounts', 'fail', 'Test accounts', "{$demo} test account(s) with @sies.test addresses exist. Delete them before go-live.")
            : $add('demo_accounts', 'ok', 'Test accounts', 'No test accounts.');

        $sharedPassword = User::whereIn('role', ['admin', 'registrar', 'teacher'])->get(['id', 'password'])
            ->filter(fn (User $user) => Hash::check(self::DEMO_PASSWORD, $user->password))->count();
        $sharedPassword > 0
            ? $add('demo_passwords', 'fail', 'Known password', "{$sharedPassword} staff account(s) still use the published test password. Reset them.")
            : $add('demo_passwords', 'ok', 'Known password', 'No staff account uses the test password.');

        User::where('role', 'admin')->where('is_active', true)->exists()
            ? $add('admin', 'ok', 'Administrator', 'At least one active administrator exists.')
            : $add('admin', 'fail', 'Administrator', 'No active administrator. Run: php artisan sies:create-admin');

        // ---- School details
        in_array(trim((string) Setting::get('school_name', '')), self::PLACEHOLDER_SCHOOL_NAMES, true)
            ? $add('school_name', 'warn', 'School name', 'The school name is still a placeholder. Set it in System Settings (it prints on every report).')
            : $add('school_name', 'ok', 'School name', 'Set.');

        // ---- Files
        $writable = is_writable(storage_path()) && is_writable(base_path('bootstrap/cache'));
        $writable
            ? $add('storage', 'ok', 'Folders', 'storage and bootstrap/cache are writable.')
            : $add('storage', 'fail', 'Folders', 'storage or bootstrap/cache is not writable by the web server.');

        return $checks;
    }

    /** Checks of one database connection (read from settings only; nothing is queried). */
    public static function database(string $connection): array
    {
        $checks = [];
        $add = function (string $key, string $level, string $label, string $detail) use (&$checks) {
            $checks[] = compact('key', 'level', 'label', 'detail');
        };

        $db = config("database.connections.{$connection}", []);
        $username = (string) ($db['username'] ?? '');
        $password = (string) ($db['password'] ?? '');

        if ($connection === 'sqlite') {
            $add('db_engine', 'warn', 'Database', 'SQLite is for testing. Use MySQL on the real server.');

            return $checks;
        }

        $add('db_engine', 'ok', 'Database', "Using {$connection}.");

        $username === 'root'
            ? $add('db_user', 'fail', 'Database user', 'The app connects as root. Create a separate user that can only use this database.')
            : $add('db_user', 'ok', 'Database user', "Connects as '{$username}'.");

        if (in_array(strtolower($password), self::WEAK_DB_PASSWORDS, true)) {
            $add('db_password', 'fail', 'Database password', 'The database password is empty or a well-known one. Choose a long random password.');
        } elseif (strlen($password) < 12) {
            $add('db_password', 'warn', 'Database password', 'The database password is shorter than 12 characters.');
        } else {
            $add('db_password', 'ok', 'Database password', 'Strong enough.');
        }

        return $checks;
    }

    public static function hasFailures(array $checks): bool
    {
        return collect($checks)->contains('level', 'fail');
    }
}
