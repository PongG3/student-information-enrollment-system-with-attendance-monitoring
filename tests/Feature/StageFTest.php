<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\NonClassDay;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\AttendanceStats;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageFTest extends TestCase
{
    use RefreshDatabase;

    // "Today" is Monday 9 November 2026. The class meets on Mondays and Wednesdays.
    private const TODAY = '2026-11-09';

    private Term $term;
    private Program $program;
    private User $teacher;
    private User $otherTeacher;
    private User $admin;
    private User $registrar;
    private ClassOffering $class;
    private Student $s1;
    private Student $s2;
    private Student $s3;

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

        $this->s1 = $this->makeStudent('2026-0001');
        $this->s2 = $this->makeStudent('2026-0002');
        $this->s3 = $this->makeStudent('2026-0003');
        foreach ([$this->s1, $this->s2, $this->s3] as $student) {
            $this->enroll($student, $this->class, 'enrolled', '2026-08-10 09:00:00');
        }
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

    private function enroll(Student $student, ClassOffering $class, string $status, ?string $enrolledAt = null): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id, 'class_offering_id' => $class->id, 'status' => $status, 'enrolled_at' => $enrolledAt,
        ]);
    }

    private function saveSheet(User $user, string $date, array $statuses, array $extra = [], ?ClassOffering $class = null)
    {
        $rows = [];
        foreach ($statuses as $studentId => $status) {
            $rows[$studentId] = is_array($status) ? $status : ['status' => $status];
        }

        return $this->actingAs($user)->post('/attendance/sheet', array_merge([
            'class_offering' => ($class ?? $this->class)->id,
            'date' => $date,
            'rows' => $rows,
        ], $extra));
    }

    private function allPresent(): array
    {
        return [$this->s1->id => 'present', $this->s2->id => 'present', $this->s3->id => 'present'];
    }

    // ---------- The sheet ----------

    public function test_only_officially_enrolled_students_are_on_the_sheet(): void
    {
        $this->enroll($this->makeStudent('2026-0004'), $this->class, 'pending');
        $this->enroll($this->makeStudent('2026-0005'), $this->class, 'approved');

        $this->actingAs($this->teacher)->get(route('attendance.sheet', ['class_offering' => $this->class->id, 'date' => self::TODAY]))
            ->assertOk()->assertSee('2026-0001')->assertSee('2026-0003')
            ->assertDontSee('2026-0004')->assertDontSee('2026-0005');
    }

    public function test_the_sheet_opens_on_the_latest_class_day(): void
    {
        $this->actingAs($this->teacher)->get(route('attendance.sheet', ['class_offering' => $this->class->id]))
            ->assertRedirect(route('attendance.sheet', ['class_offering' => $this->class->id, 'date' => self::TODAY]));
    }

    public function test_a_student_enrolled_later_is_not_on_earlier_sheets(): void
    {
        $late = $this->makeStudent('2026-0006');
        $this->enroll($late, $this->class, 'enrolled', '2026-11-05 09:00:00');

        $this->actingAs($this->teacher)->get(route('attendance.sheet', ['class_offering' => $this->class->id, 'date' => '2026-11-04']))
            ->assertDontSee('2026-0006');
        $this->actingAs($this->teacher)->get(route('attendance.sheet', ['class_offering' => $this->class->id, 'date' => self::TODAY]))
            ->assertSee('2026-0006');
    }

    public function test_a_teacher_saves_attendance_and_it_is_logged(): void
    {
        $this->saveSheet($this->teacher, self::TODAY, [
            $this->s1->id => 'present', $this->s2->id => 'absent', $this->s3->id => 'late',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $session = AttendanceSession::firstOrFail();
        $this->assertEquals($this->teacher->id, $session->taken_by);
        $this->assertSame(3, $session->records()->count());
        $this->assertSame('absent', AttendanceRecord::where('student_id', $this->s2->id)->value('status'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.recorded']);
    }

    public function test_saving_again_updates_the_same_sheet_and_logs_the_correction(): void
    {
        $this->saveSheet($this->teacher, self::TODAY, $this->allPresent());
        $this->saveSheet($this->teacher, self::TODAY, [
            $this->s1->id => 'present', $this->s2->id => 'late', $this->s3->id => 'present',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, AttendanceSession::count());
        $this->assertSame(3, AttendanceRecord::count());
        $this->assertSame('late', AttendanceRecord::where('student_id', $this->s2->id)->value('status'));
        $this->assertEquals($this->teacher->id, AttendanceRecord::where('student_id', $this->s2->id)->value('updated_by'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.corrected']);
    }

    public function test_every_student_needs_a_status(): void
    {
        $this->saveSheet($this->teacher, self::TODAY, [$this->s1->id => 'present', $this->s2->id => 'present'])
            ->assertSessionHasErrors('sheet');

        $this->assertSame(0, AttendanceSession::count());
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_unknown_statuses_are_rejected(): void
    {
        $this->saveSheet($this->teacher, self::TODAY, [
            $this->s1->id => 'present', $this->s2->id => 'sleeping', $this->s3->id => 'present',
        ])->assertSessionHasErrors('rows.' . $this->s2->id . '.status');
    }

    public function test_excused_needs_a_remark(): void
    {
        $this->saveSheet($this->teacher, self::TODAY, [
            $this->s1->id => 'present', $this->s2->id => 'excused', $this->s3->id => 'present',
        ])->assertSessionHasErrors('sheet');

        $this->saveSheet($this->teacher, self::TODAY, [
            $this->s1->id => 'present', $this->s2->id => ['status' => 'excused', 'remarks' => 'Medical certificate'], $this->s3->id => 'present',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Medical certificate', AttendanceRecord::where('student_id', $this->s2->id)->value('remarks'));
    }

    public function test_students_who_are_not_on_the_sheet_cannot_be_added(): void
    {
        $outsider = $this->makeStudent('2026-0009');
        $this->enroll($outsider, $this->class, 'pending');

        $rows = $this->allPresent() + [$outsider->id => 'present'];

        $this->saveSheet($this->teacher, self::TODAY, $rows)->assertSessionHasErrors('sheet');

        $this->assertSame(0, AttendanceRecord::where('student_id', $outsider->id)->count());
    }

    // ---------- Date rules ----------

    public function test_dates_that_cannot_have_attendance_are_refused(): void
    {
        NonClassDay::create(['date' => '2026-11-09', 'description' => 'Class suspension']);

        $cases = [
            '2026-11-09' => 'a holiday',
            '2026-11-11' => 'a future day',
            '2026-11-03' => 'a day the class does not meet (Tuesday)',
            '2026-07-27' => 'before the term',
        ];

        foreach ($cases as $date => $why) {
            $this->saveSheet($this->admin, $date, $this->allPresent(), ['reason' => 'Trying a blocked date'])
                ->assertSessionHasErrors('sheet');
        }

        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_a_closed_term_locks_attendance_for_everyone(): void
    {
        $this->term->update(['closed_at' => now(), 'is_current' => false]);

        $this->saveSheet($this->admin, self::TODAY, $this->allPresent(), ['reason' => 'Trying after closing'])
            ->assertSessionHasErrors('sheet');

        $this->assertSame(0, AttendanceSession::count());
    }

    // ---------- Who may do what ----------

    public function test_another_teacher_cannot_see_or_change_the_class(): void
    {
        $this->actingAs($this->otherTeacher)
            ->get(route('attendance.sheet', ['class_offering' => $this->class->id, 'date' => self::TODAY]))->assertForbidden();
        $this->saveSheet($this->otherTeacher, self::TODAY, $this->allPresent())->assertForbidden();
        $this->actingAs($this->otherTeacher)->get(route('attendance.summary', $this->class))->assertForbidden();

        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_the_registrar_can_look_but_not_change(): void
    {
        $this->saveSheet($this->teacher, self::TODAY, $this->allPresent());

        $this->actingAs($this->registrar)
            ->get(route('attendance.sheet', ['class_offering' => $this->class->id, 'date' => self::TODAY]))
            ->assertOk()->assertSee('read-only');
        $this->actingAs($this->registrar)->get('/attendance')->assertOk();
        $this->actingAs($this->registrar)->get(route('attendance.summary', $this->class))->assertOk();

        $this->saveSheet($this->registrar, self::TODAY, [
            $this->s1->id => 'absent', $this->s2->id => 'absent', $this->s3->id => 'absent',
        ])->assertForbidden();

        $this->assertSame(0, AttendanceRecord::where('status', 'absent')->count());
    }

    public function test_students_cannot_open_attendance_pages(): void
    {
        $student = $this->makeUser('student');

        $this->actingAs($student)->get('/attendance')->assertForbidden();
        $this->actingAs($student)->get(route('attendance.sheet', ['class_offering' => $this->class->id]))->assertForbidden();
    }

    public function test_teachers_can_edit_only_inside_the_edit_window(): void
    {
        // Wednesday 4 November is five days ago: inside the 7-day window.
        $this->saveSheet($this->teacher, '2026-11-04', $this->allPresent())->assertSessionHasNoErrors();
        $this->assertSame(1, AttendanceSession::count());

        // Wednesday 28 October is twelve days ago: outside the window.
        $this->saveSheet($this->teacher, '2026-10-28', $this->allPresent())->assertSessionHasErrors('sheet');
        $this->assertSame(1, AttendanceSession::count());

        $this->actingAs($this->teacher)
            ->get(route('attendance.sheet', ['class_offering' => $this->class->id, 'date' => '2026-10-28']))
            ->assertOk()->assertSee('administrator');
    }

    public function test_the_edit_window_is_a_setting(): void
    {
        Setting::put('attendance_edit_window_days', '20');

        $this->saveSheet($this->teacher, '2026-10-28', $this->allPresent())->assertSessionHasNoErrors();

        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_an_administrator_can_correct_old_sheets_but_must_give_a_reason(): void
    {
        $this->saveSheet($this->admin, '2026-10-28', $this->allPresent())->assertSessionHasErrors('reason');
        $this->assertSame(0, AttendanceSession::count());

        $this->saveSheet($this->admin, '2026-10-28', $this->allPresent(), ['reason' => 'Teacher was on leave'])->assertSessionHasNoErrors();
        $this->assertSame(1, AttendanceSession::count());

        $this->saveSheet($this->admin, '2026-10-28', [
            $this->s1->id => 'present', $this->s2->id => 'absent', $this->s3->id => 'present',
        ], ['reason' => 'Corrected after checking the logbook'])->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'attendance.corrected')->firstOrFail();
        $this->assertSame('Corrected after checking the logbook', $log->new_values['reason']);
        $this->assertSame('absent', AttendanceRecord::where('student_id', $this->s2->id)->value('status'));
    }

    // ---------- Percentages and alerts ----------

    public function test_the_percentage_follows_the_attendance_policy(): void
    {
        $counts = ['present' => 6, 'late' => 2, 'absent' => 1, 'excused' => 1];

        // Default: late counts as attended, excused is left out.
        $this->assertSame(88.9, AttendanceStats::percentage($counts));

        Setting::put('late_counts_as_attended', '0');
        $this->assertSame(66.7, AttendanceStats::percentage($counts));

        Setting::put('late_counts_as_attended', '1');
        Setting::put('excused_in_percentage', 'attended');
        $this->assertSame(90.0, AttendanceStats::percentage($counts));

        // With excused left out and nothing else recorded, there is no percentage yet.
        Setting::put('excused_in_percentage', 'excluded');
        $this->assertNull(AttendanceStats::percentage(['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 2]));
    }

    public function test_frequent_absence_needs_enough_meetings_and_a_high_enough_rate(): void
    {
        $this->assertTrue(AttendanceStats::isFlagged(['present' => 2, 'late' => 0, 'absent' => 1, 'excused' => 0]));
        $this->assertFalse(AttendanceStats::isFlagged(['present' => 0, 'late' => 0, 'absent' => 1, 'excused' => 0]));
        $this->assertFalse(AttendanceStats::isFlagged(['present' => 4, 'late' => 0, 'absent' => 1, 'excused' => 0]));
    }

    public function test_flagged_students_appear_on_the_monitoring_page(): void
    {
        foreach (['2026-11-02', '2026-11-04', '2026-11-09'] as $date) {
            $this->saveSheet($this->teacher, $date, [
                $this->s1->id => 'present', $this->s2->id => 'absent', $this->s3->id => 'present',
            ])->assertSessionHasNoErrors();
        }

        $flagged = AttendanceStats::flagged($this->term);
        $this->assertCount(1, $flagged);
        $this->assertEquals($this->s2->id, $flagged->first()['student']->id);

        $this->actingAs($this->registrar)->get('/attendance?range=month')->assertOk()->assertSee('2026-0002');
    }

    // ---------- Dashboard and summary ----------

    public function test_the_dashboard_is_limited_to_the_teachers_own_classes(): void
    {
        $other = $this->makeClass('CL-2001', 'CS201', $this->otherTeacher);
        $stranger = $this->makeStudent('2026-0007');
        $this->enroll($stranger, $other, 'enrolled', '2026-08-10 09:00:00');

        $this->saveSheet($this->teacher, self::TODAY, $this->allPresent());
        $this->saveSheet($this->otherTeacher, self::TODAY, [$stranger->id => 'absent'], [], $other);

        $this->actingAs($this->teacher)->get('/attendance?range=month')
            ->assertOk()->assertSee('CL-1001')->assertDontSee('CL-2001');
        $this->actingAs($this->registrar)->get('/attendance?range=month')
            ->assertOk()->assertSee('CL-1001')->assertSee('CL-2001');
    }

    public function test_the_class_summary_shows_each_students_percentage(): void
    {
        $this->saveSheet($this->teacher, '2026-11-04', [
            $this->s1->id => 'present', $this->s2->id => 'absent', $this->s3->id => 'late',
        ]);
        $this->saveSheet($this->teacher, self::TODAY, $this->allPresent());

        $this->actingAs($this->teacher)->get(route('attendance.summary', $this->class))
            ->assertOk()->assertSee('2026-0002')->assertSee('50%')->assertSee('100%');
    }
}
