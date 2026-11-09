<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StageLTest extends TestCase
{
    use RefreshDatabase;

    // "Today" is Monday 9 November 2026. Classes meet on Mondays and Wednesdays.
    private Term $term;
    private Program $program;
    private User $admin;
    private User $registrar;
    private User $teacher;
    private User $otherTeacher;
    private ClassOffering $mine;
    private ClassOffering $theirs;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 10:00:00');

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $this->term = Term::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester',
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true, 'enrollment_open' => true,
        ]);
        $this->program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);

        $this->admin = $this->makeUser('admin');
        $this->registrar = $this->makeUser('registrar');
        $this->teacher = $this->makeUser('teacher');
        $this->otherTeacher = $this->makeUser('teacher');

        $this->mine = $this->makeClass('CL-1001', 'CS101', 'Intro to Computing', $this->teacher);
        $this->theirs = $this->makeClass('CL-2002', 'MATH3', 'Calculus', $this->otherTeacher);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $role, array $extra = []): User
    {
        $user = User::factory()->create();
        $user->forceFill(array_merge(['role' => $role, 'is_active' => true, 'must_change_password' => false], $extra))->save();

        return $user;
    }

    private function makeStudent(string $number, string $first, string $last): Student
    {
        return Student::create([
            'student_number' => $number, 'first_name' => $first, 'last_name' => $last,
            'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $this->program->id,
        ]);
    }

    private function makeClass(string $code, string $subjectCode, string $title, User $teacher): ClassOffering
    {
        $subject = Subject::create(['code' => $subjectCode, 'title' => $title, 'units' => 3]);
        $class = ClassOffering::create([
            'term_id' => $this->term->id, 'subject_id' => $subject->id, 'section' => 'A',
            'class_code' => $code, 'teacher_id' => $teacher->id, 'capacity' => 40,
        ]);
        foreach ([1, 3] as $day) {
            $class->meetings()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '09:30']);
        }

        return $class;
    }

    private function enroll(Student $student, ClassOffering $class, string $status): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id, 'class_offering_id' => $class->id, 'status' => $status,
            'enrolled_at' => $status === 'enrolled' ? '2026-09-10 09:00:00' : null, 'requested_at' => '2026-09-10 09:00:00',
        ]);
    }

    private function sheet(ClassOffering $class, array $statuses, string $date = '2026-11-09'): void
    {
        $session = AttendanceSession::create(['class_offering_id' => $class->id, 'session_date' => $date, 'taken_by' => $class->teacher_id]);
        foreach ($statuses as $studentId => $status) {
            AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $studentId, 'status' => $status, 'recorded_by' => $class->teacher_id]);
        }
    }

    // ---------- Sidebar ----------

    public function test_every_menu_item_has_a_real_icon_and_a_real_page(): void
    {
        $icons = file_get_contents(resource_path('views/components/icon.blade.php'));

        foreach (config('sidebar') as $role => $workspace) {
            foreach ($workspace['items'] as $item) {
                $this->assertTrue(Route::has($item['route']), "{$role}: route {$item['route']} is missing");
                $this->assertNotEmpty($item['icon'] ?? null, "{$role}: {$item['label']} has no icon");
                $this->assertStringContainsString("'{$item['icon']}' =>", $icons, "{$role}: icon {$item['icon']} is not defined");
                $this->assertNotEmpty($item['group'] ?? null, "{$role}: {$item['label']} has no group");
            }
        }
    }

    public function test_the_sidebar_shows_icons_groups_and_the_pin_button(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('class="sidebar-pin"', false)
            ->assertSee('sidebar-group', false)
            ->assertSee('<svg class="icon"', false)
            ->assertSee('Student Records')
            ->assertSee('System Settings')
            ->assertSee('Logout')
            ->assertSee('aria-current="page"', false);
    }

    public function test_each_role_still_sees_only_its_own_menu(): void
    {
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()
            ->assertSee('My Classes')->assertDontSee('System Settings')->assertDontSee('Audit Logs');

        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertOk()
            ->assertSee('Enrollment Requests')->assertDontSee('System Settings');
    }

    public function test_the_page_remembers_a_pinned_menu_before_it_paints(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertSee('sies.sidebar.pinned', false);
    }

    // ---------- Account menu ----------

    public function test_the_account_menu_offers_password_and_sign_out(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Change Password')
            ->assertSee('Sign out')
            ->assertSee(route('account.password'), false);
    }

    // ---------- Search ----------

    public function test_search_needs_a_signed_in_staff_member(): void
    {
        $this->get(route('search', ['q' => 'abc']))->assertRedirect(route('login'));
    }

    public function test_the_registrar_finds_students_by_name_and_number(): void
    {
        $this->makeStudent('2026-0101', 'Maria', 'Santos');
        $this->makeStudent('2026-0102', 'Jose', 'Reyes');

        $this->actingAs($this->registrar)->get(route('search', ['q' => 'maria']))->assertOk()
            ->assertSee('Santos, Maria')->assertDontSee('Reyes, Jose');

        $this->actingAs($this->registrar)->get(route('search', ['q' => '2026-0102']))->assertOk()
            ->assertSee('Reyes, Jose')->assertDontSee('Santos, Maria');

        // Several words must all match.
        $this->actingAs($this->registrar)->get(route('search', ['q' => 'jose reyes']))->assertOk()->assertSee('Reyes, Jose');
        $this->actingAs($this->registrar)->get(route('search', ['q' => 'jose santos']))->assertOk()
            ->assertDontSee('Reyes, Jose')->assertDontSee('Santos, Maria');
    }

    public function test_archived_students_are_not_in_the_results(): void
    {
        $this->makeStudent('2026-0103', 'Ana', 'Archived')->delete();

        $this->actingAs($this->admin)->get(route('search', ['q' => 'archived']))->assertOk()
            ->assertDontSee('Archived, Ana')->assertSee('No students match');
    }

    public function test_classes_are_found_by_code_subject_code_or_title(): void
    {
        foreach (['CL-2002', 'MATH3', 'calculus'] as $q) {
            $this->actingAs($this->admin)->get(route('search', ['q' => $q]))->assertOk()
                ->assertSee('CL-2002')->assertDontSee('CL-1001');
        }
    }

    public function test_a_teacher_searches_only_their_own_classes_and_never_students(): void
    {
        $this->makeStudent('2026-0104', 'Carlo', 'Cruz');

        $this->actingAs($this->teacher)->get(route('search', ['q' => 'CL-']))->assertOk()
            ->assertSee('CL-1001')->assertDontSee('CL-2002');

        $this->actingAs($this->teacher)->get(route('search', ['q' => 'calculus']))->assertOk()
            ->assertDontSee('CL-2002')->assertSee('No classes match');

        $this->actingAs($this->teacher)->get(route('search', ['q' => 'Cruz']))->assertOk()
            ->assertDontSee('Cruz, Carlo')->assertDontSee('>Students<', false);
    }

    public function test_a_very_short_search_does_nothing_and_wildcards_are_not_special(): void
    {
        $this->makeStudent('2026-0105', 'Eli', 'Reyes');

        $this->actingAs($this->admin)->get(route('search', ['q' => 'e']))->assertOk()
            ->assertSee('Type at least two letters')->assertDontSee('Reyes, Eli');

        // A lone percent sign must not match everything.
        $this->actingAs($this->admin)->get(route('search', ['q' => '%%']))->assertOk()
            ->assertDontSee('Reyes, Eli')->assertSee('No students match');
    }

    public function test_search_text_is_escaped(): void
    {
        $this->actingAs($this->admin)->get(route('search', ['q' => '<script>alert(1)</script>']))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_student_role_has_no_search(): void
    {
        $user = $this->makeUser('student');

        $this->actingAs($user)->get(route('search', ['q' => 'abc']))->assertForbidden();
    }

    // ---------- Needs attention ----------

    public function test_a_quiet_dashboard_has_no_attention_panel(): void
    {
        // No pending requests, and every class sheet for today is recorded.
        $this->sheet($this->mine, []);
        $this->sheet($this->theirs, []);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Needs attention');
    }

    public function test_the_attention_panel_lists_pending_enrollments_and_missing_sheets(): void
    {
        $student = $this->makeStudent('2026-0106', 'Pia', 'Lopez');
        $this->enroll($student, $this->mine, 'pending');

        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertOk()
            ->assertSee('Needs attention')
            ->assertSee('enrollment is waiting for review')
            ->assertSee('class sheets are not recorded yet today');
    }

    public function test_frequent_absentees_and_temporary_passwords_are_flagged(): void
    {
        $student = $this->makeStudent('2026-0107', 'Rico', 'Diaz');
        $this->enroll($student, $this->mine, 'enrolled');
        // Three absences are the least that raises an alert.
        $this->sheet($this->mine, [$student->id => 'absent'], '2026-11-02');
        $this->sheet($this->mine, [$student->id => 'absent'], '2026-11-04');
        $this->sheet($this->mine, [$student->id => 'absent']);
        $this->sheet($this->theirs, []);
        $this->makeUser('teacher', ['must_change_password' => true]);

        // The administrator is told about the account they must follow up; absence alerts are in Reports.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('has not changed its temporary password')
            ->assertDontSee('over the absence limit');

        // The registrar monitors absences but does not see the account warning.
        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertOk()
            ->assertSee('over the absence limit')
            ->assertDontSee('temporary password');
    }

    public function test_a_teacher_is_told_about_their_own_missing_sheet_only(): void
    {
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()
            ->assertSee('Needs attention')
            ->assertSee('class has no attendance sheet today');

        $this->sheet($this->mine, []);

        // The other teacher's missing sheet is not this teacher's concern.
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()
            ->assertDontSee('Needs attention');
    }

    public function test_the_admin_is_told_when_no_term_is_current(): void
    {
        Term::query()->update(['is_current' => false]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('No current term is set');
    }

    // ---------- Honest attendance card ----------

    public function test_the_attendance_card_says_when_only_some_sheets_are_recorded(): void
    {
        $student = $this->makeStudent('2026-0108', 'Lia', 'Tan');
        $this->enroll($student, $this->mine, 'enrolled');
        $this->sheet($this->mine, [$student->id => 'present']);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Partial: 1 of 2 sheets recorded');

        $this->sheet($this->theirs, []);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('All 2 sheets recorded');
    }

    public function test_the_attendance_card_says_so_when_nothing_is_recorded(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Nothing recorded yet');
    }

    // ---------- Menu polish ----------

    public function test_change_password_and_logout_are_not_repeated_in_the_menu_list(): void
    {
        foreach (config('sidebar') as $role => $workspace) {
            $this->assertNotContains('account.password', array_column($workspace['items'], 'route'), "{$role} menu repeats Change Password");
        }

        // They remain reachable: the account menu and the foot of the sidebar.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('sidebar-foot', false)->assertSee('Logout')->assertSee('Change Password');
    }

    public function test_the_menu_shows_how_many_enrollments_are_waiting(): void
    {
        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertOk()
            ->assertDontSee('class="nav-badge"', false);

        $this->enroll($this->makeStudent('2026-0109', 'Nina', 'Ocampo'), $this->mine, 'pending');
        $this->enroll($this->makeStudent('2026-0110', 'Omar', 'Pascual'), $this->mine, 'pending');
        $this->enroll($this->makeStudent('2026-0111', 'Quin', 'Ramos'), $this->mine, 'enrolled');

        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertOk()
            ->assertSee('class="nav-badge"', false)->assertSee('2 waiting');
    }

    public function test_teachers_get_no_enrollment_badge(): void
    {
        $this->enroll($this->makeStudent('2026-0112', 'Sol', 'Tan'), $this->mine, 'pending');

        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()
            ->assertDontSee('class="nav-badge"', false);
    }

    public function test_pages_offer_a_skip_to_content_link(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('href="#main-content"', false)->assertSee('id="main-content"', false);
    }

    // ---------- Dashboard improvements ----------

    public function test_the_greeting_follows_the_time_of_day(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('Good morning, Administrator!');

        Carbon::setTestNow('2026-11-09 15:00:00');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('Good afternoon, Administrator!');

        Carbon::setTestNow('2026-11-09 20:00:00');
        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertSee('Good evening, Registrar!');
    }

    public function test_quick_actions_match_the_role(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Quick actions')->assertSee('Add student')->assertSee('New enrollment request')
            ->assertSee(route('students.create'), false);

        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()
            ->assertSee('Quick actions')->assertSee('Open attendance')->assertSee('My classes')
            ->assertDontSee('Add student');
    }

    public function test_repeated_cards_are_gone_and_the_banner_has_no_tagline(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('Pending enrollments')->assertDontSee('Active accounts')
            ->assertDontSee('One organized workspace')
            ->assertSee('Registered students')->assertSee('Enrolled students')->assertSee('Classes this term')->assertSee('Attendance today');
    }

    public function test_cards_show_what_changed_this_week(): void
    {
        $this->makeStudent('2026-0113', 'Una', 'Villar');
        $this->makeStudent('2026-0114', 'Val', 'Wong');

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('+2 this week')->assertSee('class="stat-change"', false);
    }

    public function test_linked_cards_show_a_view_hint(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('class="stat-go"', false)->assertSee('class="attention-go', false);
    }

    public function test_the_attendance_card_shows_a_plain_count_until_every_sheet_is_in(): void
    {
        $student = $this->makeStudent('2026-0115', 'Xia', 'Yap');
        $this->enroll($student, $this->mine, 'enrolled');
        $this->sheet($this->mine, [$student->id => 'present']);

        // One of two sheets: no big percentage, just the facts.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('1 of 1 present')->assertSee('Partial: 1 of 2 sheets recorded');

        $this->sheet($this->theirs, []);

        // Every sheet in: the percentage appears.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('100%')->assertDontSee('1 of 1 present');
    }

    public function test_classes_without_a_sheet_are_listed_first_with_a_button(): void
    {
        $this->sheet($this->mine, []);

        // CL-1001 is done, CL-2002 is not: the open job comes first.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSeeInOrder(['CL-2002', 'Take attendance', 'CL-1001', 'Recorded'])
            ->assertSee('Take attendance');
    }

    public function test_the_registrar_can_monitor_but_not_take_attendance(): void
    {
        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertOk()
            ->assertSee('Not yet')->assertDontSee('Take attendance');
    }

    public function test_doughnut_charts_know_their_centre_label(): void
    {
        // The charts only draw once there is something to show.
        $student = $this->makeStudent('2026-0116', 'Zed', 'Alva');
        $this->enroll($student, $this->mine, 'enrolled');
        $this->sheet($this->mine, [$student->id => 'present']);

        // The registrar's dashboard keeps its charts (the administrator's has none).
        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertOk()
            ->assertSee('centerLabel', false)->assertSee('records', false);
    }
}
