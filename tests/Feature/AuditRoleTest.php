<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditRoleTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $name = 'Test Person'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

        return $user;
    }

    public function test_sign_in_and_sign_out_record_the_role(): void
    {
        $teacher = $this->makeUser('teacher', 'Maria Santos');

        $this->post('/login', ['email' => $teacher->email, 'password' => 'password']);
        $this->post('/logout');

        $login = AuditLog::where('action', 'login')->firstOrFail();
        $this->assertEquals($teacher->id, $login->user_id);
        $this->assertSame('teacher', $login->user_role);
        $this->assertStringContainsString('Teacher', $login->description);

        $logout = AuditLog::where('action', 'logout')->firstOrFail();
        $this->assertSame('teacher', $logout->user_role);
        $this->assertStringContainsString('Teacher', $logout->description);
    }

    public function test_each_role_is_recorded_for_registrar_and_student(): void
    {
        foreach (['registrar', 'student'] as $role) {
            $user = $this->makeUser($role, ucfirst($role) . ' Person');
            $this->post('/login', ['email' => $user->email, 'password' => 'password']);
            $this->post('/logout');
        }

        $this->assertSame('registrar', AuditLog::where('action', 'login')->orderBy('id')->first()->user_role);
        $this->assertSame('student', AuditLog::where('action', 'login')->orderByDesc('id')->first()->user_role);
        $this->assertSame(2, AuditLog::where('action', 'logout')->count());
    }

    public function test_a_wrong_password_is_logged_with_the_account_role(): void
    {
        $teacher = $this->makeUser('teacher');

        $this->post('/login', ['email' => $teacher->email, 'password' => 'not-the-password']);

        $log = AuditLog::where('action', 'login.failed')->firstOrFail();
        $this->assertSame('teacher', $log->user_role);
        $this->assertStringContainsString($teacher->email, $log->description);
    }

    public function test_the_log_page_shows_the_role_next_to_the_name_and_can_filter_by_it(): void
    {
        $admin = $this->makeUser('admin', 'Ada Admin');
        $teacher = $this->makeUser('teacher', 'Maria Santos');

        $this->post('/login', ['email' => $teacher->email, 'password' => 'password']);
        $this->post('/logout');

        $this->actingAs($admin)->get('/audit-logs')
            ->assertOk()->assertSee('Maria Santos')->assertSee('Teacher')->assertSee('Signed in as Teacher');

        // The name also appears in the user dropdown, so check for the log entry itself.
        $this->actingAs($admin)->get('/audit-logs?role=teacher')->assertOk()
            ->assertSee('Signed in as Teacher')->assertDontSee('No log entries match');
        $this->actingAs($admin)->get('/audit-logs?role=registrar')->assertOk()
            ->assertSee('No log entries match')->assertDontSee('Signed in as Teacher');
    }

    public function test_the_role_at_the_time_is_kept_when_the_role_changes_later(): void
    {
        $user = $this->makeUser('teacher', 'Maria Santos');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/logout');

        $user->forceFill(['role' => 'registrar'])->save();

        $this->assertSame('Teacher', AuditLog::where('action', 'login')->firstOrFail()->role_label);
    }

    public function test_older_entries_without_a_stored_role_use_the_current_role(): void
    {
        $user = $this->makeUser('registrar');
        $log = AuditLog::create([
            'user_id' => $user->id, 'action' => 'login', 'description' => 'Signed in', 'created_at' => now(),
        ]);

        $this->assertSame('Registrar', $log->fresh()->role_label);
    }

    public function test_other_actions_also_record_the_role(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post('/programs', ['code' => 'BSIT', 'name' => 'BS Information Technology', 'is_active' => 1]);

        $this->assertSame('admin', AuditLog::where('action', 'program.created')->firstOrFail()->user_role);
    }
}
