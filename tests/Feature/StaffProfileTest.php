<?php

namespace Tests\Feature;

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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "My Profile" for staff: work details and photo; students see a teacher's name, photo and department only. */
class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $registrar;
    private User $studentUser;
    private ClassOffering $class;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 10:00:00');

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true, 'enrollment_open' => true]);
        $program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);

        $this->teacher = $this->makeUser('teacher', ['name' => 'Maria Santos']);
        $this->registrar = $this->makeUser('registrar');
        $this->studentUser = $this->makeUser('student');

        $subject = Subject::create(['code' => 'CS101', 'title' => 'Intro to Computing', 'units' => 3]);
        $this->class = ClassOffering::create(['term_id' => $term->id, 'subject_id' => $subject->id, 'section' => 'A', 'class_code' => 'CL-1001', 'teacher_id' => $this->teacher->id, 'capacity' => 40]);
        foreach ([1, 3] as $day) {
            $this->class->meetings()->create(['day_of_week' => $day, 'start_time' => '13:00', 'end_time' => '14:30', 'room' => 'Room 101']);
        }

        $student = Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $program->id, 'year_level' => 1, 'status' => 'active', 'user_id' => $this->studentUser->id]);
        Enrollment::create(['student_id' => $student->id, 'class_offering_id' => $this->class->id, 'status' => 'enrolled', 'requested_at' => now(), 'enrolled_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $role, array $extra = []): User
    {
        $user = User::factory()->create();
        $user->forceFill(array_merge(['role' => $role, 'is_active' => true, 'must_change_password' => false], $extra))->save();

        return $user;
    }

    public function test_a_teacher_sees_their_profile_and_teaching_load(): void
    {
        $this->actingAs($this->teacher)->get(route('profile.show'))->assertOk()
            ->assertSee('Maria Santos')->assertSee('Account')->assertSee('Work details')
            ->assertSee('class="profile-lock"', false)
            ->assertSeeInOrder(['This term', '1', 'Classes', '1', 'Students', '3', 'Hours a week'])
            ->assertSee('CS101');

        // The account menu links to it.
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertSee(route('profile.show'), false)->assertSee('My Profile');
    }

    public function test_teachers_and_the_registrar_have_my_profile_in_the_sidebar(): void
    {
        foreach (['teacher', 'registrar'] as $role) {
            $this->assertContains('profile.show', array_column(config("sidebar.{$role}.items"), 'route'), "{$role} sidebar");
        }

        // Highlighted while on the page.
        $this->actingAs($this->teacher)->get(route('profile.show'))->assertOk()
            ->assertSee('href="' . route('profile.show') . '"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_the_registrar_has_a_profile_without_a_teaching_load(): void
    {
        $this->actingAs($this->registrar)->get(route('profile.show'))->assertOk()
            ->assertSee('Registrar')->assertSee('Your recent activity')->assertDontSee('Hours a week');
    }

    public function test_staff_update_their_work_details_but_not_their_account(): void
    {
        $this->actingAs($this->teacher)->put(route('profile.update'), [
            'department' => 'Computer Science', 'office' => 'Faculty Room 2', 'contact_number' => '0917 123 4567',
            'name' => 'Someone Else', 'email' => 'other@example.com', 'role' => 'admin',
        ])->assertRedirect(route('profile.show'))->assertSessionHas('success', 'Profile saved.');

        $teacher = $this->teacher->fresh();
        $this->assertSame('Computer Science', $teacher->department);
        $this->assertSame('Faculty Room 2', $teacher->office);
        $this->assertSame('Maria Santos', $teacher->name);
        $this->assertSame('teacher', $teacher->role);
        $this->assertTrue(AuditLog::where('action', 'profile.updated')->exists());

        $this->actingAs($this->teacher)->put(route('profile.update'), ['contact_number' => 'call me'])->assertSessionHasErrors('contact_number');
        $this->actingAs($this->studentUser)->get(route('profile.show'))->assertForbidden();
    }

    public function test_a_staff_photo_is_saved_at_once_and_shown_in_the_top_bar(): void
    {
        Storage::fake('local');

        $this->actingAs($this->teacher)->post(route('profile.photo'), ['photo' => UploadedFile::fake()->image('me.jpg', 400, 400)])
            ->assertSessionHas('success', 'Photo updated.');
        $teacher = $this->teacher->fresh();
        $this->assertNotNull($teacher->photo_path);

        $this->actingAs($teacher)->get(route('profile.show'))->assertSee('class="avatar avatar-photo"', false);
        $this->actingAs($this->studentUser)->get(route('staff.photo', $teacher))->assertOk();

        $this->actingAs($teacher)->delete(route('profile.photo.remove'));
        $this->assertNull($teacher->fresh()->photo_path);
    }

    public function test_students_see_their_teachers_name_photo_and_department_but_not_the_phone(): void
    {
        $this->teacher->forceFill(['department' => 'Computer Science', 'contact_number' => '0917 999 8888'])->save();

        $this->actingAs($this->studentUser)->get(route('student.courses'))->assertOk()
            ->assertSee('class="teacher-chip"', false)->assertSee('Maria Santos')->assertSee('Computer Science')
            ->assertDontSee('0917 999 8888');

        // A student's own photo route never serves a staff member who has none, or a student account.
        $this->actingAs($this->studentUser)->get(route('staff.photo', $this->studentUser))->assertNotFound();
    }
}
