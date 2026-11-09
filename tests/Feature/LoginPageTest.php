<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, array $extra = []): User
    {
        $user = User::factory()->create();
        $user->forceFill(array_merge(['role' => $role, 'is_active' => true, 'must_change_password' => false], $extra))->save();

        return $user;
    }

    public function test_the_page_has_no_keep_me_signed_in_option(): void
    {
        $this->get('/login')->assertOk()
            ->assertDontSee('Keep me signed in')
            ->assertDontSee('name="remember"', false);
    }

    public function test_the_password_can_be_shown_and_hidden(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('id="togglePassword"', false)
            ->assertSee('aria-label="Show password"', false);
    }

    public function test_the_form_helps_browsers_and_password_managers(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('autofocus', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('autocomplete="current-password"', false);
    }

    public function test_the_page_names_every_kind_of_user_and_the_headline_reads_well(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('WHO CAN SIGN IN')->assertSee('Students')->assertSee('Teachers')
            ->assertSee('Registrar and administrators')
            ->assertSee('A smarter way to manage academic life.')
            ->assertDontSee('YOUR ACADEMIC OVERVIEW');
    }

    public function test_the_school_name_shows_only_when_it_is_a_real_name(): void
    {
        Setting::updateOrCreate(['name' => 'school_name'], ['value' => 'School Name']);
        $this->get('/login')->assertOk()->assertDontSee('Sample College of Arts');

        Setting::updateOrCreate(['name' => 'school_name'], ['value' => 'Sample College of Arts']);
        $this->get('/login')->assertOk()->assertSee('Sample College of Arts');
    }

    public function test_the_school_name_is_escaped(): void
    {
        Setting::updateOrCreate(['name' => 'school_name'], ['value' => '<script>alert(1)</script> College']);

        $this->get('/login')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_help_line_follows_the_contact_setting(): void
    {
        $this->get('/login')->assertOk()->assertSee("Ask the registrar's office", false);

        Setting::updateOrCreate(['name' => 'registrar_contact'], ['value' => 'registrar@school.edu.ph']);
        $this->get('/login')->assertOk()
            ->assertSee('href="mailto:registrar@school.edu.ph"', false)->assertSee('Contact the registrar');

        Setting::updateOrCreate(['name' => 'registrar_contact'], ['value' => '(02) 8123 4567']);
        $this->get('/login')->assertOk()->assertSee('(02) 8123 4567')->assertDontSee('mailto:', false);
    }

    public function test_the_footer_is_a_real_notice(): void
    {
        $this->get('/login')->assertOk()->assertSee('Authorized users only. Sign-ins and changes are recorded.');
    }

    public function test_signing_in_still_works_and_is_logged(): void
    {
        $user = $this->makeUser('teacher', ['email' => 'teacher@school.edu.ph']);

        $this->post('/login', ['email' => 'teacher@school.edu.ph', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('login', AuditLog::latest('id')->first()->action);
    }

    public function test_a_wrong_password_shows_a_message_and_is_logged(): void
    {
        $this->makeUser('teacher', ['email' => 'teacher@school.edu.ph']);

        $this->from('/login')->post('/login', ['email' => 'teacher@school.edu.ph', 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['action' => 'login.failed']);
    }

    public function test_the_registrar_contact_is_saved_from_settings(): void
    {
        $admin = $this->makeUser('admin');
        $payload = [
            'school_name' => 'Sample College of Arts', 'registrar_contact' => 'registrar@school.edu.ph',
            'attendance_edit_window_days' => 7, 'absentee_threshold_percent' => 20, 'max_units_per_term' => 24,
            'excused_in_percentage' => 'excluded', 'late_counts_as_attended' => 1,
        ];

        $this->actingAs($admin)->put('/settings', $payload)->assertRedirect(route('settings.edit'));
        $this->assertSame('registrar@school.edu.ph', Setting::get('registrar_contact'));

        $this->actingAs($admin)->get('/settings')->assertOk()->assertSee('Registrar contact (optional)');
    }

    public function test_saving_settings_without_a_contact_does_not_log_a_phantom_change(): void
    {
        $admin = $this->makeUser('admin');
        $payload = [
            'school_name' => 'Sample College of Arts',
            'attendance_edit_window_days' => 7, 'absentee_threshold_percent' => 20, 'max_units_per_term' => 24,
            'excused_in_percentage' => 'excluded', 'late_counts_as_attended' => 1,
        ];
        $this->actingAs($admin)->put('/settings', $payload);
        $before = AuditLog::where('action', 'settings.updated')->count();

        // Same values again, with an empty contact box: nothing changed, so nothing new is logged.
        $this->actingAs($admin)->put('/settings', $payload + ['registrar_contact' => ''])->assertRedirect();

        $this->assertSame($before, AuditLog::where('action', 'settings.updated')->count());
    }

    public function test_login_page_still_loads_when_the_settings_table_is_missing(): void
    {
        \Illuminate\Support\Facades\Schema::drop('settings');

        $this->get('/login')->assertOk()->assertSee('WHO CAN SIGN IN');
    }
}
