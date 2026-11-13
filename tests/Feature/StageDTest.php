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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StageDTest extends TestCase
{
    use RefreshDatabase;

    private Term $term;
    private Subject $subject;
    private User $teacher;
    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $this->term = Term::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester',
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true,
        ]);
        $this->subject = Subject::create(['code' => 'CS101', 'title' => 'Intro to Computing', 'units' => 3]);
        $this->teacher = $this->makeUser('teacher');
        $this->program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

        return $user;
    }

    private function studentPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Alex', 'last_name' => 'Rivera', 'gender' => 'Male', 'birth_date' => '2007-03-14',
            'program_id' => $this->program->id, 'year_level' => 1, 'student_type' => 'new', 'privacy_consent' => 1,
            'guardians' => [
                0 => ['full_name' => 'Pat Rivera', 'relationship' => 'Parent', 'contact_number' => '0912 345 6789', 'is_emergency_contact' => 1],
            ],
        ], $overrides);
    }

    private function classPayload(array $overrides = []): array
    {
        return array_merge([
            'term_id' => $this->term->id, 'subject_id' => $this->subject->id, 'section' => 'A',
            'class_code' => 'CL-1001', 'teacher_id' => $this->teacher->id, 'capacity' => 30, 'status' => 'open',
            'meetings' => [['day' => 1, 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101']],
        ], $overrides);
    }

    private function makeClass(array $overrides = []): ClassOffering
    {
        $class = ClassOffering::create(array_merge([
            'term_id' => $this->term->id, 'subject_id' => $this->subject->id, 'section' => 'A',
            'class_code' => 'CL-1001', 'teacher_id' => $this->teacher->id, 'capacity' => 30,
        ], $overrides));
        $class->meetings()->create(['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'Room 101']);

        return $class;
    }

    private function makeStudent(string $number = '2026-0001'): Student
    {
        return Student::create([
            'student_number' => $number, 'first_name' => 'Test', 'last_name' => 'Student', 'gender' => 'Female',
            'birth_date' => '2006-01-01', 'program_id' => $this->program->id,
        ]);
    }

    // ---------- Students ----------

    public function test_registrar_registers_a_student_with_a_generated_number(): void
    {
        $registrar = $this->makeUser('registrar');

        $this->actingAs($registrar)->post('/students', $this->studentPayload())->assertSessionHasNoErrors();

        $student = Student::firstOrFail();
        $this->assertSame(now()->year . '-0001', $student->student_number);
        $this->assertSame('active', $student->status);
        $this->assertCount(1, $student->guardians);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.created']);
    }

    public function test_numbers_continue_after_existing_students(): void
    {
        $registrar = $this->makeUser('registrar');
        $this->makeStudent(now()->year . '-0005');

        $this->actingAs($registrar)->post('/students', $this->studentPayload());

        $this->assertDatabaseHas('students', ['student_number' => now()->year . '-0006']);
    }

    public function test_a_guardian_and_an_emergency_contact_are_required(): void
    {
        $registrar = $this->makeUser('registrar');

        $this->actingAs($registrar)->post('/students', $this->studentPayload(['guardians' => []]))
            ->assertSessionHasErrors('guardians');

        $noEmergency = ['0' => ['full_name' => 'Pat Rivera', 'relationship' => 'Parent', 'contact_number' => '0912 345 6789']];
        $this->actingAs($registrar)->post('/students', $this->studentPayload(['guardians' => $noEmergency]))
            ->assertSessionHasErrors('guardians');

        $this->assertSame(0, Student::count());
    }

    public function test_teachers_cannot_open_student_records(): void
    {
        $teacher = $this->makeUser('teacher');
        $student = $this->makeStudent();

        $this->actingAs($teacher)->get('/students')->assertForbidden();
        $this->actingAs($teacher)->get(route('students.show', $student))->assertForbidden();
        $this->actingAs($teacher)->get(route('students.export'))->assertForbidden();
    }

    public function test_photos_are_private_and_staff_only(): void
    {
        Storage::fake('local');
        $registrar = $this->makeUser('registrar');

        $this->actingAs($registrar)->post('/students', $this->studentPayload([
            'photo' => UploadedFile::fake()->image('photo.jpg', 200, 200),
        ]))->assertSessionHasNoErrors();

        $student = Student::firstOrFail();
        Storage::disk('local')->assertExists($student->photo_path);

        $this->actingAs($registrar)->get(route('students.photo', $student))->assertOk();
        $this->actingAs($this->makeUser('teacher'))->get(route('students.photo', $student))->assertForbidden();
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $registrar = $this->makeUser('registrar');

        $this->actingAs($registrar)->post('/students', $this->studentPayload([
            'photo' => UploadedFile::fake()->create('virus.exe', 50, 'application/x-msdownload'),
        ]))->assertSessionHasErrors('photo');
    }

    public function test_archiving_keeps_history_and_can_be_undone(): void
    {
        $registrar = $this->makeUser('registrar');
        $student = $this->makeStudent();

        $this->actingAs($registrar)->delete(route('students.archive', $student))->assertRedirect(route('students.index'));
        $this->assertSoftDeleted('students', ['id' => $student->id]);

        $this->actingAs($registrar)->get(route('students.show', $student))->assertOk();
        $this->actingAs($registrar)->get(route('students.index', ['archived' => 1]))->assertOk()->assertSee($student->student_number);

        $this->actingAs($registrar)->post(route('students.restore', $student))->assertRedirect();
        $this->assertNull($student->fresh()->deleted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.restored']);
    }

    public function test_a_student_with_an_open_request_cannot_be_archived(): void
    {
        $registrar = $this->makeUser('registrar');
        $student = $this->makeStudent();
        $class = $this->makeClass();
        Enrollment::create(['student_id' => $student->id, 'class_offering_id' => $class->id, 'status' => 'pending']);

        $this->actingAs($registrar)->delete(route('students.archive', $student))->assertSessionHas('error');

        $this->assertNull($student->fresh()->deleted_at);
    }

    public function test_requirements_can_be_verified_and_reset(): void
    {
        $registrar = $this->makeUser('registrar');
        $student = $this->makeStudent();
        $requirement = \App\Models\Requirement::create(['name' => 'PSA Birth Certificate']);

        $this->actingAs($registrar)->post(route('students.requirements.update', [$student, $requirement]), ['status' => 'verified']);
        $this->assertDatabaseHas('student_requirements', [
            'student_id' => $student->id, 'requirement_id' => $requirement->id, 'status' => 'verified', 'verified_by' => $registrar->id,
        ]);

        // Going back to pending is no longer an instant reset: it needs the "return" action with a reason.
        $this->actingAs($registrar)->post(route('students.requirements.update', [$student, $requirement]), ['status' => 'pending'])
            ->assertSessionHasErrors('status');
        $this->actingAs($registrar)->post(route('students.requirements.return', [$student, $requirement]), ['reason' => 'Wrong document attached.']);
        $this->assertDatabaseHas('student_requirements', ['student_id' => $student->id, 'status' => 'pending', 'verified_by' => null, 'returned_reason' => 'Wrong document attached.']);
    }

    public function test_search_and_filters_narrow_the_list(): void
    {
        $registrar = $this->makeUser('registrar');
        $this->makeStudent('2026-0001');
        Student::create([
            'student_number' => '2026-0002', 'first_name' => 'Jamie', 'last_name' => 'Lee', 'gender' => 'Female',
            'birth_date' => '2006-05-05', 'program_id' => $this->program->id, 'year_level' => 3,
        ]);

        $this->actingAs($registrar)->get(route('students.index', ['search' => 'jamie']))->assertSee('2026-0002')->assertDontSee('2026-0001');
        $this->actingAs($registrar)->get(route('students.index', ['year_level' => 3]))->assertSee('2026-0002')->assertDontSee('2026-0001');
    }

    public function test_csv_export_blocks_formula_injection(): void
    {
        $registrar = $this->makeUser('registrar');
        $student = $this->makeStudent();
        $student->update(['address' => '=1+1']);

        $response = $this->actingAs($registrar)->get(route('students.export'));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString($student->student_number, $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.exported']);
    }

    // ---------- Classes ----------

    public function test_registrar_creates_a_class_with_a_schedule(): void
    {
        $registrar = $this->makeUser('registrar');

        $this->actingAs($registrar)->post('/classes', $this->classPayload())->assertSessionHasNoErrors();

        // The schedule code is generated (2026, 1st semester, number 1); a typed-in code is ignored.
        $class = ClassOffering::firstOrFail();
        $this->assertSame('202610001', $class->class_code);
        $this->assertCount(1, $class->meetings);
        $this->assertDatabaseHas('audit_logs', ['action' => 'class.created']);
    }

    public function test_a_class_needs_a_valid_schedule(): void
    {
        $registrar = $this->makeUser('registrar');

        $this->actingAs($registrar)->post('/classes', $this->classPayload(['meetings' => []]))->assertSessionHasErrors('meetings');
        $this->actingAs($registrar)->post('/classes', $this->classPayload([
            'meetings' => [['day' => 1, 'start' => '10:00', 'end' => '09:00']],
        ]))->assertSessionHasErrors('meetings');
    }

    public function test_a_teacher_cannot_teach_two_classes_at_once(): void
    {
        $registrar = $this->makeUser('registrar');
        $this->makeClass();
        $other = Subject::create(['code' => 'CS102', 'title' => 'Programming', 'units' => 3]);

        $this->actingAs($registrar)->post('/classes', $this->classPayload([
            'subject_id' => $other->id, 'class_code' => 'CL-1002',
            'meetings' => [['day' => 1, 'start' => '09:00', 'end' => '10:30', 'room' => 'Room 202']],
        ]))->assertSessionHasErrors('meetings');

        $this->assertSame(1, ClassOffering::count());
    }

    public function test_a_room_cannot_be_used_twice_at_the_same_time(): void
    {
        $registrar = $this->makeUser('registrar');
        $this->makeClass();
        $other = Subject::create(['code' => 'CS102', 'title' => 'Programming', 'units' => 3]);

        $this->actingAs($registrar)->post('/classes', $this->classPayload([
            'subject_id' => $other->id, 'class_code' => 'CL-1002', 'teacher_id' => $this->makeUser('teacher')->id,
            'meetings' => [['day' => 1, 'start' => '08:30', 'end' => '10:00', 'room' => 'room 101']],
        ]))->assertSessionHasErrors('meetings');
    }

    public function test_back_to_back_classes_do_not_conflict(): void
    {
        $registrar = $this->makeUser('registrar');
        $this->makeClass();
        $other = Subject::create(['code' => 'CS102', 'title' => 'Programming', 'units' => 3]);

        $this->actingAs($registrar)->post('/classes', $this->classPayload([
            'subject_id' => $other->id, 'class_code' => 'CL-1002',
            'meetings' => [['day' => 1, 'start' => '09:30', 'end' => '11:00', 'room' => 'Room 101']],
        ]))->assertSessionHasNoErrors();
    }

    public function test_capacity_cannot_drop_below_seats_taken(): void
    {
        $registrar = $this->makeUser('registrar');
        $class = $this->makeClass();
        Enrollment::create(['student_id' => $this->makeStudent()->id, 'class_offering_id' => $class->id, 'status' => 'enrolled']);

        $this->actingAs($registrar)->put(route('classes.update', $class), $this->classPayload(['capacity' => 0 + 1, 'section' => 'A']))
            ->assertSessionHasNoErrors();

        $second = $this->makeStudent('2026-0002');
        Enrollment::create(['student_id' => $second->id, 'class_offering_id' => $class->id, 'status' => 'enrolled']);

        $this->actingAs($registrar)->put(route('classes.update', $class), $this->classPayload(['capacity' => 1]))
            ->assertSessionHasErrors('capacity');
    }

    public function test_times_are_locked_while_students_hold_seats(): void
    {
        $registrar = $this->makeUser('registrar');
        $class = $this->makeClass();
        Enrollment::create(['student_id' => $this->makeStudent()->id, 'class_offering_id' => $class->id, 'status' => 'enrolled']);

        $this->actingAs($registrar)->put(route('classes.update', $class), $this->classPayload([
            'meetings' => [['day' => 2, 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101']],
        ]))->assertSessionHasErrors('meetings');

        // Changing only the room is allowed.
        $this->actingAs($registrar)->put(route('classes.update', $class), $this->classPayload([
            'meetings' => [['day' => 1, 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 305']],
        ]))->assertSessionHasNoErrors();
    }

    public function test_a_class_with_active_requests_cannot_be_cancelled(): void
    {
        $registrar = $this->makeUser('registrar');
        $class = $this->makeClass();
        Enrollment::create(['student_id' => $this->makeStudent()->id, 'class_offering_id' => $class->id, 'status' => 'pending']);

        $this->actingAs($registrar)->put(route('classes.update', $class), $this->classPayload(['status' => 'cancelled']))
            ->assertSessionHasErrors('status');
    }

    public function test_teachers_see_only_their_own_classes_and_enrolled_students(): void
    {
        $mine = $this->makeClass();
        $otherTeacher = $this->makeUser('teacher');
        $other = $this->makeClass(['class_code' => 'CL-1002', 'teacher_id' => $otherTeacher->id, 'section' => 'B']);

        $enrolled = $this->makeStudent('2026-0001');
        $pending = $this->makeStudent('2026-0002');
        Enrollment::create(['student_id' => $enrolled->id, 'class_offering_id' => $mine->id, 'status' => 'enrolled']);
        Enrollment::create(['student_id' => $pending->id, 'class_offering_id' => $mine->id, 'status' => 'pending']);

        $this->actingAs($this->teacher)->get(route('classes.show', $mine))
            ->assertOk()->assertSee('2026-0001')->assertDontSee('2026-0002');
        $this->actingAs($this->teacher)->get(route('classes.show', $other))->assertForbidden();
        $this->actingAs($this->teacher)->get('/teacher/classes')->assertOk()->assertSee('CL-1001')->assertDontSee('CL-1002');
        $this->actingAs($this->teacher)->get('/classes')->assertForbidden();
    }

    public function test_registrar_sees_every_request_on_a_class(): void
    {
        $registrar = $this->makeUser('registrar');
        $class = $this->makeClass();
        Enrollment::create(['student_id' => $this->makeStudent('2026-0002')->id, 'class_offering_id' => $class->id, 'status' => 'pending']);

        $this->actingAs($registrar)->get(route('classes.show', $class))->assertOk()->assertSee('2026-0002');
    }
}
