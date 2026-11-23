<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Archive page: own archives for staff, everything (with who) for admins, restore, admin-only delete. */
class ArchiveModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $registrar;
    private User $otherRegistrar;
    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser('admin');
        $this->registrar = $this->makeUser('registrar', 'Rita Registrar');
        $this->otherRegistrar = $this->makeUser('registrar', 'Otto Other');
        $this->program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
    }

    private function makeUser(string $role, ?string $name = null): User
    {
        $user = User::factory()->create($name ? ['name' => $name] : []);
        $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

        return $user;
    }

    private function archivedBy(User $user, string $number, string $first): Student
    {
        $student = Student::create(['student_number' => $number, 'first_name' => $first, 'last_name' => 'Cruz', 'gender' => 'Female',
            'birth_date' => '2006-01-01', 'program_id' => $this->program->id, 'year_level' => 1, 'status' => 'active']);
        $this->actingAs($user)->delete(route('students.archive', $student))->assertSessionHas('success');

        return $student->fresh();
    }

    public function test_staff_see_only_what_they_archived_and_admins_see_all_with_who(): void
    {
        $mine = $this->archivedBy($this->registrar, '2026-0001', 'Ana');
        $theirs = $this->archivedBy($this->otherRegistrar, '2026-0002', 'Bea');
        $this->assertSame($this->registrar->id, $mine->archived_by);

        $this->actingAs($this->registrar)->get(route('archive.index'))->assertOk()
            ->assertSee('Cruz, Ana')->assertDontSee('Cruz, Bea')->assertDontSee('Archived by');

        $this->actingAs($this->admin)->get(route('archive.index'))->assertOk()
            ->assertSee('Cruz, Ana')->assertSee('Cruz, Bea')->assertSee('Archived by')->assertSee('Otto Other');
        $this->actingAs($this->admin)->get(route('archive.index', ['by' => $this->otherRegistrar->id]))
            ->assertSee('Cruz, Bea')->assertDontSee('Cruz, Ana');
        $this->actingAs($this->admin)->get(route('archive.index', ['search' => '0002']))
            ->assertSee('Cruz, Bea')->assertDontSee('Cruz, Ana');
        $this->actingAs($this->admin)->get(route('archive.index', ['from' => now()->addDay()->toDateString()]))
            ->assertSee('No archived items match your filters.');

        // A registrar cannot restore someone else's archive from here.
        $this->actingAs($this->registrar)->post(route('archive.restore', ['student', $theirs->id]))->assertForbidden();
    }

    public function test_students_cannot_open_the_archive_and_do_not_see_it_in_the_menu(): void
    {
        $studentUser = $this->makeUser('student');
        $this->actingAs($studentUser)->get(route('archive.index'))->assertForbidden();

        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertSee(route('archive.index'), false);
        $this->actingAs($this->makeUser('teacher'))->get(route('archive.index'))->assertOk()->assertSee('No archived items found.');
    }

    public function test_restore_puts_the_record_back_in_the_active_list(): void
    {
        $student = $this->archivedBy($this->registrar, '2026-0001', 'Ana');
        $this->actingAs($this->registrar)->get(route('students.index'))->assertDontSee('2026-0001');

        $this->actingAs($this->registrar)->post(route('archive.restore', ['student', $student->id]))
            ->assertSessionHas('success', 'Ana Cruz was restored to Student Records.');

        $this->assertFalse($student->fresh()->trashed());
        $this->assertNull($student->fresh()->archived_by);
        $this->actingAs($this->registrar)->get(route('students.index'))->assertSee('2026-0001');
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.restored']);
    }

    public function test_only_admins_delete_permanently_and_never_a_student_with_history(): void
    {
        $student = $this->archivedBy($this->registrar, '2026-0001', 'Ana');
        Guardian::create(['student_id' => $student->id, 'full_name' => 'Maria Cruz', 'relationship' => 'Mother', 'contact_number' => '0917']);

        $this->actingAs($this->registrar)->delete(route('archive.destroy', ['student', $student->id]))->assertForbidden();
        $this->actingAs($this->registrar)->get(route('archive.index'))->assertDontSee('Delete permanently');

        $this->actingAs($this->admin)->delete(route('archive.destroy', ['student', $student->id]))->assertSessionHas('success');
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('guardians', ['student_id' => $student->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.deleted']);

        // With enrollment history: kept.
        $kept = $this->archivedBy($this->registrar, '2026-0002', 'Bea');
        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31']);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'closed_at' => now()]);
        $class = ClassOffering::create(['term_id' => $term->id, 'subject_id' => Subject::create(['code' => 'CS1', 'title' => 'CS', 'units' => 3])->id,
            'section' => 'A', 'class_code' => 'C1', 'teacher_id' => $this->makeUser('teacher')->id, 'capacity' => 40]);
        Enrollment::create(['student_id' => $kept->id, 'class_offering_id' => $class->id, 'status' => 'enrolled', 'requested_at' => now(), 'enrolled_at' => now()]);

        $this->actingAs($this->admin)->delete(route('archive.destroy', ['student', $kept->id]))->assertSessionHas('error');
        $this->assertTrue(Student::withTrashed()->find($kept->id)->trashed());
    }

    public function test_the_page_filters_live_and_asks_before_restoring(): void
    {
        $this->archivedBy($this->registrar, '2026-0001', 'Ana');

        $this->actingAs($this->admin)->get(route('archive.index'))
            ->assertSee('data-live-filter', false)->assertSee('data-live-region="results"', false)
            ->assertSee('data-bs-target="#confirmModal"', false)->assertSee('Restore Cruz, Ana?')
            ->assertSee('Delete Cruz, Ana permanently?');
    }
}
