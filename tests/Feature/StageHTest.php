<?php

namespace Tests\Feature;

use App\Exports\ReportExport;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageHTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-09';

    private Term $term;
    private Program $program;
    private User $teacher;
    private User $otherTeacher;
    private User $admin;
    private User $registrar;
    private ClassOffering $class;
    private ClassOffering $otherClass;
    private Student $s1;
    private Student $s2;
    private Student $s3;
    private Student $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::TODAY . ' 10:00:00');

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $this->term = Term::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester',
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $this->program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        $this->teacher = $this->makeUser('teacher');
        $this->otherTeacher = $this->makeUser('teacher');
        $this->admin = $this->makeUser('admin');
        $this->registrar = $this->makeUser('registrar');

        $this->class = $this->makeClass('CL-1001', 'CS101', $this->teacher);
        $this->otherClass = $this->makeClass('CL-2002', 'CS202', $this->otherTeacher);

        $this->s1 = $this->makeStudent('2026-0001');
        $this->s2 = $this->makeStudent('2026-0002');
        $this->s3 = $this->makeStudent('2026-0003');
        $this->outsider = $this->makeStudent('2026-0009');

        foreach ([$this->s1, $this->s2, $this->s3] as $student) {
            $this->enroll($student, $this->class);
        }
        $this->enroll($this->outsider, $this->otherClass);

        // Monday 9 Nov: s1 present, s2 absent, s3 late. Wednesday 4 Nov: s1 and s2 absent.
        $this->sheet($this->teacher, $this->class, '2026-11-09', [$this->s1->id => 'present', $this->s2->id => 'absent', $this->s3->id => 'late']);
        $this->sheet($this->teacher, $this->class, '2026-11-04', [$this->s1->id => 'absent', $this->s2->id => 'absent', $this->s3->id => 'present']);
        $this->sheet($this->otherTeacher, $this->otherClass, '2026-11-09', [$this->outsider->id => 'absent']);
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

    private function enroll(Student $student, ClassOffering $class): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id, 'class_offering_id' => $class->id,
            'status' => 'enrolled', 'enrolled_at' => '2026-08-10 09:00:00',
        ]);
    }

    private function sheet(User $user, ClassOffering $class, string $date, array $statuses): void
    {
        $rows = [];
        foreach ($statuses as $studentId => $status) {
            $rows[$studentId] = ['status' => $status];
        }

        $this->actingAs($user)->post('/attendance/sheet', [
            'class_offering' => $class->id, 'date' => $date, 'rows' => $rows,
        ])->assertSessionHasNoErrors();
    }

    // ---------- Access ----------

    public function test_guests_are_sent_to_sign_in(): void
    {
        // setUp signed in teachers to save sheets; forget them so this request is really a guest.
        $this->app['auth']->forgetGuards();

        $this->get('/reports')->assertRedirect('/login');
        $this->get('/reports/absentees')->assertRedirect('/login');
    }

    public function test_students_cannot_open_reports(): void
    {
        $student = $this->makeUser('student');

        $this->actingAs($student)->get('/reports')->assertForbidden();
        $this->actingAs($student)->get('/reports/absentees/pdf')->assertForbidden();
    }

    public function test_each_role_sees_only_its_reports_in_the_list(): void
    {
        $this->actingAs($this->admin)->get('/reports')->assertOk()
            ->assertSee('Student master list')->assertSee('Enrollment report')->assertSee('Absentee report');

        $this->actingAs($this->registrar)->get('/reports')->assertOk()->assertSee('Student master list');

        $this->actingAs($this->teacher)->get('/reports')->assertOk()
            ->assertSee('Absentee report')->assertSee('Class attendance summary')
            ->assertDontSee('Student master list')->assertDontSee('Enrollment report');
    }

    public function test_a_teacher_cannot_open_a_report_meant_for_staff(): void
    {
        $this->actingAs($this->teacher)->get('/reports/student-master-list')->assertForbidden();
        $this->actingAs($this->teacher)->get('/reports/enrollment/excel')->assertForbidden();
    }

    public function test_an_unknown_report_is_not_found(): void
    {
        $this->actingAs($this->admin)->get('/reports/payroll')->assertNotFound();
    }

    // ---------- Contents ----------

    public function test_every_report_opens_for_admin_without_error(): void
    {
        foreach (array_keys(\App\Services\ReportService::CATALOG) as $slug) {
            $url = $slug === 'student-history' ? "/reports/{$slug}?student=2026-0001" : "/reports/{$slug}?date=" . self::TODAY . '&month=2026-11';
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_the_daily_report_lists_the_days_marks(): void
    {
        $this->actingAs($this->admin)->get('/reports/attendance-daily?date=2026-11-09')->assertOk()
            ->assertSee('2026-0001')->assertSee('2026-0002')->assertSee('Absent')->assertSee('Late');
    }

    public function test_a_teacher_only_sees_their_own_classes(): void
    {
        $this->actingAs($this->teacher)->get('/reports/attendance-daily?date=2026-11-09')->assertOk()
            ->assertSee('CL-1001')->assertDontSee('CL-2002')->assertDontSee('2026-0009');

        $this->actingAs($this->teacher)->get('/reports/class-summary')->assertOk()
            ->assertSee('CL-1001')->assertDontSee('CL-2002');
    }

    public function test_a_teacher_cannot_see_another_teachers_class_even_by_asking_for_it(): void
    {
        $this->actingAs($this->teacher)
            ->get('/reports/attendance-daily?date=2026-11-09&class_offering=' . $this->otherClass->id)
            ->assertOk()->assertDontSee('2026-0009');
    }

    public function test_absentees_are_listed_worst_first(): void
    {
        $this->actingAs($this->admin)->get('/reports/absentees')->assertOk()
            ->assertSeeInOrder(['2026-0002', '2026-0001']);
    }

    public function test_the_late_report_lists_late_students(): void
    {
        $this->actingAs($this->admin)->get('/reports/late')->assertOk()->assertSee('2026-0003');
    }

    public function test_student_history_needs_a_known_student_id(): void
    {
        $this->actingAs($this->admin)->get('/reports/student-history?student=')->assertOk()->assertSee('Enter a student ID.');
        $this->actingAs($this->admin)->get('/reports/student-history?student=9999-9999')->assertOk()->assertSee('No student has that ID.');
        $this->actingAs($this->admin)->get('/reports/student-history?student=2026-0001')->assertOk()->assertSee('2026-0001');
    }

    public function test_bad_filters_are_refused(): void
    {
        $this->actingAs($this->admin)->get('/reports/attendance-daily?date=not-a-date')->assertSessionHasErrors('date');
        $this->actingAs($this->admin)->get('/reports/attendance-monthly?month=2026-13')->assertSessionHasErrors('month');
        $this->actingAs($this->admin)->get('/reports/absentees?from=2026-11-09&to=2026-11-01')->assertSessionHasErrors('to');
    }

    public function test_archived_students_only_appear_when_asked_for(): void
    {
        $this->s3->delete();

        $this->actingAs($this->registrar)->get('/reports/student-master-list')->assertOk()
            ->assertSee('2026-0001')->assertDontSee('2026-0003');

        $this->actingAs($this->registrar)->get('/reports/student-master-list?archived=1')->assertOk()
            ->assertSee('2026-0003')->assertSee('Archived');
    }

    // ---------- Print, PDF, Excel ----------

    public function test_print_view_has_the_title_and_rows(): void
    {
        $this->actingAs($this->admin)->get('/reports/attendance-daily/print?date=2026-11-09')->assertOk()
            ->assertSee('Daily attendance report')->assertSee('2026-0002')->assertSee('window.print');
    }

    public function test_pdf_downloads_as_a_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports/absentees/pdf');

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('absentees-2026-11-09.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_excel_downloads_as_a_workbook(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports/absentees/excel');

        $response->assertOk();
        $this->assertStringContainsString('absentees-2026-11-09.xlsx', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }

    public function test_a_teacher_can_export_their_own_report(): void
    {
        $this->actingAs($this->teacher)->get('/reports/absentees/pdf')->assertOk();
        $this->actingAs($this->teacher)->get('/reports/absentees/excel')->assertOk();
    }

    public function test_taking_a_copy_is_written_to_the_audit_log_but_viewing_is_not(): void
    {
        $this->actingAs($this->admin)->get('/reports/absentees')->assertOk();
        $this->assertSame(0, AuditLog::where('action', 'report.exported')->count());

        $this->actingAs($this->admin)->get('/reports/absentees/print')->assertOk();
        $this->actingAs($this->admin)->get('/reports/absentees/pdf')->assertOk();
        $this->actingAs($this->admin)->get('/reports/absentees/excel')->assertOk();

        $this->assertSame(3, AuditLog::where('action', 'report.exported')->count());
        $this->assertStringContainsString('Absentee report', AuditLog::where('action', 'report.exported')->latest('id')->first()->description);
    }

    // ---------- Spreadsheet safety ----------

    public function test_text_that_looks_like_a_formula_is_neutralised(): void
    {
        $this->assertSame("'=1+1", ReportExport::safe('=1+1'));
        $this->assertSame("'+cmd", ReportExport::safe('+cmd'));
        $this->assertSame("'-5x", ReportExport::safe('-5x'));
        $this->assertSame("'@SUM(A1)", ReportExport::safe('@SUM(A1)'));
        $this->assertSame('Santos, Ana', ReportExport::safe('Santos, Ana'));
        $this->assertSame(5, ReportExport::safe(5));
    }
}
