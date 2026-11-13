<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Notifications\Notice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Emails: request decided, excuse letter decided, absence alert (student and guardians), new password. */
class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $registrar;
    private User $teacher;
    private User $anaUser;
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
        $this->registrar = $this->makeUser('registrar');
        $this->teacher = $this->makeUser('teacher');
        $this->anaUser = $this->makeUser('student', ['email' => 'ana@students.test']);

        $subject = Subject::create(['code' => 'CS101', 'title' => 'Intro to Computing', 'units' => 3]);
        $this->class = ClassOffering::create(['term_id' => $term->id, 'subject_id' => $subject->id, 'section' => 'A', 'class_code' => 'CL-1001', 'teacher_id' => $this->teacher->id, 'capacity' => 40]);
        foreach ([1, 3] as $day) {
            $this->class->meetings()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'Room 101']);
        }

        $this->ana = Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $program->id, 'year_level' => 1, 'status' => 'active', 'user_id' => $this->anaUser->id]);
        $this->ana->guardians()->create(['full_name' => 'Maria Cruz', 'relationship' => 'Mother', 'contact_number' => '0917 000 0000', 'email' => 'maria@parents.test', 'is_emergency_contact' => true]);
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

    /** Was a Notice whose subject starts with this sent to this address? */
    private function find(string $email, string $subjectStartsWith): bool
    {
        $found = false;
        Notification::assertSentOnDemand(Notice::class, function (Notice $notice, array $channels, object $notifiable) use ($email, $subjectStartsWith, &$found) {
            if (($notifiable->routes['mail'] ?? null) === $email && str_starts_with($notice->subject, $subjectStartsWith)) {
                $found = true;
            }

            return true;
        });

        return $found;
    }

    public function test_a_rejected_and_an_enrolled_request_email_the_student(): void
    {
        $rejected = Enrollment::create(['student_id' => $this->ana->id, 'class_offering_id' => $this->class->id, 'status' => 'pending', 'requested_at' => now()]);
        $this->actingAs($this->registrar)->post(route('enrollments.reject', $rejected), ['reason' => 'Prerequisite not taken yet']);
        $this->assertTrue($this->find('ana@students.test', 'Class request not approved: CS101'));

        $rejected->forceFill(['status' => 'approved'])->save();
        $this->actingAs($this->registrar)->post(route('enrollments.enroll', $rejected));
        $this->assertTrue($this->find('ana@students.test', 'Enrolled: CS101'));
    }

    public function test_going_over_the_absence_limit_emails_the_student_and_the_guardian_once(): void
    {
        Enrollment::create(['student_id' => $this->ana->id, 'class_offering_id' => $this->class->id, 'status' => 'enrolled', 'requested_at' => now(), 'enrolled_at' => now()]);
        foreach (['2026-10-26', '2026-10-28'] as $date) {
            $session = AttendanceSession::create(['class_offering_id' => $this->class->id, 'session_date' => $date, 'taken_by' => $this->teacher->id]);
            AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $this->ana->id, 'status' => 'absent', 'recorded_by' => $this->teacher->id]);
        }

        // The third absence (3 of 3 meetings) puts Ana over the limit.
        $this->actingAs($this->teacher)->post(route('attendance.save'), [
            'class_offering' => $this->class->id, 'date' => '2026-11-09', 'rows' => [$this->ana->id => ['status' => 'absent']],
        ]);
        $this->assertTrue($this->find('ana@students.test', 'Attendance alert: CS101'));
        $this->assertTrue($this->find('maria@parents.test', 'Attendance alert for Ana Cruz'));

        // Saving the same sheet again does not send it twice: she was already over.
        Notification::fake();
        $this->actingAs($this->teacher)->post(route('attendance.save'), [
            'class_offering' => $this->class->id, 'date' => '2026-11-09', 'rows' => [$this->ana->id => ['status' => 'absent']],
        ]);
        Notification::assertNothingSent();
    }

    public function test_a_decided_excuse_letter_emails_the_student_with_the_teachers_note(): void
    {
        Enrollment::create(['student_id' => $this->ana->id, 'class_offering_id' => $this->class->id, 'status' => 'enrolled', 'requested_at' => now(), 'enrolled_at' => now()]);
        $session = AttendanceSession::create(['class_offering_id' => $this->class->id, 'session_date' => '2026-11-04', 'taken_by' => $this->teacher->id]);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $this->ana->id, 'status' => 'absent', 'recorded_by' => $this->teacher->id]);
        $this->actingAs($this->anaUser)->post(route('student.attendance.excuse', $this->class), ['date' => '2026-11-04', 'reason' => 'I had a fever and stayed home.']);

        $this->actingAs($this->teacher)->post(route('attendance.save'), [
            'class_offering' => $this->class->id, 'date' => '2026-11-04',
            'rows' => [$this->ana->id => ['status' => 'absent', 'remarks' => 'Please bring a medical certificate next time.']],
        ]);

        $this->assertTrue($this->find('ana@students.test', 'Excuse letter not accepted: CS101'));
        $this->assertSame('Please bring a medical certificate next time.', \App\Models\AbsenceExcuse::first()->decision_note);
    }

    public function test_a_new_account_emails_the_temporary_password(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'New Teacher', 'email' => 'newteacher@school.test', 'role' => 'teacher']);

        $this->assertTrue($this->find('newteacher@school.test', 'Your SIES Campus Portal account'));
    }

    public function test_a_mail_failure_never_stops_the_action(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'no-such-host.invalid', 'mail.mailers.smtp.port' => 1]);
        Notification::swap(app(\Illuminate\Notifications\ChannelManager::class));   // real sending, which will fail

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Offline Mail', 'email' => 'offline@school.test', 'role' => 'teacher'])
            ->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'offline@school.test']);
    }
}
