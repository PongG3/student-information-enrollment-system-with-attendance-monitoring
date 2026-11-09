<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\NonClassDay;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\DashboardStats;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageGTest extends TestCase
{
    use RefreshDatabase;

    // "Today" is Monday 9 November 2026. The class meets on Mondays and Wednesdays.
    private Term $term;
    private Program $program;
    private User $teacher;
    private User $otherTeacher;
    private User $admin;
    private User $registrar;
    private ClassOffering $class;
    private Student $s1;
    private Student $s2;

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
        $this->teacher = $this->makeUser('teacher');
        $this->otherTeacher = $this->makeUser('teacher');
        $this->admin = $this->makeUser('admin');
        $this->registrar = $this->makeUser('registrar');

        $this->class = $this->makeClass('CL-1001', 'CS101', $this->teacher);
        $this->s1 = $this->makeStudent('2026-0001');
        $this->s2 = $this->makeStudent('2026-0002');
        $this->enroll($this->s1, $this->class, 'enrolled', '2026-09-10 09:00:00');
        $this->enroll($this->s2, $this->class, 'enrolled', '2026-10-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

        return $user;
    }

    private function makeStudent(string $number): Student
    {
        return Student::create([
            'student_number' => $number, 'first_name' => 'Student' . substr($number, -1), 'last_name' => 'Tester',
            'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $this->program->id,
        ]);
    }

    private function makeClass(string $code, string $subjectCode, User $teacher): ClassOffering
    {
        $subject = Subject::create(['code' => $subjectCode, 'title' => $subjectCode . ' title', 'units' => 3]);
        $class = ClassOffering::create([
            'term_id' => $this->term->id, 'subject_id' => $subject->id, 'section' => 'A',
            'class_code' => $code, 'teacher_id' => $teacher->id, 'capacity' => 40,
        ]);
        foreach ([1, 3] as $day) {
            $class->meetings()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '09:30']);
        }

        return $class;
    }

    private function enroll(Student $student, ClassOffering $class, string $status, ?string $enrolledAt = null, ?string $requestedAt = null): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id, 'class_offering_id' => $class->id, 'status' => $status,
            'enrolled_at' => $enrolledAt, 'requested_at' => $requestedAt ?? $enrolledAt,
        ]);
    }

    private function sheet(ClassOffering $class, string $date, array $statuses): void
    {
        $session = AttendanceSession::create(['class_offering_id' => $class->id, 'session_date' => $date, 'taken_by' => $class->teacher_id]);

        foreach ($statuses as $studentId => $status) {
            AttendanceRecord::create([
                'attendance_session_id' => $session->id, 'student_id' => $studentId,
                'status' => $status, 'recorded_by' => $class->teacher_id,
            ]);
        }
    }

    // ---------- The numbers ----------

    public function test_overview_counts_come_from_the_database(): void
    {
        $this->enroll($this->makeStudent('2026-0003'), $this->class, 'pending');
        $archived = $this->makeStudent('2026-0004');
        $archived->delete();
        $this->makeUser('teacher')->forceFill(['is_active' => false])->save();

        $overview = DashboardStats::overview($this->term);

        $this->assertSame(3, $overview['students']);   // archived student not counted
        $this->assertSame(2, $overview['enrolled']);
        $this->assertSame(1, $overview['pending']);
        $this->assertSame(2, $overview['teachers']);   // inactive teacher not counted
        $this->assertSame(1, $overview['classes']);
        $this->assertSame(1, $overview['subjects']);
    }

    public function test_todays_attendance_counts_and_expected_sheets(): void
    {
        $this->sheet($this->class, '2026-11-09', [$this->s1->id => 'present', $this->s2->id => 'absent']);
        $second = $this->makeClass('CL-1002', 'CS102', $this->otherTeacher);

        $today = DashboardStats::today($this->term);

        $this->assertSame(1, $today['counts']['present']);
        $this->assertSame(1, $today['counts']['absent']);
        $this->assertSame(2, $today['expected']);
        $this->assertSame(1, $today['recorded']);
        $this->assertEquals(50.0, $today['percentage']);

        $mine = DashboardStats::today($this->term, $this->teacher->id);
        $this->assertSame(1, $mine['expected']);
        $this->assertSame(1, $mine['recorded']);

        $theirs = DashboardStats::today($this->term, $this->otherTeacher->id);
        $this->assertSame(1, $theirs['expected']);
        $this->assertSame(0, $theirs['recorded']);
    }

    public function test_a_holiday_means_no_sheets_are_expected(): void
    {
        NonClassDay::create(['date' => '2026-11-09', 'description' => 'Class suspension']);

        $today = DashboardStats::today($this->term);

        $this->assertSame(0, $today['expected']);
        $this->assertSame('Class suspension', $today['holiday']);
        $this->assertStringContainsString('Class suspension', $today['note']);
    }

    public function test_weekly_attendance_trend(): void
    {
        $this->sheet($this->class, '2026-11-02', [$this->s1->id => 'present', $this->s2->id => 'absent']);
        $this->sheet($this->class, '2026-11-04', [$this->s1->id => 'present', $this->s2->id => 'present']);
        $this->sheet($this->class, '2026-11-09', [$this->s1->id => 'present', $this->s2->id => 'present']);

        $trend = DashboardStats::attendanceTrend($this->term);

        $this->assertTrue($trend['hasData']);
        $this->assertSame(['Nov 2', 'Nov 9'], array_slice($trend['labels'], -2));
        $this->assertEquals([75.0, 100.0], array_slice($trend['values'], -2));
        $this->assertNull($trend['values'][0]);   // no sheets in the earliest weeks
    }

    public function test_trend_is_limited_to_the_teachers_own_classes(): void
    {
        $other = $this->makeClass('CL-2001', 'CS201', $this->otherTeacher);
        $stranger = $this->makeStudent('2026-0007');
        $this->enroll($stranger, $other, 'enrolled', '2026-09-10 09:00:00');

        $this->sheet($this->class, '2026-11-09', [$this->s1->id => 'present', $this->s2->id => 'present']);
        $this->sheet($other, '2026-11-09', [$stranger->id => 'absent']);

        $mine = DashboardStats::attendanceTrend($this->term, $this->teacher->id);
        $all = DashboardStats::attendanceTrend($this->term);

        $this->assertEquals(100.0, end($mine['values']));
        $this->assertEquals(66.7, end($all['values']));
        $this->assertSame(2, DashboardStats::termCounts($this->term, $this->teacher->id)['present']);
        $this->assertSame(1, DashboardStats::termCounts($this->term, $this->otherTeacher->id)['absent']);
    }

    public function test_monthly_enrollment_trend(): void
    {
        $this->enroll($this->makeStudent('2026-0003'), $this->class, 'pending', null, '2026-09-15 09:00:00');

        $trend = DashboardStats::enrollmentTrend($this->term);

        $this->assertTrue($trend['hasData']);
        $this->assertSame('Aug', $trend['labels'][0]);
        $this->assertCount(10, $trend['labels']);   // August to May

        $sep = array_search('Sep', $trend['labels']);
        $oct = array_search('Oct', $trend['labels']);
        $this->assertSame(2, $trend['requests'][$sep]);   // s1 enrolled in Sept + the pending request
        $this->assertSame(1, $trend['enrolled'][$sep]);
        $this->assertSame(1, $trend['requests'][$oct]);
        $this->assertSame(1, $trend['enrolled'][$oct]);
    }

    public function test_status_and_program_breakdowns(): void
    {
        $this->enroll($this->makeStudent('2026-0003'), $this->class, 'pending');
        Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);

        $status = DashboardStats::enrollmentStatus($this->term);
        $this->assertSame(['Pending', 'Approved', 'Enrolled', 'Rejected', 'Cancelled'], $status['labels']);
        $this->assertSame([1, 0, 2, 0, 0], $status['values']);

        $programs = DashboardStats::byProgram();
        $this->assertSame(['BSCS', 'BSIT'], $programs['labels']);
        $this->assertSame([3, 0], $programs['values']);
    }

    public function test_an_empty_system_still_works(): void
    {
        Term::query()->update(['is_current' => false]);

        $this->assertSame(0, DashboardStats::overview(null)['enrolled']);
        $this->assertFalse(DashboardStats::attendanceTrend(null)['hasData']);
        $this->assertNotNull(DashboardStats::today(null)['note']);
    }

    // ---------- The pages ----------

    public function test_the_admin_dashboard_shows_cards_charts_and_activity(): void
    {
        $this->sheet($this->class, '2026-11-09', [$this->s1->id => 'present', $this->s2->id => 'absent']);

        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Registered students')->assertSee('Enrolled students')->assertSee('Classes this term')
            ->assertSee("Today's attendance")->assertSee('Monthly enrollment trend')
            ->assertSee('Weekly attendance trend')->assertSee('Students by program')
            ->assertSee('Recent activity')
            ->assertSee('data-chart', false);
    }

    public function test_the_registrar_sees_enrollment_activity_not_the_audit_log(): void
    {
        $this->enroll($this->makeStudent('2026-0003'), $this->class, 'pending');

        $this->actingAs($this->registrar)->get('/registrar/dashboard')
            ->assertOk()->assertSee('Recent enrollment activity')->assertSee('Tester, Student3')
            ->assertDontSee('Recent activity');
    }

    public function test_the_teacher_dashboard_is_about_their_own_classes(): void
    {
        $this->makeClass('CL-2001', 'CS201', $this->otherTeacher);

        $this->actingAs($this->teacher)->get('/teacher/dashboard')
            ->assertOk()
            ->assertSee("Today's classes")->assertSee('CL-1001')->assertSee('Take attendance')
            ->assertSee('Frequent absences in my classes')
            ->assertDontSee('CL-2001')->assertDontSee('Registered students');
    }

    public function test_a_recorded_class_offers_to_open_the_sheet(): void
    {
        $this->sheet($this->class, '2026-11-09', [$this->s1->id => 'present', $this->s2->id => 'present']);

        $this->actingAs($this->teacher)->get('/teacher/dashboard')
            ->assertOk()->assertSee('Recorded')->assertDontSee('Take attendance');
    }

    public function test_dashboards_load_when_there_is_no_current_term(): void
    {
        Term::query()->update(['is_current' => false]);

        foreach (['admin' => $this->admin, 'registrar' => $this->registrar, 'teacher' => $this->teacher] as $role => $user) {
            $this->actingAs($user)->get("/{$role}/dashboard")->assertOk();
        }
    }
}
