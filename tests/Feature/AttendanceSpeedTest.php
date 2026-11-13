<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\MissingSheets;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Taking attendance faster: a teacher's own entry page, no alerts for empty classes, a friendlier sheet. */
class AttendanceSpeedTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private ClassOffering $class;
    private ClassOffering $empty;
    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 10:00:00');   // a Monday

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $this->term = Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true, 'enrollment_open' => true]);
        $program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        $this->teacher = User::factory()->create();
        $this->teacher->forceFill(['role' => 'teacher', 'is_active' => true, 'must_change_password' => false])->save();

        $make = function (string $code, string $subject, string $room) {
            $s = Subject::create(['code' => $subject, 'title' => $subject . ' title', 'units' => 3]);
            $c = ClassOffering::create(['term_id' => $this->term->id, 'subject_id' => $s->id, 'section' => 'A', 'class_code' => $code, 'teacher_id' => $this->teacher->id, 'capacity' => 40]);
            $c->meetings()->create(['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => $room]);

            return $c;
        };
        $this->class = $make('CL-1', 'CS101', 'R1');
        $this->empty = $make('CL-2', 'HUM101', 'R2');

        $ana = Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $program->id, 'year_level' => 1, 'status' => 'active']);
        Enrollment::create(['student_id' => $ana->id, 'class_offering_id' => $this->class->id, 'status' => 'enrolled', 'requested_at' => '2026-08-05', 'enrolled_at' => '2026-08-05']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_class_nobody_joined_is_left_out_of_the_missed_sheet_alerts(): void
    {
        $classes = ClassOffering::with(['term', 'meetings'])->get();
        $found = MissingSheets::find($classes, today()->subDays(14));

        $this->assertTrue($found->contains(fn ($row) => $row['class']->id === $this->class->id));
        $this->assertFalse($found->contains(fn ($row) => $row['class']->id === $this->empty->id));
    }

    public function test_the_teacher_attendance_page_is_where_attendance_is_taken(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Take attendance · today')
            ->assertSee('Still to record')
            // Oct 26 is past the 7-day teacher window: named, but not offered as a link the teacher cannot use.
            ->assertSee('can only be recorded by an administrator')
            ->assertDontSee('date=2026-10-26', false);

        // Only the class with students is offered (the one nobody joined has no sheet to take).
        $this->assertSame(1, $response->viewData('take')['today']['expected']);
        $this->assertTrue($response->viewData('take')['missed']->isNotEmpty());
    }

    public function test_an_empty_class_sheet_says_what_to_do_and_a_teacher_goes_back_to_my_classes(): void
    {
        $this->actingAs($this->teacher)->get(route('attendance.sheet', ['class_offering' => $this->empty->id, 'date' => '2026-11-09']))
            ->assertOk()
            ->assertSee('nothing to record')
            ->assertSee('Back to my classes')
            ->assertDontSee('Everyone starts as Present');
    }

    public function test_the_sheet_has_compact_rows_quick_buttons_and_a_clear_remark_hint(): void
    {
        $this->actingAs($this->teacher)->get(route('attendance.sheet', ['class_offering' => $this->class->id, 'date' => '2026-11-09']))
            ->assertOk()
            ->assertSee('data-student-row', false)
            ->assertSee('Mark everyone present')
            ->assertSee('Mark everyone absent')
            ->assertSee('placeholder="Reason"', false);
    }
}
