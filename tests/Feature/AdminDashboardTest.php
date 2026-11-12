<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $registrar;

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        Term::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester',
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);

        $this->admin = $this->makeUser('admin', 'Ana Admin');
        $this->registrar = $this->makeUser('registrar', 'Rey Registrar');
    }

    private function makeUser(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

        return $user;
    }

    private function log(string $action, string $description, int $minutesAgo): void
    {
        AuditLog::create([
            'user_id' => $this->registrar->id, 'user_role' => 'registrar', 'action' => $action,
            'description' => $description, 'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_the_admin_dashboard_has_no_charts(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('data-chart', false)
            ->assertDontSee('Monthly enrollment trend')->assertDontSee('Weekly attendance trend')
            ->assertDontSee('Students by program')->assertDontSee('Requests by status')->assertDontSee('Attendance by status');
    }

    public function test_it_keeps_the_header_cards_attendance_and_activity_in_that_order(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSeeInOrder([
                'Good', 'Add user', 'Terms & enrollment', 'Add student', '1st Semester · 2026-2027',
                'Registered students', 'Enrolled students', 'Active accounts', 'Inactive accounts',
                "Today's attendance", 'Present', 'Late', 'Absent', 'Excused',
                'Recent activity',
            ]);
    }

    public function test_the_cards_have_no_decorative_icons_but_still_link(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('class="stat-icon"', false)
            ->assertSee('class="stat-go"', false)
            ->assertSee(route('students.index'), false)
            ->assertSee(route('classes.index'), false)
            ->assertSee(route('attendance.index'), false);
    }

    public function test_recent_activity_shows_the_latest_five_administrative_entries(): void
    {
        foreach (range(1, 7) as $i) {
            $this->log('student.created', "Registered student 2026-000{$i}", 70 - $i); // newest is 7
        }
        $this->log('login', 'Signed in as Registrar', 1);
        $this->log('logout', 'Signed out (Registrar)', 2);
        $this->log('login.failed', 'Failed sign-in attempt for someone', 3);
        $this->log('student.exported', 'Exported the student list (7 records)', 4);
        $this->log('report.exported', 'Student master list printed (7 rows)', 5);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertSeeInOrder([
            'Registered student 2026-0007', 'Registered student 2026-0006', 'Registered student 2026-0005',
            'Registered student 2026-0004', 'Registered student 2026-0003',
        ]);
        $response->assertDontSee('Registered student 2026-0002')
            ->assertDontSee('Signed in as Registrar')->assertDontSee('Signed out (Registrar)')
            ->assertDontSee('Failed sign-in attempt')->assertDontSee('Exported the student list')
            ->assertDontSee('Student master list printed')
            ->assertSee('Rey Registrar')
            ->assertSee(route('audit-logs.index'), false);
    }

    public function test_the_registrar_dashboard_is_unchanged(): void
    {
        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertOk()
            ->assertSee('Recent enrollment activity')->assertSee('Students to follow up')->assertSee('Enrollment by month')
            ->assertDontSee('Students by program')->assertDontSee('Requests by status')
            ->assertSee('class="stat-icon"', false);
    }
}
