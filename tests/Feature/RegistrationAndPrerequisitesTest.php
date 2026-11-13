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
use App\Services\EnrollmentRules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Subject prerequisites in the enrollment rules, and the Certificate of Registration PDF. */
class RegistrationAndPrerequisitesTest extends TestCase
{
    use RefreshDatabase;

    private User $registrar;
    private User $teacher;
    private User $anaUser;
    private Student $ana;
    private Term $first;
    private Term $second;
    private Subject $prog1;
    private Subject $prog2;
    private int $room = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-01-11 10:00:00');

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $this->first = Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'enrollment_open' => true]);
        $this->second = Term::create(['academic_year_id' => $year->id, 'name' => '2nd Semester', 'start_date' => '2027-01-04', 'end_date' => '2027-05-21', 'is_current' => true, 'enrollment_open' => true]);
        $program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);

        $this->registrar = $this->makeUser('registrar');
        $this->teacher = $this->makeUser('teacher');
        $this->anaUser = $this->makeUser('student');

        $this->prog1 = Subject::create(['code' => 'CS101', 'title' => 'Programming 1', 'units' => 3]);
        $this->prog2 = Subject::create(['code' => 'CS102', 'title' => 'Programming 2', 'units' => 3]);
        $this->prog2->prerequisites()->sync([$this->prog1->id]);

        $this->ana = Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $program->id, 'year_level' => 1, 'status' => 'active', 'user_id' => $this->anaUser->id]);
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

    private function makeClass(Term $term, Subject $subject, int $day = 1): ClassOffering
    {
        $this->room++;
        $class = ClassOffering::create(['term_id' => $term->id, 'subject_id' => $subject->id, 'section' => 'S' . $this->room, 'class_code' => 'CL-' . $this->room, 'teacher_id' => $this->teacher->id, 'capacity' => 40]);
        $class->meetings()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'Room ' . $this->room]);

        return $class;
    }

    private function enrolled(ClassOffering $class, string $status = 'enrolled', array $extra = []): Enrollment
    {
        return Enrollment::create(array_merge(['student_id' => $this->ana->id, 'class_offering_id' => $class->id, 'status' => $status, 'requested_at' => now(), 'enrolled_at' => $status === 'pending' ? null : now()], $extra));
    }

    public function test_a_subject_cannot_be_taken_before_its_prerequisite(): void
    {
        $problems = EnrollmentRules::check($this->ana, $this->makeClass($this->second, $this->prog2));

        $this->assertContains('CS102 needs CS101 completed first.', $problems);
    }

    public function test_taking_the_prerequisite_in_the_same_term_is_not_enough(): void
    {
        $this->enrolled($this->makeClass($this->second, $this->prog1, 2));

        $this->assertContains('CS102 needs CS101 completed first.', EnrollmentRules::check($this->ana, $this->makeClass($this->second, $this->prog2)));
    }

    public function test_the_prerequisite_completed_in_an_earlier_term_allows_it(): void
    {
        $this->enrolled($this->makeClass($this->first, $this->prog1), 'enrolled', ['requested_at' => '2026-08-05', 'enrolled_at' => '2026-08-05']);

        $this->assertNotContains('CS102 needs CS101 completed first.', EnrollmentRules::check($this->ana, $this->makeClass($this->second, $this->prog2)));
    }

    public function test_a_dropped_prerequisite_does_not_count(): void
    {
        $this->enrolled($this->makeClass($this->first, $this->prog1), 'cancelled', ['requested_at' => '2026-08-05', 'enrolled_at' => '2026-08-05', 'dropped_at' => '2026-09-01']);

        $this->assertContains('CS102 needs CS101 completed first.', EnrollmentRules::check($this->ana, $this->makeClass($this->second, $this->prog2)));
    }

    public function test_the_registrar_sets_prerequisites_and_circles_are_refused(): void
    {
        $values = fn (Subject $s, array $pre) => ['code' => $s->code, 'title' => $s->title, 'units' => 3, 'category' => 'minor', 'is_active' => 1, 'prerequisite_ids' => $pre];

        // CS101 cannot need CS102, because CS102 already needs CS101; nor can it need itself.
        $this->actingAs($this->registrar)->put(route('subjects.update', $this->prog1), $values($this->prog1, [$this->prog2->id]))
            ->assertSessionHasErrors('prerequisite_ids');
        $this->actingAs($this->registrar)->put(route('subjects.update', $this->prog1), $values($this->prog1, [$this->prog1->id]))
            ->assertSessionHasErrors('prerequisite_ids');

        $prog3 = Subject::create(['code' => 'CS103', 'title' => 'Data Structures', 'units' => 3]);
        $this->actingAs($this->registrar)->put(route('subjects.update', $prog3), $values($prog3, [$this->prog2->id]))->assertSessionHasNoErrors();
        $this->assertSame(['CS102'], $prog3->prerequisites()->pluck('code')->all());

        $this->actingAs($this->registrar)->get(route('subjects.index'))->assertSee('Needs CS101');
    }

    public function test_the_student_downloads_their_registration_form(): void
    {
        $this->enrolled($this->makeClass($this->second, $this->prog1));

        $response = $this->actingAs($this->anaUser)->get(route('student.registration-form'));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('registration-form-2026-0001', $response->headers->get('content-disposition'));

        $this->actingAs($this->anaUser)->get(route('student.schedule'))->assertSee('Registration form (PDF)');
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.registration_form']);
    }

    public function test_the_form_shows_the_load_and_the_registrar_can_print_it(): void
    {
        $this->enrolled($this->makeClass($this->second, $this->prog1));

        $html = view('registration.form', [
            'student' => $this->ana->load('program'), 'term' => $this->second->load('academicYear'),
            'rows' => collect([['code' => 'CL-1', 'subject' => 'CS101', 'title' => 'Programming 1', 'units' => 3.0, 'block' => 'Section S1', 'times' => ['Mon 8:00 AM–9:30 AM'], 'rooms' => ['Room 1'], 'teacher' => 'Maria Santos']]),
            'totalUnits' => 3.0, 'enrolledOn' => now(), 'school' => 'SIES Campus Portal', 'address' => '', 'logo' => null,
            'generatedAt' => now(), 'generatedBy' => 'Test',
        ])->render();
        $this->assertStringContainsString('CERTIFICATE OF REGISTRATION', $html);
        $this->assertStringContainsString('Mon 8:00 AM–9:30 AM', $html);
        $this->assertStringContainsString('Maria Santos', $html);

        $this->actingAs($this->registrar)->get(route('students.registration-form', [$this->ana, 'term' => $this->second->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->registrar)->get(route('students.show', $this->ana))->assertSee('Registration form');
    }

    public function test_pending_classes_alone_give_no_form_and_others_cannot_fetch_it(): void
    {
        $this->enrolled($this->makeClass($this->second, $this->prog1), 'pending');

        $this->actingAs($this->anaUser)->get(route('student.registration-form'))
            ->assertRedirect(route('student.schedule'))->assertSessionHas('error');

        // Students and teachers cannot use the staff route.
        $this->actingAs($this->anaUser)->get(route('students.registration-form', $this->ana))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('students.registration-form', $this->ana))->assertForbidden();
    }
}
