<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReadinessCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StageJTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, array $extra = []): User
    {
        $user = User::factory()->create();
        $user->forceFill(array_merge(['role' => $role, 'is_active' => true, 'must_change_password' => false], $extra))->save();

        return $user;
    }

    /** The level of one readiness check, by its key. */
    private function level(string $key): string
    {
        return collect(ReadinessCheck::run())->firstWhere('key', $key)['level'];
    }

    /** The level of one database check, for a connection described by config values. */
    private function dbLevel(string $key): string
    {
        return collect(ReadinessCheck::database('mysql'))->firstWhere('key', $key)['level'];
    }

    // ---------- Browser protections ----------

    public function test_pages_carry_the_security_headers(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->withServerVariables(['HTTPS' => 'on'])->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_pages_of_signed_in_users_are_not_kept_by_the_browser(): void
    {
        $response = $this->actingAs($this->makeUser('admin'))->get('/admin/dashboard');

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_error_pages_have_the_headers_too(): void
    {
        $this->actingAs($this->makeUser('teacher'))->get('/users')->assertForbidden()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    // ---------- Readiness check ----------

    public function test_debug_mode_is_a_must_fix(): void
    {
        config(['app.debug' => true]);
        $this->assertSame('fail', $this->level('debug'));

        config(['app.debug' => false]);
        $this->assertSame('ok', $this->level('debug'));
    }

    public function test_the_environment_must_be_production(): void
    {
        $this->assertSame('fail', $this->level('env'));   // the test suite runs as "testing"

        $this->app['env'] = 'production';
        $this->assertSame('ok', $this->level('env'));
    }

    public function test_https_and_cookie_settings_are_checked(): void
    {
        config(['app.url' => 'http://sies.school.local']);
        $this->assertSame('warn', $this->level('https'));

        config(['app.url' => 'https://sies.school.local', 'session.secure' => false]);
        $this->assertSame('ok', $this->level('https'));
        $this->assertSame('warn', $this->level('session_secure'));

        config(['session.secure' => true]);
        $this->assertSame('ok', $this->level('session_secure'));
    }

    public function test_long_sessions_are_flagged(): void
    {
        config(['session.lifetime' => 480]);
        $this->assertSame('warn', $this->level('session_lifetime'));

        config(['session.lifetime' => 60]);
        $this->assertSame('ok', $this->level('session_lifetime'));
    }

    public function test_database_user_and_password_are_checked(): void
    {
        config(['database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => 'sies123']);
        $this->assertSame('fail', $this->dbLevel('db_user'));
        $this->assertSame('fail', $this->dbLevel('db_password'));

        config(['database.connections.mysql.username' => 'sies_app', 'database.connections.mysql.password' => 'short-one']);
        $this->assertSame('ok', $this->dbLevel('db_user'));
        $this->assertSame('warn', $this->dbLevel('db_password'));

        config(['database.connections.mysql.password' => 'Xk29Lm41Pq77Rt03']);
        $this->assertSame('ok', $this->dbLevel('db_password'));
    }

    public function test_sqlite_is_only_for_testing(): void
    {
        $this->assertSame('warn', $this->level('db_engine'));
    }

    public function test_test_accounts_and_the_published_password_are_must_fix(): void
    {
        $this->assertSame('ok', $this->level('demo_accounts'));
        $this->assertSame('ok', $this->level('demo_passwords'));

        $this->makeUser('admin', ['email' => 'admin@sies.test', 'password' => Hash::make(ReadinessCheck::DEMO_PASSWORD)]);

        $this->assertSame('fail', $this->level('demo_accounts'));
        $this->assertSame('fail', $this->level('demo_passwords'));
    }

    public function test_a_real_account_with_its_own_password_passes(): void
    {
        $this->makeUser('admin', ['email' => 'it@school.edu.ph', 'password' => Hash::make('My-Own-Password-9')]);

        $this->assertSame('ok', $this->level('demo_accounts'));
        $this->assertSame('ok', $this->level('demo_passwords'));
        $this->assertSame('ok', $this->level('admin'));
    }

    public function test_an_administrator_must_exist_and_be_active(): void
    {
        $this->assertSame('fail', $this->level('admin'));

        $admin = $this->makeUser('admin', ['is_active' => false]);
        $this->assertSame('fail', $this->level('admin'));

        $admin->forceFill(['is_active' => true])->save();
        $this->assertSame('ok', $this->level('admin'));
    }

    public function test_the_school_name_must_not_be_a_placeholder(): void
    {
        Setting::updateOrCreate(['name' => 'school_name'], ['value' => 'School Name']);
        $this->assertSame('warn', $this->level('school_name'));

        Setting::updateOrCreate(['name' => 'school_name'], ['value' => 'Sample College of Arts']);
        $this->assertSame('ok', $this->level('school_name'));
    }

    public function test_the_check_command_fails_while_problems_remain_and_names_them(): void
    {
        config(['app.debug' => true]);

        $this->artisan('sies:check')
            ->expectsOutputToContain('Debug mode')
            ->expectsOutputToContain('Not ready for go-live')
            ->assertFailed();
    }

    public function test_the_check_command_passes_when_nothing_is_wrong(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false, 'app.url' => 'https://sies.school.local', 'session.secure' => true, 'session.lifetime' => 60,
        ]);
        Setting::updateOrCreate(['name' => 'school_name'], ['value' => 'Sample College of Arts']);
        $this->makeUser('admin', ['email' => 'it@school.edu.ph', 'password' => Hash::make('My-Own-Password-9')]);

        // Only warnings are left (the test database is SQLite and the config is not cached).
        $this->assertFalse(ReadinessCheck::hasFailures(ReadinessCheck::run()));
        $this->artisan('sies:check')->assertSuccessful();
    }

    // ---------- First administrator ----------

    public function test_create_admin_makes_a_working_administrator(): void
    {
        $this->artisan('sies:create-admin', ['--name' => 'Real Admin', '--email' => 'It@School.edu.ph'])
            ->expectsQuestion('Password', 'Strong-Passw0rd-2026')
            ->expectsQuestion('Confirm password', 'Strong-Passw0rd-2026')
            ->assertSuccessful();

        $user = User::where('email', 'it@school.edu.ph')->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertTrue((bool) $user->is_active);
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertTrue(Hash::check('Strong-Passw0rd-2026', $user->password));
        $this->assertSame('user.created', AuditLog::latest('id')->first()->action);

        $this->post('/login', ['email' => 'it@school.edu.ph', 'password' => 'Strong-Passw0rd-2026'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_create_admin_refuses_weak_or_unmatched_passwords(): void
    {
        $this->artisan('sies:create-admin', ['--name' => 'Real Admin', '--email' => 'it@school.edu.ph'])
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->artisan('sies:create-admin', ['--name' => 'Real Admin', '--email' => 'it@school.edu.ph'])
            ->expectsQuestion('Password', 'Strong-Passw0rd-2026')
            ->expectsQuestion('Confirm password', 'Different-Passw0rd-2026')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'it@school.edu.ph']);
    }

    public function test_create_admin_refuses_an_email_already_in_use(): void
    {
        $this->makeUser('teacher', ['email' => 'it@school.edu.ph']);

        $this->artisan('sies:create-admin', ['--name' => 'Real Admin', '--email' => 'it@school.edu.ph'])
            ->expectsQuestion('Password', 'Strong-Passw0rd-2026')
            ->expectsQuestion('Confirm password', 'Strong-Passw0rd-2026')
            ->assertFailed();

        $this->assertSame(1, User::where('email', 'it@school.edu.ph')->count());
    }

    // ---------- Shipped files ----------

    public function test_the_deployment_kit_is_in_the_project(): void
    {
        foreach (['docs/DEPLOYMENT.md', 'scripts/backup.ps1', '.env.production.example'] as $file) {
            $this->assertFileExists(base_path($file));
        }

        $example = file_get_contents(base_path('.env.production.example'));
        $this->assertStringContainsString('APP_DEBUG=false', $example);
        $this->assertStringContainsString('APP_ENV=production', $example);
        $this->assertStringNotContainsString('sies123', $example);
    }
}
