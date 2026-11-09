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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StageITest extends TestCase
{
    use RefreshDatabase;

    // "Today" is Monday 9 November 2026. The class meets on Mondays and Wednesdays at 8:00 AM.
    private const TODAY = '2026-11-09';

    private Term $term;
    private Program $program;
    private User $teacher;
    private User $admin;
    private User $registrar;
    private ClassOffering $class;
    private ClassOffering $otherClass;
    private Student $ana;
    private Student $ben;
    private User $anaUser;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::TODAY . ' 10:00:00');

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $this->term = Term::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester',
            'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true, 'enrollment_open' => true,
        ]);
        $this->program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        $this->teacher = $this->makeUser('teacher');
        $this->admin = $this->makeUser('admin');
        $this->registrar = $this->makeUser('registrar');

        $this->class = $this->makeClass('CL-1001', 'CS101', $this->teacher);
        $this->otherClass = $this->makeClass('CL-2002', 'CS202', $this->teacher);

        $this->ana = $this->makeStudent('2026-0001', 'Ana');
        $this->ben = $this->makeStudent('2026-0002', 'Ben');
        $this->anaUser = $this->linkAccount($this->ana, 'ana@sies.test');

        $this->enroll($this->ana, $this->class, 'enrolled');
        $this->enroll($this->ben, $this->class, 'enrolled');
        $this->enroll($this->ben, $this->otherClass, 'enrolled');
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

    private function makeStudent(string $number, string $first): Student
    {
        return Student::create([
            'student_number' => $number, 'first_name' => $first, 'last_name' => 'Tester',
            'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $this->program->id,
        ]);
    }

    private function linkAccount(Student $student, string $email): User
    {
        $user = $this->makeUser('student', ['email' => $email, 'name' => $student->first_name . ' Tester']);
        $student->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    private function makeClass(string $code, string $subjectCode, User $teacher): ClassOffering
    {
        $subject = Subject::create(['code' => $subjectCode, 'title' => $subjectCode . ' title', 'units' => 3]);
        $class = ClassOffering::create([
            'term_id' => $this->term->id, 'subject_id' => $subject->id, 'section' => 'A',
            'class_code' => $code, 'teacher_id' => $teacher->id, 'capacity' => 2,
        ]);
        foreach ([1, 3] as $day) {
            $class->meetings()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'Room 101']);
        }

        return $class;
    }

    private function enroll(Student $student, ClassOffering $class, string $status, ?string $reason = null): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id, 'class_offering_id' => $class->id, 'status' => $status,
            'requested_at' => now(), 'enrolled_at' => $status === 'enrolled' ? '2026-08-10 09:00:00' : null,
            'status_reason' => $reason,
        ]);
    }

    /** One attendance sheet, written straight to the database. */
    private function mark(ClassOffering $class, string $date, array $statuses, ?string $remarks = null): void
    {
        $session = AttendanceSession::create(['class_offering_id' => $class->id, 'session_date' => $date, 'taken_by' => $this->teacher->id]);

        foreach ($statuses as $studentId => $status) {
            AttendanceRecord::create([
                'attendance_session_id' => $session->id, 'student_id' => $studentId, 'status' => $status,
                'remarks' => $remarks, 'recorded_by' => $this->teacher->id,
            ]);
        }
    }

    // ---------- Accounts are created from the student's record ----------

    public function test_the_registrar_creates_a_portal_account_from_the_student_record(): void
    {
        $response = $this->actingAs($this->registrar)->post(route('students.account.store', $this->ben), ['email' => 'Ben@Sies.test']);

        $response->assertRedirect(route('students.show', $this->ben));

        $user = User::where('email', 'ben@sies.test')->firstOrFail();
        $this->assertSame('student', $user->role);
        $this->assertSame('Ben Tester', $user->name);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertTrue((bool) $user->is_active);
        $this->assertSame($user->id, $this->ben->fresh()->user_id);
        $response->assertSessionHas('temp_password', fn ($password) => Hash::check($password, $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.account_created']);
    }

    public function test_the_temporary_password_is_shown_once_on_the_student_page(): void
    {
        $this->actingAs($this->registrar)->post(route('students.account.store', $this->ben), ['email' => 'ben@sies.test']);

        $this->actingAs($this->registrar)->get(route('students.show', $this->ben))->assertOk()
            ->assertSee('Temporary password for ben@sies.test')->assertSee('ben@sies.test');

        $this->actingAs($this->registrar)->get(route('students.show', $this->ben))->assertDontSee('Temporary password for');
    }

    public function test_account_creation_checks_the_email_and_refuses_duplicates(): void
    {
        $this->actingAs($this->registrar)->post(route('students.account.store', $this->ben), ['email' => ''])
            ->assertSessionHasErrors('email');
        $this->actingAs($this->registrar)->post(route('students.account.store', $this->ben), ['email' => 'ana@sies.test'])
            ->assertSessionHasErrors('email');

        // Ana already has an account.
        $this->actingAs($this->registrar)->post(route('students.account.store', $this->ana), ['email' => 'other@sies.test'])
            ->assertSessionHas('error');

        $this->assertNull($this->ben->fresh()->user_id);
        $this->assertDatabaseMissing('users', ['email' => 'other@sies.test']);
    }

    public function test_an_archived_student_gets_no_account(): void
    {
        $this->ben->delete();

        $this->actingAs($this->registrar)->post(route('students.account.store', $this->ben), ['email' => 'ben@sies.test'])
            ->assertNotFound();
    }

    public function test_reset_and_deactivate_work_from_the_student_record(): void
    {
        $old = $this->anaUser->password;

        $reset = $this->actingAs($this->admin)->post(route('students.account.reset', $this->ana));
        $reset->assertRedirect(route('students.show', $this->ana))->assertSessionHas('temp_password');
        $this->anaUser->refresh();
        $this->assertNotSame($old, $this->anaUser->password);
        $this->assertTrue((bool) $this->anaUser->must_change_password);

        $this->actingAs($this->admin)->post(route('students.account.status', $this->ana))->assertRedirect();
        $this->assertFalse((bool) $this->anaUser->fresh()->is_active);

        $this->actingAs($this->admin)->post(route('students.account.status', $this->ana));
        $this->assertTrue((bool) $this->anaUser->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', ['action' => 'student.account_password_reset']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.account_deactivated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.account_activated']);
    }

    public function test_only_admin_and_registrar_manage_student_accounts(): void
    {
        foreach ([$this->teacher, $this->anaUser] as $user) {
            $this->actingAs($user)->post(route('students.account.store', $this->ben), ['email' => 'x@sies.test'])->assertForbidden();
            $this->actingAs($user)->post(route('students.account.reset', $this->ana))->assertForbidden();
            $this->actingAs($user)->post(route('students.account.status', $this->ana))->assertForbidden();
        }
    }

    public function test_the_student_page_offers_the_right_account_controls(): void
    {
        $this->actingAs($this->registrar)->get(route('students.show', $this->ana))->assertOk()
            ->assertSee('Portal account')->assertSee('ana@sies.test')->assertSee('Reset password');

        $this->actingAs($this->registrar)->get(route('students.show', $this->ben))->assertOk()
            ->assertSee('No sign-in account yet')->assertSee('Create account');
    }

    // ---------- Signing in ----------

    public function test_a_student_lands_on_the_student_dashboard(): void
    {
        $this->actingAs($this->anaUser)->get('/dashboard')->assertRedirect(route('student.dashboard'));
        $this->actingAs($this->anaUser)->get(route('student.dashboard'))->assertOk()
            ->assertSee('Good morning, Ana.')->assertSee('2026-0001');
    }

    public function test_the_student_menu_lists_only_finished_pages(): void
    {
        $page = $this->actingAs($this->anaUser)->get(route('student.dashboard'));

        foreach (['Dashboard', 'My Profile', 'Available Courses', 'My Enrollments', 'My Schedule', 'My Attendance', 'Change Password'] as $label) {
            $page->assertSee($label);
        }
        $page->assertDontSee('Audit Logs')->assertDontSee('Student Records');
    }

    public function test_a_student_account_without_a_record_is_signed_out(): void
    {
        $orphan = $this->makeUser('student');

        $this->actingAs($orphan)->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_archived_students_account_stops_working_until_restored(): void
    {
        $this->ana->delete();

        $this->actingAs($this->anaUser)->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->ana->restore();
        $this->actingAs($this->anaUser)->get(route('student.dashboard'))->assertOk();
    }

    public function test_a_deactivated_account_is_signed_out(): void
    {
        $this->anaUser->forceFill(['is_active' => false])->save();

        $this->actingAs($this->anaUser)->get(route('student.dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_new_student_must_choose_a_password_first(): void
    {
        $this->anaUser->forceFill(['must_change_password' => true])->save();

        $this->actingAs($this->anaUser)->get(route('student.dashboard'))->assertRedirect(route('account.password'));
        $this->actingAs($this->anaUser)->get(route('account.password'))->assertOk();
    }

    public function test_a_student_can_change_their_own_password(): void
    {
        $this->anaUser->forceFill(['password' => Hash::make('Old-Password-1'), 'must_change_password' => true])->save();

        $this->actingAs($this->anaUser)->put(route('account.password.update'), [
            'current_password' => 'Old-Password-1', 'password' => 'New-Password-22', 'password_confirmation' => 'New-Password-22',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('New-Password-22', $this->anaUser->fresh()->password));
        $this->assertFalse((bool) $this->anaUser->fresh()->must_change_password);
    }

    // ---------- Keeping roles apart ----------

    public function test_a_student_cannot_open_staff_pages(): void
    {
        foreach (['/students', '/enrollments', '/classes', '/attendance', '/reports', '/subjects', '/users', '/audit-logs', '/settings', '/admin/dashboard', '/teacher/classes'] as $url) {
            $this->actingAs($this->anaUser)->get($url)->assertForbidden();
        }

        $this->actingAs($this->anaUser)->get(route('students.show', $this->ben))->assertForbidden();
        $this->actingAs($this->anaUser)->get(route('students.photo', $this->ben))->assertForbidden();
        $this->actingAs($this->anaUser)->post(route('enrollments.approve', $this->enroll($this->ana, $this->otherClass, 'pending')))->assertForbidden();
    }

    public function test_staff_cannot_open_the_student_portal(): void
    {
        foreach ([$this->admin, $this->registrar, $this->teacher] as $user) {
            foreach (['dashboard', 'profile', 'courses', 'enrollments', 'schedule', 'attendance'] as $page) {
                $this->actingAs($user)->get("/student/{$page}")->assertForbidden();
            }
        }
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->app['auth']->forgetGuards();

        $this->get('/student/dashboard')->assertRedirect('/login');
    }

    public function test_the_portal_is_read_only(): void
    {
        foreach (['dashboard', 'profile', 'courses', 'enrollments', 'schedule', 'attendance', 'attendance/' . $this->class->id] as $page) {
            foreach (['post', 'put', 'patch', 'delete'] as $method) {
                $this->actingAs($this->anaUser)->{$method}("/student/{$page}")->assertStatus(405);
            }
        }

        $this->assertSame(3, Enrollment::count());
    }

    // ---------- Pages show the student's own data ----------

    public function test_every_portal_page_opens(): void
    {
        foreach (['dashboard', 'profile', 'courses', 'enrollments', 'schedule', 'attendance'] as $page) {
            $this->actingAs($this->anaUser)->get("/student/{$page}")->assertOk();
        }
    }

    public function test_the_dashboard_shows_todays_class_and_totals(): void
    {
        $this->actingAs($this->anaUser)->get(route('student.dashboard'))->assertOk()
            ->assertSee('Today')->assertSee('CS101')->assertSee('8:00 AM')->assertSee('Room 101')
            ->assertSee('1st Semester')->assertDontSee('CS202');
    }

    public function test_the_profile_shows_the_students_own_record(): void
    {
        $this->actingAs($this->anaUser)->get(route('student.profile'))->assertOk()
            ->assertSee('Tester, Ana')->assertSee('2026-0001')->assertSee('BS Computer Science')
            ->assertDontSee('2026-0002');
    }

    public function test_the_profile_shows_requirement_progress(): void
    {
        $photo = \App\Models\Requirement::create(['name' => '2x2 ID Photo']);
        $psa = \App\Models\Requirement::create(['name' => 'PSA Birth Certificate']);
        \App\Models\Requirement::create(['name' => 'Good Moral Certificate']);
        \App\Models\StudentRequirement::create(['student_id' => $this->ana->id, 'requirement_id' => $photo->id, 'status' => 'verified']);
        \App\Models\StudentRequirement::create(['student_id' => $this->ana->id, 'requirement_id' => $psa->id, 'status' => 'submitted']);

        $this->actingAs($this->anaUser)->get(route('student.profile'))->assertOk()
            ->assertSee('2 of 3 submitted')->assertSee('1 verified')
            ->assertSee("Bring the pending documents to the registrar's office", false)
            ->assertSee('Block section');
    }

    public function test_courses_show_seats_and_my_status(): void
    {
        $this->actingAs($this->anaUser)->get(route('student.courses'))->assertOk()
            ->assertSee('CL-1001')->assertSee('CL-2002')
            ->assertSee('Full')            // CL-1001: capacity 2, Ana and Ben hold the seats
            ->assertSee('1 of 2')          // CL-2002: only Ben holds a seat
            ->assertSee('Enrolled');       // Ana's own status on CL-1001
    }

    public function test_courses_can_be_searched(): void
    {
        $this->actingAs($this->anaUser)->get(route('student.courses', ['q' => 'CS202']))->assertOk()
            ->assertSee('CL-2002')->assertDontSee('CL-1001');
    }

    public function test_cancelled_classes_are_not_offered(): void
    {
        $this->otherClass->forceFill(['status' => 'cancelled'])->save();

        $this->actingAs($this->anaUser)->get(route('student.courses'))->assertOk()->assertDontSee('CL-2002');
    }

    public function test_enrollments_show_every_status_with_its_reason(): void
    {
        $this->enroll($this->ana, $this->otherClass, 'rejected', 'Schedule clash with another subject');

        $this->actingAs($this->anaUser)->get(route('student.enrollments'))->assertOk()
            ->assertSee('CL-1001')->assertSee('Enrolled')
            ->assertSee('CL-2002')->assertSee('Rejected')->assertSee('Schedule clash with another subject');
    }

    public function test_enrollments_never_show_other_students(): void
    {
        $this->actingAs($this->anaUser)->get(route('student.enrollments', ['term' => 'all']))->assertOk()
            ->assertDontSee('2026-0002')->assertDontSee('Ben');
    }

    public function test_the_schedule_lists_only_enrolled_classes_by_day(): void
    {
        $this->enroll($this->ana, $this->otherClass, 'approved');   // held seat, not enrolled yet

        $this->actingAs($this->anaUser)->get(route('student.schedule'))->assertOk()
            ->assertSee('Monday')->assertSee('Wednesday')->assertDontSee('Tuesday')
            ->assertSee('CS101')->assertSee('8:00 AM')->assertSee('9:30 AM')
            ->assertDontSee('CS202');
    }

    public function test_a_student_with_no_classes_sees_friendly_empty_pages(): void
    {
        $newcomer = $this->linkAccount($this->makeStudent('2026-0003', 'Cy'), 'cy@sies.test');

        $this->actingAs($newcomer)->get(route('student.schedule'))->assertOk()->assertSee('no scheduled classes');
        $this->actingAs($newcomer)->get(route('student.attendance'))->assertOk()->assertSee('not enrolled in any class');
        $this->actingAs($newcomer)->get(route('student.enrollments'))->assertOk()->assertSee('No class requests');
        $this->actingAs($newcomer)->get(route('student.dashboard'))->assertOk()->assertSee('No classes today');
    }

    public function test_the_portal_works_when_there_is_no_current_term(): void
    {
        Term::query()->update(['is_current' => false]);

        foreach (['dashboard', 'profile', 'courses', 'enrollments', 'schedule', 'attendance'] as $page) {
            $this->actingAs($this->anaUser)->get("/student/{$page}")->assertOk();
        }
    }

    // ---------- Attendance ----------

    public function test_attendance_percentages_follow_the_policy(): void
    {
        // Present, Late, Absent: Late counts as attended, so 2 of 3 = 66.7%.
        $this->mark($this->class, '2026-11-02', [$this->ana->id => 'present']);
        $this->mark($this->class, '2026-11-04', [$this->ana->id => 'late']);
        $this->mark($this->class, '2026-11-09', [$this->ana->id => 'absent', $this->ben->id => 'present']);

        $this->actingAs($this->anaUser)->get(route('student.attendance'))->assertOk()
            ->assertSee('66.7%')->assertSee('Frequent absence');
    }

    public function test_excused_meetings_are_left_out_of_the_percentage(): void
    {
        $this->mark($this->class, '2026-11-02', [$this->ana->id => 'present']);
        $this->mark($this->class, '2026-11-04', [$this->ana->id => 'excused']);

        $this->actingAs($this->anaUser)->get(route('student.attendance'))->assertOk()->assertSee('100%');
    }

    public function test_the_class_page_lists_dated_records_with_remarks(): void
    {
        $this->mark($this->class, '2026-11-04', [$this->ana->id => 'excused'], 'Doctor appointment');
        $this->mark($this->class, '2026-11-09', [$this->ana->id => 'present', $this->ben->id => 'absent']);

        $this->actingAs($this->anaUser)->get(route('student.attendance.show', $this->class))->assertOk()
            ->assertSee('Wed, Nov 4, 2026')->assertSee('Mon, Nov 9, 2026')
            ->assertSee('Doctor appointment')->assertSeeInOrder(['Nov 9, 2026', 'Nov 4, 2026']);
    }

    public function test_a_student_cannot_open_the_attendance_of_a_class_they_are_not_in(): void
    {
        $this->mark($this->otherClass, '2026-11-09', [$this->ben->id => 'absent']);

        $this->actingAs($this->anaUser)->get(route('student.attendance.show', $this->otherClass))->assertNotFound();
        $this->actingAs($this->anaUser)->get(route('student.attendance.show', 9999))->assertNotFound();
    }

    public function test_a_class_where_the_request_is_only_pending_is_not_open_either(): void
    {
        $this->enroll($this->ana, $this->otherClass, 'pending');

        $this->actingAs($this->anaUser)->get(route('student.attendance.show', $this->otherClass))->assertNotFound();
    }

    public function test_other_students_attendance_is_never_shown(): void
    {
        $this->mark($this->class, '2026-11-09', [$this->ana->id => 'present', $this->ben->id => 'absent']);

        $page = $this->actingAs($this->anaUser)->get(route('student.attendance.show', $this->class));
        $page->assertOk()->assertDontSee('Ben')->assertDontSee('2026-0002');
        $this->assertStringNotContainsString('Absent', $this->tableBody($page->getContent()));
    }

    /** The meetings table of the class page (the stat cards also say "Absent"). */
    private function tableBody(string $html): string
    {
        $start = strpos($html, '<tbody>');
        $end = strpos($html, '</tbody>');

        return $start === false ? '' : substr($html, $start, $end - $start);
    }

    public function test_students_can_see_their_history_for_an_earlier_term(): void
    {
        $oldYear = AcademicYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-05-31']);
        $oldTerm = Term::create(['academic_year_id' => $oldYear->id, 'name' => '2nd Semester', 'start_date' => '2026-01-05', 'end_date' => '2026-05-20', 'closed_at' => now()]);
        $subject = Subject::create(['code' => 'MA101', 'title' => 'Old maths', 'units' => 3]);
        $oldClass = ClassOffering::create(['term_id' => $oldTerm->id, 'subject_id' => $subject->id, 'section' => 'A', 'class_code' => 'CL-0900', 'teacher_id' => $this->teacher->id, 'capacity' => 30]);
        $oldClass->meetings()->create(['day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '11:30']);
        $this->enroll($this->ana, $oldClass, 'enrolled');

        $this->actingAs($this->anaUser)->get(route('student.enrollments', ['term' => $oldTerm->id]))->assertOk()
            ->assertSee('CL-0900')->assertDontSee('CL-1001');
        $this->actingAs($this->anaUser)->get(route('student.schedule', ['term' => $oldTerm->id]))->assertOk()
            ->assertSee('MA101')->assertSee('Tuesday');
        $this->actingAs($this->anaUser)->get(route('student.enrollments', ['term' => 'all']))->assertOk()
            ->assertSee('CL-0900')->assertSee('CL-1001');
    }

    public function test_a_term_the_student_has_no_part_in_is_ignored(): void
    {
        $oldYear = AcademicYear::create(['name' => '2024-2025', 'start_date' => '2024-08-01', 'end_date' => '2025-05-31']);
        $stranger = Term::create(['academic_year_id' => $oldYear->id, 'name' => 'Other Semester', 'start_date' => '2025-01-05', 'end_date' => '2025-05-20']);

        // Falls back to the current term instead of showing an unrelated one.
        $this->actingAs($this->anaUser)->get(route('student.schedule', ['term' => $stranger->id]))->assertOk()
            ->assertSee('CS101')->assertDontSee('Other Semester');
    }
}
