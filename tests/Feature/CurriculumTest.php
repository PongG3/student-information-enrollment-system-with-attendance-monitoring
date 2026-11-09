<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassOffering;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\EnrollmentRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Subjects belong to programs' curricula (CS102 -> BSCS, BSIT); general subjects are open to all. */
class CurriculumTest extends TestCase
{
    use RefreshDatabase;

    private User $registrar;
    private User $teacher;
    private Term $term;
    private Program $bscs;
    private Program $bsit;
    private Program $bsba;
    private Subject $cs102;
    private Subject $eng101;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrar = $this->makeUser('registrar');
        $this->teacher = $this->makeUser('teacher');
        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $this->term = Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true, 'enrollment_open' => true]);

        $this->bscs = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        $this->bsit = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $this->bsba = Program::create(['code' => 'BSBA', 'name' => 'BS Business Administration']);

        $this->cs102 = Subject::create(['code' => 'CS102', 'title' => 'Computer Programming 1', 'units' => 3, 'category' => Subject::MAJOR]);
        $this->cs102->programs()->sync([$this->bscs->id, $this->bsit->id]);
        $this->eng101 = Subject::create(['code' => 'ENG101', 'title' => 'Communication Skills', 'units' => 3]); // minor (the default)
    }

    private function makeUser(string $role, array $extra = []): User
    {
        $user = User::factory()->create($extra);
        $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

        return $user;
    }

    private function makeStudent(Program $program, string $number): Student
    {
        return Student::create([
            'student_number' => $number, 'first_name' => 'Sam', 'last_name' => $number, 'gender' => 'Female',
            'birth_date' => '2006-05-05', 'program_id' => $program->id, 'year_level' => 1, 'section' => 'A', 'status' => 'active',
        ]);
    }

    private function makeClass(Subject $subject, ?Program $program = null, int $day = 1): ClassOffering
    {
        $class = ClassOffering::create([
            'term_id' => $this->term->id, 'subject_id' => $subject->id, 'program_id' => $program?->id,
            'year_level' => $program ? 1 : null, 'section' => 'A', 'class_code' => '20261000' . $day,
            'teacher_id' => $this->teacher->id, 'capacity' => 40, 'status' => 'open',
        ]);
        $class->meetings()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '09:30']);

        return $class;
    }

    // ---------- Subjects ----------

    public function test_a_subject_is_open_to_its_programs_only(): void
    {
        $this->cs102->load('programs');
        $this->assertTrue($this->cs102->isOpenTo($this->bscs->id));
        $this->assertTrue($this->cs102->isOpenTo($this->bsit->id));
        $this->assertFalse($this->cs102->isOpenTo($this->bsba->id));
        $this->assertSame('BSCS, BSIT', $this->cs102->programsLabel());

        $this->assertTrue($this->eng101->isOpenTo($this->bsba->id));
        $this->assertSame('All programs', $this->eng101->programsLabel());
    }

    public function test_programs_are_chosen_on_the_subject_form(): void
    {
        $this->actingAs($this->registrar)->post(route('subjects.store'), [
            'code' => 'it201', 'title' => 'Networking 1', 'units' => 3, 'is_active' => 1, 'category' => 'major', 'program_ids' => [$this->bsit->id],
        ])->assertSessionHasNoErrors();

        $subject = Subject::where('code', 'IT201')->firstOrFail();
        $this->assertSame(['BSIT'], $subject->programs->pluck('code')->all());

        // Turning it into a minor subject opens it to every program (its program list is cleared).
        $this->actingAs($this->registrar)->put(route('subjects.update', $subject), [
            'code' => 'IT201', 'title' => 'Networking 1', 'units' => 3, 'is_active' => 1, 'category' => 'minor', 'program_ids' => [$this->bsit->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $subject->programs()->count());
        $this->assertTrue($subject->fresh()->isGeneral());

        $this->actingAs($this->registrar)->get(route('subjects.index'))->assertOk()
            ->assertSee('class="program-chip"', false)->assertSee('All programs');
    }

    public function test_a_program_with_classes_cannot_be_taken_off_a_subject(): void
    {
        $class = $this->makeClass($this->cs102, $this->bsit);

        $this->actingAs($this->registrar)->put(route('subjects.update', $this->cs102), [
            'code' => 'CS102', 'title' => 'Computer Programming 1', 'units' => 3, 'is_active' => 1, 'category' => 'major', 'program_ids' => [$this->bscs->id],
        ])->assertSessionHasErrors('program_ids');

        $this->assertStringContainsString($class->class_code, session('errors')->first('program_ids'));
        $this->assertSame(2, $this->cs102->programs()->count());
    }

    public function test_a_major_subject_needs_a_program(): void
    {
        $this->actingAs($this->registrar)->post(route('subjects.store'), [
            'code' => 'CS999', 'title' => 'Thesis', 'units' => 3, 'is_active' => 1, 'category' => 'major',
        ])->assertSessionHasErrors('program_ids');
        $this->assertDatabaseMissing('subjects', ['code' => 'CS999']);

        $this->actingAs($this->registrar)->post(route('subjects.store'), [
            'code' => 'GE-ETH', 'title' => 'Ethics', 'units' => 3, 'is_active' => 1, 'category' => 'minor',
        ])->assertSessionHasNoErrors();
        $this->assertSame('minor', Subject::where('code', 'GE-ETH')->value('category'));

        $this->actingAs($this->registrar)->get(route('subjects.index', ['category' => 'minor']))->assertOk()
            ->assertSee('GE-ETH')->assertDontSee('CS102')->assertSee('class="category-badge is-minor"', false);
    }

    // ---------- Classes ----------

    public function test_a_block_class_must_be_in_its_programs_curriculum(): void
    {
        $payload = fn (Program $program) => [
            'term_id' => $this->term->id, 'subject_id' => $this->cs102->id, 'program_id' => $program->id, 'year_level' => 1,
            'section' => 'A', 'teacher_id' => $this->teacher->id, 'capacity' => 40, 'status' => 'open',
            'meetings' => [['day' => 2, 'start' => '08:00', 'end' => '09:30', 'room' => '']],
        ];

        $this->actingAs($this->registrar)->post(route('classes.store'), $payload($this->bsba))->assertSessionHasErrors('program_id');
        $this->actingAs($this->registrar)->post(route('classes.store'), $payload($this->bscs))->assertSessionHasNoErrors();
        $this->assertSame(['BSCS 1-A'], ClassOffering::with('program')->get()->pluck('block_label')->all());
    }

    // ---------- Enrollment ----------

    public function test_students_are_only_enrolled_in_their_curriculum(): void
    {
        $cs = $this->makeClass($this->cs102, null, 1);
        $eng = $this->makeClass($this->eng101, null, 2);

        $bscsStudent = $this->makeStudent($this->bscs, '2026-0001');
        $bsbaStudent = $this->makeStudent($this->bsba, '2026-0002');

        $this->assertNotContains('CS102 is a major subject for BSCS, BSIT students only.', EnrollmentRules::check($bscsStudent, $cs));
        $this->assertContains('CS102 is a major subject for BSCS, BSIT students only.', EnrollmentRules::check($bsbaStudent, $cs));
        $this->assertSame([], EnrollmentRules::check($bsbaStudent, $eng)); // general subject: open to all

        // Recording the request is refused, and nothing is saved.
        $this->actingAs($this->registrar)->post(route('enrollments.store'), ['student_id' => $bsbaStudent->id, 'class_ids' => [$cs->id]]);
        $this->assertDatabaseMissing('enrollments', ['student_id' => $bsbaStudent->id, 'class_offering_id' => $cs->id]);
    }

    // ---------- Student portal ----------

    public function test_available_courses_show_the_students_curriculum_only(): void
    {
        $cs = $this->makeClass($this->cs102, null, 1);
        $eng = $this->makeClass($this->eng101, null, 2);

        $student = $this->makeStudent($this->bsba, '2026-0003');
        $account = $this->makeUser('student', ['email' => 'bsba@school.test']);
        $student->forceFill(['user_id' => $account->id])->save();

        $this->actingAs($account)->get(route('student.courses'))->assertOk()
            ->assertSee($eng->class_code)->assertDontSee($cs->class_code);
    }
}
