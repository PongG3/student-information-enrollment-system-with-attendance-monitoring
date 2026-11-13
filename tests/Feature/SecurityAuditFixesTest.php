<?php

namespace Tests\Feature;

use App\Models\AbsenceExcuse;
use App\Models\AcademicYear;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Notifications\Notice;
use App\Services\AttendanceService;
use App\Services\EnrollmentWorkflow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fixes from the security and logic audit: one-use password links, drop dates, sandboxed excuse files. */
class SecurityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $registrar;
    private User $teacher;
    private Student $ana;
    private ClassOffering $class;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 10:00:00');
        Notification::fake();

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true, 'enrollment_open' => true]);
        $program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        $this->admin = $this->makeUser('admin');
        $this->registrar = $this->makeUser('registrar');
        $this->teacher = $this->makeUser('teacher');
        $anaUser = $this->makeUser('student');

        $subject = Subject::create(['code' => 'CS101', 'title' => 'Intro to Computing', 'units' => 3]);
        $this->class = ClassOffering::create(['term_id' => $term->id, 'subject_id' => $subject->id, 'section' => 'A', 'class_code' => 'CL-1001', 'teacher_id' => $this->teacher->id, 'capacity' => 40]);
        foreach ([1, 3] as $day) {
            $this->class->meetings()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'Room 101']);
        }
        $this->ana = Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $program->id, 'year_level' => 1, 'status' => 'active', 'user_id' => $anaUser->id]);
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

    public function test_the_account_email_has_a_one_use_link_and_never_the_password(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), ['name' => 'New Teacher', 'email' => 'new@school.test', 'role' => 'teacher']);

        $temporary = session('temp_password');
        $this->assertNotEmpty($temporary);

        $url = null;
        Notification::assertSentOnDemand(Notice::class, function (Notice $notice) use (&$url, $temporary) {
            $this->assertStringNotContainsString($temporary, implode(' ', $notice->lines));
            $url = $notice->actionUrl;

            return true;
        });
        $this->assertStringContainsString('set-password', $url);

        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Choose your password');

        $this->post($url, ['password' => 'ShortPass1', 'password_confirmation' => 'ShortPass1'])->assertSessionHasErrors('password');
        $this->post($url, ['password' => 'A-Long-Passw0rd-Here', 'password_confirmation' => 'A-Long-Passw0rd-Here'])->assertRedirect(route('login'));

        $user = User::where('email', 'new@school.test')->first();
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('A-Long-Passw0rd-Here', $user->password));

        // Used once: the same link no longer works.
        $this->get($url)->assertForbidden();
    }

    public function test_a_changed_or_unsigned_link_is_refused(): void
    {
        $user = $this->makeUser('teacher');
        $url = \App\Support\Notify::setPasswordUrl($user);

        $this->get($url . 'x')->assertForbidden();
        $this->get(route('password.set', $user) . '?v=' . \App\Support\Notify::fingerprint($user))->assertForbidden();
    }

    public function test_a_dropped_student_stays_on_sheets_from_before_the_drop(): void
    {
        $enrollment = Enrollment::create(['student_id' => $this->ana->id, 'class_offering_id' => $this->class->id, 'status' => 'enrolled', 'requested_at' => '2026-11-01', 'enrolled_at' => '2026-11-02 09:00:00']);

        EnrollmentWorkflow::cancel($enrollment, $this->registrar, 'Withdrew from the course');
        $enrollment->refresh();
        $this->assertSame('cancelled', $enrollment->status);
        $this->assertNotNull($enrollment->enrolled_at);
        $this->assertNotNull($enrollment->dropped_at);

        $before = AttendanceService::eligible($this->class, Carbon::parse('2026-11-04'));
        $this->assertTrue($before->contains('id', $this->ana->id), 'class day before the drop');

        $after = AttendanceService::eligible($this->class, Carbon::parse('2026-11-11'));
        $this->assertFalse($after->contains('id', $this->ana->id), 'class day after the drop');

        $earlier = AttendanceService::eligible($this->class, Carbon::parse('2026-10-28'));
        $this->assertFalse($earlier->contains('id', $this->ana->id), 'class day before joining');
    }

    public function test_a_request_that_was_never_enrolled_has_no_drop_date_and_reopening_clears_it(): void
    {
        $enrollment = Enrollment::create(['student_id' => $this->ana->id, 'class_offering_id' => $this->class->id, 'status' => 'pending', 'requested_at' => now()]);
        EnrollmentWorkflow::cancel($enrollment, $this->registrar, 'Changed my mind');
        $this->assertNull($enrollment->fresh()->dropped_at);
        $this->assertFalse(AttendanceService::eligible($this->class, today())->contains('id', $this->ana->id));
    }

    public function test_a_missing_reason_is_a_normal_validation_error(): void
    {
        $enrollment = Enrollment::create(['student_id' => $this->ana->id, 'class_offering_id' => $this->class->id, 'status' => 'pending', 'requested_at' => now()]);

        $this->actingAs($this->registrar)->post(route('enrollments.reject', $enrollment), ['reason' => 'no'])
            ->assertSessionHasErrors('enrollment');
        $this->assertSame('pending', $enrollment->fresh()->status);
    }

    public function test_excuse_files_are_served_sandboxed(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('excuse-letters/note.pdf', '%PDF-1.4 test');
        $excuse = AbsenceExcuse::create([
            'student_id' => $this->ana->id, 'class_offering_id' => $this->class->id, 'date' => '2026-11-04',
            'reason' => 'I had a fever and stayed home.', 'file_path' => 'excuse-letters/note.pdf', 'file_name' => 'note.pdf', 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('excuses.file', $excuse))->assertOk();
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_changing_a_users_email_signs_them_out_elsewhere(): void
    {
        config(['session.driver' => 'database']);
        \Illuminate\Support\Facades\DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $this->teacher->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->admin)->put(route('users.update', $this->teacher), ['name' => $this->teacher->name, 'email' => 'moved@school.test', 'role' => 'teacher'])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
    }
}
