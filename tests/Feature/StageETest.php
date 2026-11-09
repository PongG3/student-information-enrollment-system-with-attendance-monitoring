<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageETest extends TestCase
{
    use RefreshDatabase;

    private Term $term;
    private Program $program;
    private User $teacher;
    private User $registrar;
    private Requirement $requirement;

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $this->term = Term::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester',
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true, 'enrollment_open' => true,
        ]);
        $this->program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        $this->teacher = $this->makeUser('teacher');
        $this->registrar = $this->makeUser('registrar');
        $this->requirement = Requirement::create(['name' => 'PSA Birth Certificate', 'is_required' => true, 'is_active' => true]);
        Setting::put('max_units_per_term', '24');
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

        return $user;
    }

    private function makeStudent(string $number, bool $verified = true): Student
    {
        $student = Student::create([
            'student_number' => $number, 'first_name' => 'Student' . substr($number, -1), 'last_name' => 'Tester',
            'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $this->program->id,
        ]);

        if ($verified) {
            StudentRequirement::create([
                'student_id' => $student->id, 'requirement_id' => $this->requirement->id,
                'status' => 'verified', 'verified_by' => $this->registrar->id, 'verified_at' => now(),
            ]);
        }

        return $student;
    }

    private function makeSubject(string $code, float $units = 3): Subject
    {
        return Subject::create(['code' => $code, 'title' => $code . ' title', 'units' => $units]);
    }

    private function makeClass(string $code, Subject $subject, array $meeting = [1, '08:00', '09:30'], int $capacity = 30, string $section = 'A'): ClassOffering
    {
        $class = ClassOffering::create([
            'term_id' => $this->term->id, 'subject_id' => $subject->id, 'section' => $section,
            'class_code' => $code, 'teacher_id' => $this->teacher->id, 'capacity' => $capacity,
        ]);
        $class->meetings()->create(['day_of_week' => $meeting[0], 'start_time' => $meeting[1], 'end_time' => $meeting[2]]);

        return $class;
    }

    private function requestClasses(Student $student, array $classes)
    {
        return $this->actingAs($this->registrar)->post('/enrollments', [
            'student_id' => $student->id,
            'class_ids' => array_map(fn ($class) => $class->id, $classes),
        ]);
    }

    private function enrollment(Student $student, ClassOffering $class, string $status = 'pending'): Enrollment
    {
        return Enrollment::create(['student_id' => $student->id, 'class_offering_id' => $class->id, 'status' => $status]);
    }

    private function statusOf(Student $student, ClassOffering $class): ?string
    {
        return Enrollment::where('student_id', $student->id)->where('class_offering_id', $class->id)->value('status');
    }

    // ---------- Requests ----------

    public function test_a_request_is_recorded_as_pending_and_logged(): void
    {
        $student = $this->makeStudent('2026-0001');
        $class = $this->makeClass('CL-1001', $this->makeSubject('CS101'));

        $this->requestClasses($student, [$class])->assertSessionHasNoErrors()->assertRedirect(route('enrollments.index'));

        $this->assertSame('pending', $this->statusOf($student, $class));
        $this->assertDatabaseHas('audit_logs', ['action' => 'enrollment.requested']);
    }

    public function test_requests_are_refused_when_enrollment_is_closed(): void
    {
        $student = $this->makeStudent('2026-0001');
        $class = $this->makeClass('CL-1001', $this->makeSubject('CS101'));
        $this->term->update(['enrollment_open' => false]);

        $this->requestClasses($student, [$class])->assertSessionHasErrors('enrollment');

        $this->assertNull($this->statusOf($student, $class));
    }

    public function test_a_full_class_takes_no_new_requests(): void
    {
        $class = $this->makeClass('CL-1001', $this->makeSubject('CS101'), capacity: 1);
        $this->enrollment($this->makeStudent('2026-0001'), $class, 'enrolled');
        $second = $this->makeStudent('2026-0002');

        $this->requestClasses($second, [$class])->assertSessionHasErrors('enrollment');

        $this->assertNull($this->statusOf($second, $class));
    }

    public function test_the_same_class_cannot_be_requested_twice(): void
    {
        $student = $this->makeStudent('2026-0001');
        $class = $this->makeClass('CL-1001', $this->makeSubject('CS101'));
        $this->enrollment($student, $class);

        $this->requestClasses($student, [$class])->assertSessionHasErrors('enrollment');

        $this->assertSame(1, Enrollment::where('student_id', $student->id)->count());
    }

    public function test_one_class_per_subject_in_a_term(): void
    {
        $student = $this->makeStudent('2026-0001');
        $subject = $this->makeSubject('CS101');
        $first = $this->makeClass('CL-1001', $subject);
        $second = $this->makeClass('CL-1002', $subject, [3, '08:00', '09:30'], 30, 'B');
        $this->enrollment($student, $first);

        $this->requestClasses($student, [$second])->assertSessionHasErrors('enrollment');
    }

    public function test_a_class_that_clashes_with_a_held_class_is_refused(): void
    {
        $student = $this->makeStudent('2026-0001');
        $held = $this->makeClass('CL-1001', $this->makeSubject('CS101'));
        $clash = $this->makeClass('CL-1002', $this->makeSubject('CS102'), [1, '09:00', '10:30']);
        $this->enrollment($student, $held, 'approved');

        $this->requestClasses($student, [$clash])->assertSessionHasErrors('enrollment');
    }

    public function test_back_to_back_classes_are_allowed(): void
    {
        $student = $this->makeStudent('2026-0001');
        $held = $this->makeClass('CL-1001', $this->makeSubject('CS101'));
        $next = $this->makeClass('CL-1002', $this->makeSubject('CS102'), [1, '09:30', '11:00']);
        $this->enrollment($student, $held, 'approved');

        $this->requestClasses($student, [$next])->assertSessionHasNoErrors();
    }

    public function test_two_clashing_classes_chosen_together_are_refused_and_nothing_is_saved(): void
    {
        $student = $this->makeStudent('2026-0001');
        $a = $this->makeClass('CL-1001', $this->makeSubject('CS101'));
        $b = $this->makeClass('CL-1002', $this->makeSubject('CS102'), [1, '08:30', '10:00']);

        $this->requestClasses($student, [$a, $b])->assertSessionHasErrors('enrollment');

        $this->assertSame(0, Enrollment::count());
    }

    public function test_the_unit_limit_is_enforced(): void
    {
        Setting::put('max_units_per_term', '5');
        $student = $this->makeStudent('2026-0001');
        $held = $this->makeClass('CL-1001', $this->makeSubject('CS101', 3));
        $next = $this->makeClass('CL-1002', $this->makeSubject('CS102', 3), [2, '08:00', '09:30']);
        $this->enrollment($student, $held, 'enrolled');

        $this->requestClasses($student, [$next])->assertSessionHasErrors('enrollment');
    }

    public function test_inactive_and_archived_students_cannot_be_enrolled(): void
    {
        $class = $this->makeClass('CL-1001', $this->makeSubject('CS101'));

        $withdrawn = $this->makeStudent('2026-0001');
        $withdrawn->update(['status' => 'withdrawn']);
        $this->requestClasses($withdrawn, [$class])->assertSessionHasErrors('enrollment');

        $archived = $this->makeStudent('2026-0002');
        $archived->delete();
        $this->requestClasses($archived, [$class])->assertSessionHasErrors('student_id');
    }

    // ---------- Approval and enrollment ----------

    public function test_approval_needs_verified_documents(): void
    {
        $student = $this->makeStudent('2026-0001', verified: false);
        $class = $this->makeClass('CL-1001', $this->makeSubject('CS101'));
        $enrollment = $this->enrollment($student, $class);

        $this->actingAs($this->registrar)->post(route('enrollments.approve', $enrollment))->assertSessionHasErrors('enrollment');
        $this->assertSame('pending', $enrollment->fresh()->status);

        StudentRequirement::create([
            'student_id' => $student->id, 'requirement_id' => $this->requirement->id,
            'status' => 'verified', 'verified_by' => $this->registrar->id, 'verified_at' => now(),
        ]);

        $this->actingAs($this->registrar)->post(route('enrollments.approve', $enrollment))->assertSessionHasNoErrors();
        $this->assertSame('approved', $enrollment->fresh()->status);
        $this->assertSame($this->registrar->id, $enrollment->fresh()->reviewed_by);
    }

    public function test_the_last_seat_goes_to_one_student_only(): void
    {
        $class = $this->makeClass('CL-1001', $this->makeSubject('CS101'), capacity: 1);
        $first = $this->enrollment($this->makeStudent('2026-0001'), $class);
        $second = $this->enrollment($this->makeStudent('2026-0002'), $class);

        $this->actingAs($this->registrar)->post(route('enrollments.approve', $first))->assertSessionHasNoErrors();
        $this->actingAs($this->registrar)->post(route('enrollments.approve', $second))->assertSessionHasErrors('enrollment');

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_enrollment_is_a_separate_step_after_approval(): void
    {
        $student = $this->makeStudent('2026-0001');
        $class = $this->makeClass('CL-1001', $this->makeSubject('CS101'));
        $enrollment = $this->enrollment($student, $class);

        // Cannot jump from pending straight to enrolled.
        $this->actingAs($this->registrar)->post(route('enrollments.enroll', $enrollment))->assertSessionHasErrors('enrollment');
        $this->assertSame('pending', $enrollment->fresh()->status);

        $this->actingAs($this->registrar)->post(route('enrollments.approve', $enrollment));
        $this->actingAs($this->teacher)->get(route('classes.show', $class))->assertDontSee('2026-0001');

        $this->actingAs($this->registrar)->post(route('enrollments.enroll', $enrollment))->assertSessionHasNoErrors();
        $this->assertSame('enrolled', $enrollment->fresh()->status);
        $this->assertNotNull($enrollment->fresh()->enrolled_at);
        $this->actingAs($this->teacher)->get(route('classes.show', $class))->assertSee('2026-0001');
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $enrollment = $this->enrollment($this->makeStudent('2026-0001'), $this->makeClass('CL-1001', $this->makeSubject('CS101')));

        $this->actingAs($this->registrar)->post(route('enrollments.reject', $enrollment), ['reason' => 'no'])->assertSessionHasErrors('enrollment');
        $this->assertSame('pending', $enrollment->fresh()->status);

        $this->actingAs($this->registrar)->post(route('enrollments.reject', $enrollment), ['reason' => 'Documents are incomplete'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $enrollment->fresh()->status);
        $this->assertSame('Documents are incomplete', $enrollment->fresh()->status_reason);
    }

    public function test_cancelling_frees_the_seat_and_reopening_is_checked(): void
    {
        $class = $this->makeClass('CL-1001', $this->makeSubject('CS101'), capacity: 1);
        $first = $this->enrollment($this->makeStudent('2026-0001'), $class, 'enrolled');

        $this->actingAs($this->registrar)->post(route('enrollments.cancel', $first), ['reason' => 'Student dropped the class'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertSame(0, $class->seatsTaken());

        // The freed seat goes to someone else.
        $other = $this->enrollment($this->makeStudent('2026-0002'), $class, 'approved');
        $this->assertSame(1, $class->seatsTaken());

        // Reopening the first request is refused while the class is full.
        $this->actingAs($this->registrar)->post(route('enrollments.reopen', $first))->assertSessionHasErrors('enrollment');
        $this->assertSame('cancelled', $first->fresh()->status);

        $this->actingAs($this->registrar)->post(route('enrollments.cancel', $other), ['reason' => 'Changed their mind'])->assertSessionHasNoErrors();
        $this->actingAs($this->registrar)->post(route('enrollments.reopen', $first))->assertSessionHasNoErrors();
        $this->assertSame('pending', $first->fresh()->status);
    }

    public function test_closed_terms_lock_every_status_change(): void
    {
        $class = $this->makeClass('CL-1001', $this->makeSubject('CS101'));
        $pending = $this->enrollment($this->makeStudent('2026-0001'), $class);
        $enrolled = $this->enrollment($this->makeStudent('2026-0002'), $class, 'enrolled');
        $this->term->update(['closed_at' => now(), 'is_current' => false, 'enrollment_open' => false]);

        $this->actingAs($this->registrar)->post(route('enrollments.approve', $pending))->assertSessionHasErrors('enrollment');
        $this->actingAs($this->registrar)->post(route('enrollments.reject', $pending), ['reason' => 'Not allowed now'])->assertSessionHasErrors('enrollment');
        $this->actingAs($this->registrar)->post(route('enrollments.cancel', $enrolled), ['reason' => 'Too late to change'])->assertSessionHasErrors('enrollment');

        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('enrolled', $enrolled->fresh()->status);
    }

    public function test_every_transition_is_written_to_the_audit_log(): void
    {
        $enrollment = $this->enrollment($this->makeStudent('2026-0001'), $this->makeClass('CL-1001', $this->makeSubject('CS101')));

        $this->actingAs($this->registrar)->post(route('enrollments.approve', $enrollment));
        $this->actingAs($this->registrar)->post(route('enrollments.enroll', $enrollment));

        $this->assertDatabaseHas('audit_logs', ['action' => 'enrollment.approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'enrollment.enrolled']);
    }

    // ---------- Access and screens ----------

    public function test_only_admin_and_registrar_can_use_enrollment_pages(): void
    {
        $enrollment = $this->enrollment($this->makeStudent('2026-0001'), $this->makeClass('CL-1001', $this->makeSubject('CS101')));

        $this->actingAs($this->registrar)->get('/enrollments')->assertOk();
        $this->actingAs($this->makeUser('admin'))->get('/enrollments')->assertOk();

        foreach ([$this->teacher, $this->makeUser('student')] as $user) {
            $this->actingAs($user)->get('/enrollments')->assertForbidden();
            $this->actingAs($user)->post(route('enrollments.approve', $enrollment))->assertForbidden();
        }

        $this->assertSame('pending', $enrollment->fresh()->status);
    }

    public function test_the_request_list_and_new_request_page_show_real_data(): void
    {
        $student = $this->makeStudent('2026-0001');
        $held = $this->makeClass('CL-1001', $this->makeSubject('CS101'));
        $this->makeClass('CL-1002', $this->makeSubject('CS102'), [1, '09:00', '10:30']);
        $this->enrollment($student, $held, 'approved');

        $this->actingAs($this->registrar)->get('/enrollments')->assertOk()->assertSee('2026-0001')->assertSee('CL-1001');
        $this->actingAs($this->registrar)->get(route('enrollments.create', ['student' => $student->id]))
            ->assertOk()->assertSee('Schedule conflict')->assertSee('Already requested');
        $this->actingAs($this->registrar)->get(route('enrollments.create', ['q' => 'Tester']))->assertOk()->assertSee('2026-0001');
    }

    public function test_the_new_request_page_explains_when_enrollment_is_closed(): void
    {
        $this->term->update(['enrollment_open' => false]);

        $this->actingAs($this->registrar)->get(route('enrollments.create'))->assertOk()->assertSee('Enrollment is closed');
    }
}
