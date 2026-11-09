<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StageCTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, bool $active = true, bool $mustChange = false): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role' => $role,
            'is_active' => $active,
            'must_change_password' => $mustChange,
        ])->save();

        return $user;
    }

    private function makeYearWithTerms(): array
    {
        $year = AcademicYear::create([
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true,
        ]);
        $first = Term::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester',
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18',
            'is_current' => true, 'enrollment_open' => true,
        ]);
        $second = Term::create([
            'academic_year_id' => $year->id, 'name' => '2nd Semester',
            'start_date' => '2027-01-11', 'end_date' => '2027-05-28',
        ]);

        return [$year, $first, $second];
    }

    public function test_admin_can_open_every_admin_page(): void
    {
        $admin = $this->makeUser('admin');

        foreach (['/admin/dashboard', '/users', '/programs', '/subjects', '/holidays',
            '/academic-years', '/audit-logs', '/settings', '/account/password'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_registrar_can_manage_subjects_but_nothing_administrative(): void
    {
        $registrar = $this->makeUser('registrar');

        $this->actingAs($registrar)->get('/registrar/dashboard')->assertOk();
        $this->actingAs($registrar)->get('/subjects')->assertOk();

        foreach (['/users', '/programs', '/holidays', '/academic-years', '/audit-logs', '/settings'] as $url) {
            $this->actingAs($registrar)->get($url)->assertForbidden();
        }
    }

    public function test_teacher_has_only_a_dashboard_and_password_page(): void
    {
        $teacher = $this->makeUser('teacher');

        $this->actingAs($teacher)->get('/teacher/dashboard')->assertOk();
        $this->actingAs($teacher)->get('/account/password')->assertOk();
        $this->actingAs($teacher)->get('/subjects')->assertForbidden();
        $this->actingAs($teacher)->get('/users')->assertForbidden();
        $this->actingAs($teacher)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_a_student_account_without_a_student_record_is_signed_out(): void
    {
        $student = $this->makeUser('student');

        $this->actingAs($student)->get('/student/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_creates_a_user_with_a_one_time_temporary_password(): void
    {
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'New Teacher', 'email' => 'new@sies.test', 'role' => 'teacher',
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::where('email', 'new@sies.test')->firstOrFail();
        $this->assertSame('teacher', $user->role);
        $this->assertTrue((bool) $user->must_change_password);
        $response->assertSessionHas('temp_password', fn ($password) => Hash::check($password, $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
    }

    public function test_student_accounts_are_not_created_from_user_management(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Pat Student', 'email' => 'pat@sies.test', 'role' => 'student',
        ])->assertSessionHasErrors('role');
    }

    public function test_an_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->patch(route('users.status', $admin))->assertSessionHas('error');

        $this->assertTrue((bool) $admin->fresh()->is_active);
    }

    public function test_a_user_who_must_change_their_password_is_sent_to_the_password_page(): void
    {
        $user = $this->makeUser('admin', true, true);

        $this->actingAs($user)->get('/admin/dashboard')->assertRedirect(route('account.password'));
        $this->actingAs($user)->get('/account/password')->assertOk();
    }

    public function test_changing_the_password_clears_the_forced_change(): void
    {
        $user = $this->makeUser('teacher', true, true);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'password',
            'password' => 'NewPassw0rd123',
            'password_confirmation' => 'NewPassw0rd123',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassw0rd123', $user->password));
        $this->assertFalse((bool) $user->must_change_password);
    }

    public function test_programs_are_saved_in_capitals_and_logged(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post('/programs', ['code' => 'bsit', 'name' => 'BS Information Technology', 'is_active' => 1])
            ->assertRedirect(route('programs.index'));

        $this->assertDatabaseHas('programs', ['code' => 'BSIT']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'program.created']);
    }

    public function test_two_holidays_cannot_share_a_date(): void
    {
        $admin = $this->makeUser('admin');
        $data = ['date' => '2026-12-25', 'description' => 'Christmas Day'];

        $this->actingAs($admin)->post('/holidays', $data)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/holidays', $data)->assertSessionHasErrors('date');
    }

    public function test_making_a_term_current_replaces_the_previous_one(): void
    {
        $admin = $this->makeUser('admin');
        [$year, $first, $second] = $this->makeYearWithTerms();

        $this->actingAs($admin)->post(route('terms.current', $second))->assertSessionHas('success');

        $this->assertFalse((bool) $first->fresh()->is_current);
        $this->assertFalse((bool) $first->fresh()->enrollment_open);
        $this->assertTrue((bool) $second->fresh()->is_current);
    }

    public function test_overlapping_terms_are_rejected(): void
    {
        $admin = $this->makeUser('admin');
        [$year] = $this->makeYearWithTerms();

        $this->actingAs($admin)->post(route('terms.store'), [
            'academic_year_id' => $year->id, 'name' => 'Summer',
            'start_date' => '2026-12-01', 'end_date' => '2027-01-20',
        ])->assertSessionHasErrors('start_date');
    }

    public function test_closing_a_year_locks_its_terms(): void
    {
        $admin = $this->makeUser('admin');
        [$year, $first] = $this->makeYearWithTerms();

        $this->actingAs($admin)->post(route('academic-years.close', $year))->assertSessionHas('success');

        $this->assertTrue($year->fresh()->isClosed());
        $this->assertTrue($first->fresh()->isClosed());
        $this->actingAs($admin)->get(route('terms.edit', $first))
            ->assertRedirect(route('academic-years.index'))
            ->assertSessionHas('error');
    }

    public function test_settings_changes_are_saved_and_logged(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->put('/settings', [
            'school_name' => 'Test College',
            'attendance_edit_window_days' => 5,
            'absentee_threshold_percent' => 25,
            'max_units_per_term' => 21,
            'excused_in_percentage' => 'attended',
            'late_counts_as_attended' => 1,
        ])->assertRedirect(route('settings.edit'));

        $this->assertDatabaseHas('settings', ['name' => 'school_name', 'value' => 'Test College']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }
}
