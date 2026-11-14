<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassOffering;
use App\Models\CreditedSubject;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\EnrollmentRules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Credited subjects, copying classes between terms, promoting year levels, class list PDF, confirmed Verify. */
class TermToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $registrar;
    private User $teacher;
    private User $otherTeacher;
    private Program $program;
    private Term $first;
    private Term $second;
    private Student $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-01-11 10:00:00');

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $this->first = Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18']);
        $this->second = Term::create(['academic_year_id' => $year->id, 'name' => '2nd Semester', 'start_date' => '2027-01-04', 'end_date' => '2027-05-21', 'is_current' => true, 'enrollment_open' => true]);
        $this->program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        $this->registrar = $this->makeUser('registrar');
        $this->teacher = $this->makeUser('teacher');
        $this->otherTeacher = $this->makeUser('teacher');
        $this->ana = Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female', 'birth_date' => '2006-01-01',
            'program_id' => $this->program->id, 'year_level' => 1, 'status' => 'active', 'student_type' => 'transferee', 'previous_school' => 'Sample State University']);
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

    private function makeClass(Term $term, Subject $subject, User $teacher, string $code, int $day = 1, string $section = 'A'): ClassOffering
    {
        $class = ClassOffering::create(['term_id' => $term->id, 'subject_id' => $subject->id, 'program_id' => $this->program->id, 'year_level' => 1,
            'section' => $section, 'class_code' => $code, 'teacher_id' => $teacher->id, 'capacity' => 35]);
        $class->meetings()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'R-' . $code]);

        return $class;
    }

    // ---------- Credited subjects ----------

    public function test_a_credited_subject_counts_for_prerequisites_and_is_not_taken_again(): void
    {
        $prog1 = Subject::create(['code' => 'CS101', 'title' => 'Programming 1', 'units' => 3]);
        $prog2 = Subject::create(['code' => 'CS102', 'title' => 'Programming 2', 'units' => 3]);
        $prog2->prerequisites()->sync([$prog1->id]);
        $class2 = $this->makeClass($this->second, $prog2, $this->teacher, 'C2');
        $class1 = $this->makeClass($this->second, $prog1, $this->teacher, 'C1', 2);

        $this->assertContains('CS102 needs CS101 completed first.', EnrollmentRules::check($this->ana, $class2));

        $this->actingAs($this->registrar)->post(route('students.credits.store', $this->ana), ['subject_id' => $prog1->id, 'school' => 'Sample State University', 'note' => 'Grade 1.75'])
            ->assertSessionHas('success')->assertSessionHas('tab', 'enrollments');

        $this->assertNotContains('CS102 needs CS101 completed first.', EnrollmentRules::check($this->ana, $class2));
        $this->assertContains('CS101 is already credited (taken at Sample State University).', EnrollmentRules::check($this->ana, $class1));
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.subject_credited']);

        $this->actingAs($this->registrar)->get(route('students.show', $this->ana))->assertSee('Credited subjects')->assertSee('Grade 1.75');

        // Twice is refused; removing asks first (confirm window) and then the prerequisite applies again.
        $this->actingAs($this->registrar)->post(route('students.credits.store', $this->ana), ['subject_id' => $prog1->id, 'school' => 'X'])
            ->assertSessionHasErrors('subject_id');
        $credit = CreditedSubject::firstOrFail();
        $this->actingAs($this->registrar)->delete(route('students.credits.destroy', [$this->ana, $credit]))->assertSessionHas('success');
        $this->assertContains('CS102 needs CS101 completed first.', EnrollmentRules::check($this->ana, $class2));
    }

    public function test_a_subject_taken_here_cannot_be_credited_and_teachers_cannot_credit(): void
    {
        $subject = Subject::create(['code' => 'GE1', 'title' => 'Ethics', 'units' => 3]);
        $class = $this->makeClass($this->second, $subject, $this->teacher, 'C1');
        Enrollment::create(['student_id' => $this->ana->id, 'class_offering_id' => $class->id, 'status' => 'pending', 'requested_at' => now()]);

        $this->actingAs($this->registrar)->post(route('students.credits.store', $this->ana), ['subject_id' => $subject->id, 'school' => 'Elsewhere'])
            ->assertSessionHasErrors('subject_id');
        $this->actingAs($this->teacher)->post(route('students.credits.store', $this->ana), ['subject_id' => $subject->id, 'school' => 'Elsewhere'])
            ->assertForbidden();
    }

    // ---------- Copy classes ----------

    public function test_classes_are_copied_into_a_new_term_with_new_codes_and_no_students(): void
    {
        $prog = Subject::create(['code' => 'CS101', 'title' => 'Programming 1', 'units' => 3]);
        $ethics = Subject::create(['code' => 'GE1', 'title' => 'Ethics', 'units' => 3]);
        $old = $this->makeClass($this->first, $prog, $this->teacher, '202610001');
        $this->makeClass($this->first, $ethics, $this->otherTeacher, '202610002', 2);
        Enrollment::create(['student_id' => $this->ana->id, 'class_offering_id' => $old->id, 'status' => 'enrolled', 'requested_at' => '2026-08-05', 'enrolled_at' => '2026-08-05']);

        $this->actingAs($this->registrar)->post(route('classes.copy'), ['from_term' => $this->first->id, 'to_term' => $this->second->id])
            ->assertRedirect(route('classes.index', ['term' => $this->second->id]))->assertSessionHas('success');

        $copies = ClassOffering::with('meetings')->where('term_id', $this->second->id)->get();
        $this->assertCount(2, $copies);
        $copy = $copies->firstWhere('subject_id', $prog->id);
        $this->assertNotSame('202610001', $copy->class_code);
        $this->assertSame($this->teacher->id, $copy->teacher_id);
        $this->assertSame(35, (int) $copy->capacity);
        $this->assertSame('open', $copy->status);
        $this->assertCount(1, $copy->meetings);
        $this->assertSame(0, Enrollment::where('class_offering_id', $copy->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'class.copied']);

        // Copying again skips what is already there, and says why.
        $this->actingAs($this->registrar)->post(route('classes.copy'), ['from_term' => $this->first->id, 'to_term' => $this->second->id])
            ->assertSessionHas('copy_skipped', fn ($skipped) => count($skipped) === 2 && str_contains($skipped[0], 'already in this term'));
        $this->assertSame(2, ClassOffering::where('term_id', $this->second->id)->count());
    }

    public function test_copying_skips_inactive_teachers_and_schedule_clashes(): void
    {
        $prog = Subject::create(['code' => 'CS101', 'title' => 'Programming 1', 'units' => 3]);
        $ethics = Subject::create(['code' => 'GE1', 'title' => 'Ethics', 'units' => 3]);
        $math = Subject::create(['code' => 'MATH1', 'title' => 'Math', 'units' => 3]);
        $this->makeClass($this->first, $prog, $this->teacher, '202610001');
        $this->makeClass($this->first, $ethics, $this->otherTeacher, '202610002', 3);
        $this->otherTeacher->forceFill(['is_active' => false])->save();
        // The new term already uses the teacher on Monday 8:00.
        $this->makeClass($this->second, $math, $this->teacher, '202620001', 1, 'B');

        $this->actingAs($this->registrar)->post(route('classes.copy'), ['from_term' => $this->first->id, 'to_term' => $this->second->id])
            ->assertSessionHas('copy_skipped', fn ($s) => count($s) === 2);
        $this->assertSame(1, ClassOffering::where('term_id', $this->second->id)->count());

        $this->actingAs($this->registrar)->get(route('classes.index', ['term' => $this->second->id]))->assertSee('Copy from another term');
    }

    // ---------- Promote year level ----------

    public function test_students_move_up_one_year_after_a_preview_and_the_last_year_stays(): void
    {
        $bea = Student::create(['student_number' => '2026-0002', 'first_name' => 'Bea', 'last_name' => 'Lim', 'gender' => 'Female', 'birth_date' => '2004-01-01', 'program_id' => $this->program->id, 'year_level' => 4, 'status' => 'active']);
        $cal = Student::create(['student_number' => '2026-0003', 'first_name' => 'Cal', 'last_name' => 'Uy', 'gender' => 'Male', 'birth_date' => '2005-01-01', 'program_id' => $this->program->id, 'year_level' => 2, 'status' => 'active']);

        $this->actingAs($this->registrar)->get(route('students.promote'))
            ->assertOk()->assertSee('Year 1 → ', false)->assertSee('last year, not moved')->assertSee('2 of 3 active');

        // Confirmation is required.
        $this->actingAs($this->registrar)->post(route('students.promote.store'), ['students' => [$this->ana->id], 'last_year' => 4])
            ->assertSessionHasErrors('confirm');
        $this->assertSame(1, $this->ana->fresh()->year_level);

        // Cal is unticked (stays); Bea is in the last year and is never moved even if sent.
        $this->actingAs($this->registrar)->post(route('students.promote.store'), ['students' => [$this->ana->id, $bea->id], 'last_year' => 4, 'confirm' => 1])
            ->assertRedirect(route('students.index'))->assertSessionHas('success', '1 student moved up one year level.');
        $this->assertSame(2, $this->ana->fresh()->year_level);
        $this->assertSame(4, $bea->fresh()->year_level);
        $this->assertSame(2, $cal->fresh()->year_level);

        $this->actingAs($this->registrar)->get(route('students.promote'))->assertSee('Last done on');
        $this->actingAs($this->teacher)->get(route('students.promote'))->assertForbidden();
    }

    // ---------- Class list PDF ----------

    public function test_the_class_list_prints_for_staff_and_the_classes_own_teacher_only(): void
    {
        $class = $this->makeClass($this->second, Subject::create(['code' => 'CS101', 'title' => 'Programming 1', 'units' => 3]), $this->teacher, '202620001');
        Enrollment::create(['student_id' => $this->ana->id, 'class_offering_id' => $class->id, 'status' => 'enrolled', 'requested_at' => now(), 'enrolled_at' => now()]);

        $this->actingAs($this->teacher)->get(route('classes.show', $class))->assertSee('Print class list (PDF)');
        $this->actingAs($this->teacher)->get(route('classes.roster', $class))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->registrar)->get(route('classes.roster', $class))->assertOk();
        $this->actingAs($this->otherTeacher)->get(route('classes.roster', $class))->assertForbidden();

        $html = view('classes.roster-pdf', ['class' => $class->load(['subject', 'teacher', 'term.academicYear', 'meetings']), 'students' => collect([$this->ana]),
            'schedule' => ['Monday 8:00 AM–9:30 AM · R-1'], 'school' => 'SIES', 'address' => '', 'logo' => null, 'generatedAt' => now(), 'generatedBy' => 'T'])->render();
        $this->assertStringContainsString('CLASS LIST', $html);
        $this->assertStringContainsString('Cruz, Ana', $html);
    }

    // ---------- Verify asks first ----------

    public function test_verify_and_mark_submitted_open_a_confirmation_window(): void
    {
        $requirement = Requirement::create(['name' => 'PSA Birth Certificate', 'is_required' => true, 'is_active' => true]);

        $page = $this->actingAs($this->registrar)->get(route('students.show', $this->ana));
        $page->assertSee('Verify PSA Birth Certificate?')->assertSee('Mark PSA Birth Certificate as submitted?')
            ->assertSee('data-confirm-fields="{&quot;status&quot;:&quot;verified&quot;}"', false);

        // The confirmed action still saves as before.
        $this->actingAs($this->registrar)->post(route('students.requirements.update', [$this->ana, $requirement]), ['status' => 'verified']);
        $this->assertSame('verified', StudentRequirement::where('requirement_id', $requirement->id)->value('status'));
    }
}
