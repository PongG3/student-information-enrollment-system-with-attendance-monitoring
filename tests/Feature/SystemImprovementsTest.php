<?php

namespace Tests\Feature;

use App\Models\AbsenceExcuse;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\NonClassDay;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\MissingSheets;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The system review: missed sheets, bulk enrollment, CSV import, excuse letters, privacy consent,
 * phone-first attendance sheet and the attendance rule shown with percentages.
 */
class SystemImprovementsTest extends TestCase
{
    use RefreshDatabase;

    // "Today" is Monday 9 November 2026, 10:00. Classes meet Mondays and Wednesdays at 8:00.
    private const TODAY = '2026-11-09';

    private Term $term;
    private Program $program;
    private User $teacher;
    private User $otherTeacher;
    private User $registrar;
    private User $admin;
    private ClassOffering $class;
    private ClassOffering $otherClass;
    private Student $ana;
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
        $this->otherTeacher = $this->makeUser('teacher');
        $this->registrar = $this->makeUser('registrar');
        $this->admin = $this->makeUser('admin');

        $this->class = $this->makeClass('CL-1001', 'CS101', $this->teacher, 2);
        $this->otherClass = $this->makeClass('CL-2002', 'CS202', $this->otherTeacher, 1);

        $this->ana = $this->makeStudent('2026-0001', 'Ana');
        $this->anaUser = $this->makeUser('student', ['email' => 'ana@sies.test']);
        $this->ana->forceFill(['user_id' => $this->anaUser->id])->save();
        $this->enroll($this->ana, $this->class, 'enrolled');
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
            'student_number' => $number, 'first_name' => $first, 'last_name' => 'Tester', 'status' => 'active',
            'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $this->program->id, 'year_level' => 1,
        ]);
    }

    private function makeClass(string $code, string $subjectCode, User $teacher, int $capacity): ClassOffering
    {
        $subject = Subject::create(['code' => $subjectCode, 'title' => $subjectCode . ' title', 'units' => 3]);
        $class = ClassOffering::create([
            'term_id' => $this->term->id, 'subject_id' => $subject->id, 'section' => 'A',
            'class_code' => $code, 'teacher_id' => $teacher->id, 'capacity' => $capacity,
        ]);
        foreach ([1, 3] as $day) {
            $class->meetings()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'Room 101']);
        }

        return $class;
    }

    private function enroll(Student $student, ClassOffering $class, string $status): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id, 'class_offering_id' => $class->id, 'status' => $status,
            'requested_at' => now(), 'enrolled_at' => $status === 'enrolled' ? now() : null,
        ]);
    }

    private function mark(ClassOffering $class, string $date, array $statuses): void
    {
        $session = AttendanceSession::create(['class_offering_id' => $class->id, 'session_date' => $date, 'taken_by' => $class->teacher_id]);
        foreach ($statuses as $studentId => $status) {
            AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $studentId, 'status' => $status, 'recorded_by' => $class->teacher_id]);
        }
    }

    // ---------- Missed past sheets ----------

    public function test_past_meetings_without_a_sheet_are_counted_skipping_holidays_and_today(): void
    {
        // Last two weeks: Mon Oct 26, Wed Oct 28, Mon Nov 2, Wed Nov 4. Today (Mon Nov 9) is not counted.
        NonClassDay::create(['date' => '2026-11-04', 'description' => 'Typhoon']);
        $this->mark($this->class, '2026-10-26', []);

        $this->assertSame(2, MissingSheets::recentCount($this->term->id, $this->teacher->id));

        $dates = MissingSheets::find(ClassOffering::with(['term', 'meetings'])->whereKey($this->class->id)->get(), Carbon::parse('2026-10-26'))
            ->map(fn ($row) => $row['date']->toDateString())->all();
        $this->assertSame(['2026-10-28', '2026-11-02'], $dates);
    }

    public function test_the_teacher_is_told_about_missed_sheets_and_the_report_lists_them(): void
    {
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk()
            ->assertSee('past class meetings have no attendance sheet (last 2 weeks)');

        $this->actingAs($this->teacher)->get(route('reports.show', ['missing-sheets', 'from' => '2026-10-26']))->assertOk()
            ->assertSee('Missing attendance sheets')->assertSee('2026-11-04')->assertSee('CL-1001')
            ->assertDontSee('CL-2002');   // another teacher's class
    }

    // ---------- Bulk approve / enroll ----------

    public function test_several_requests_are_approved_at_once_and_a_full_class_is_reported(): void
    {
        $ben = $this->makeStudent('2026-0002', 'Ben');
        $cara = $this->makeStudent('2026-0003', 'Cara');
        $dan = $this->makeStudent('2026-0004', 'Dan');
        $one = $this->enroll($ben, $this->otherClass, 'pending');
        $two = $this->enroll($cara, $this->otherClass, 'pending');   // capacity 1: the second does not fit
        $three = $this->enroll($dan, $this->class, 'pending');

        $this->actingAs($this->registrar)->from(route('enrollments.index'))
            ->post(route('enrollments.bulk'), ['action' => 'approve', 'ids' => [$one->id, $two->id, $three->id]])
            ->assertRedirect(route('enrollments.index'))
            ->assertSessionHas('success', '2 requests approved.')
            ->assertSessionHasErrors('enrollment');

        $this->assertSame('approved', $one->fresh()->status);
        $this->assertSame('pending', $two->fresh()->status);
        $this->assertSame('approved', $three->fresh()->status);

        $this->actingAs($this->registrar)->post(route('enrollments.bulk'), ['action' => 'enroll', 'ids' => [$one->id, $three->id]])
            ->assertSessionHas('success', '2 requests enrolled.');
        $this->assertSame('enrolled', $three->fresh()->status);
    }

    public function test_the_requests_page_offers_bulk_actions_and_teachers_cannot_use_them(): void
    {
        $request = $this->enroll($this->makeStudent('2026-0005', 'Eli'), $this->class, 'pending');

        $this->actingAs($this->registrar)->get(route('enrollments.index'))->assertOk()
            ->assertSee('Approve selected')->assertSee('Enroll selected')->assertSee('name="ids[]" value="' . $request->id . '"', false);

        $this->actingAs($this->teacher)->post(route('enrollments.bulk'), ['action' => 'approve', 'ids' => [$request->id]])->assertForbidden();
    }

    // ---------- CSV import ----------

    private function csv(array $rows): UploadedFile
    {
        $lines = [implode(',', array_keys(\App\Http\Controllers\StudentImportController::COLUMNS))];
        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        return UploadedFile::fake()->createWithContent('students.csv', implode("\n", $lines) . "\n");
    }

    public function test_students_are_imported_after_a_checked_preview(): void
    {
        $file = $this->csv([
            ['Reyes', 'Lia', '', '', 'female', '2008-02-03', 'bscs', '1', 'b', 'new', '', '', '', '', 'Rosa Reyes', 'Mother', '0917 111 2222'],
            ['Tester', 'Ana', '', '', 'Female', '2006-01-01', 'BSCS', '1', '', 'new', '', '', '', '', 'Guardian', 'Father', '0917 111 3333'],   // already registered
            ['Cruz', '', '', '', 'Male', '03/04/2008', 'XYZ', '9', '', 'old', '', '', '', '', '', '', ''],   // many errors
        ]);

        $this->actingAs($this->registrar)->post(route('students.import.preview'), ['file' => $file])
            ->assertRedirect(route('students.import', ['preview' => 1]));

        $this->actingAs($this->registrar)->get(route('students.import', ['preview' => 1]))->assertOk()
            ->assertSee('1 ready')->assertSee('2 with errors')
            ->assertSee('Already registered (same name and birth date).')
            ->assertSee('Birth date must look like 2008-05-14.')
            ->assertSee('Unknown program code.');

        // Consent is required before anything is saved.
        $this->actingAs($this->registrar)->post(route('students.import.store'))->assertSessionHasErrors('consent');
        $this->assertSame(0, Student::where('last_name', 'Reyes')->count());

        $this->actingAs($this->registrar)->post(route('students.import.store'), ['consent' => 1])
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('success', '1 student registered. 2 rows were skipped because of errors.');

        $lia = Student::where('last_name', 'Reyes')->firstOrFail();
        $this->assertSame('Female', $lia->gender);
        $this->assertSame('B', $lia->section);
        $this->assertNotNull($lia->privacy_consent_at);
        $this->assertNotNull($lia->student_number);
        $this->assertTrue($lia->guardians()->first()->is_emergency_contact);
    }

    public function test_a_file_without_the_required_columns_is_refused_and_the_template_downloads(): void
    {
        $bad = UploadedFile::fake()->createWithContent('x.csv', "name,age\nAna,18\n");
        $this->actingAs($this->registrar)->from(route('students.import'))->post(route('students.import.preview'), ['file' => $bad])
            ->assertSessionHasErrors('file');

        $response = $this->actingAs($this->registrar)->get(route('students.import.template'))->assertOk();
        $this->assertStringStartsWith('last_name,first_name', $response->streamedContent());

        $this->actingAs($this->teacher)->get(route('students.import'))->assertForbidden();
    }

    // ---------- Excuse letters ----------

    public function test_a_student_sends_an_excuse_letter_and_the_teacher_accepts_it_on_the_sheet(): void
    {
        Storage::fake('local');
        $this->mark($this->class, '2026-11-04', [$this->ana->id => 'absent']);

        $this->actingAs($this->anaUser)->get(route('student.attendance.show', $this->class))->assertOk()
            ->assertSee('Excuse letter')->assertSee('data-excuse-date="2026-11-04"', false);

        $this->actingAs($this->anaUser)->post(route('student.attendance.excuse', $this->class), [
            'date' => '2026-11-04', 'reason' => 'I had a fever and saw a doctor.',
            'file' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
        ])->assertSessionHas('success');

        $excuse = AbsenceExcuse::firstOrFail();
        $this->assertSame('pending', $excuse->status);
        Storage::disk('local')->assertExists($excuse->file_path);

        // The teacher is told, and sees the letter on that day's sheet.
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertSee('excuse letter is waiting for your decision');
        $this->actingAs($this->teacher)->get(route('attendance.sheet', ['class_offering' => $this->class->id, 'date' => '2026-11-04']))->assertOk()
            ->assertSee('I had a fever and saw a doctor.')->assertSee('View file')->assertSee('1 excuse letter waiting.');
        $this->actingAs($this->teacher)->get(route('excuses.file', $excuse))->assertOk();

        // Saving the student as Excused accepts it.
        $this->actingAs($this->teacher)->post(route('attendance.save'), [
            'class_offering' => $this->class->id, 'date' => '2026-11-04',
            'rows' => [$this->ana->id => ['status' => 'excused', 'remarks' => 'Excuse letter']],
        ])->assertRedirect();

        $this->assertSame('accepted', $excuse->fresh()->status);
        $this->assertSame($this->teacher->id, $excuse->fresh()->reviewed_by);

        // Once decided it cannot be changed by the student.
        $this->actingAs($this->anaUser)->post(route('student.attendance.excuse', $this->class), ['date' => '2026-11-04', 'reason' => 'Changing my mind here.'])
            ->assertSessionHasErrors('excuse');
    }

    public function test_saving_another_status_declines_the_letter(): void
    {
        $this->mark($this->class, '2026-11-04', [$this->ana->id => 'absent']);
        $this->actingAs($this->anaUser)->post(route('student.attendance.excuse', $this->class), ['date' => '2026-11-04', 'reason' => 'Overslept, sorry about that.']);

        $this->actingAs($this->teacher)->post(route('attendance.save'), [
            'class_offering' => $this->class->id, 'date' => '2026-11-04',
            'rows' => [$this->ana->id => ['status' => 'absent']],
        ]);

        $this->assertSame('declined', AbsenceExcuse::firstOrFail()->status);
    }

    public function test_letters_are_only_for_absences_and_files_are_private(): void
    {
        Storage::fake('local');
        $this->mark($this->class, '2026-11-02', [$this->ana->id => 'present']);
        $this->mark($this->class, '2026-11-04', [$this->ana->id => 'late']);

        $this->actingAs($this->anaUser)->post(route('student.attendance.excuse', $this->class), ['date' => '2026-11-02', 'reason' => 'I was actually there.'])
            ->assertSessionHasErrors('excuse');

        $this->actingAs($this->anaUser)->post(route('student.attendance.excuse', $this->class), [
            'date' => '2026-11-04', 'reason' => 'The jeepney broke down on the way.',
            'file' => UploadedFile::fake()->create('note.pdf', 50, 'application/pdf'),
        ])->assertSessionHas('success');

        $excuse = AbsenceExcuse::firstOrFail();
        $this->actingAs($this->otherTeacher)->get(route('excuses.file', $excuse))->assertNotFound();
        $this->actingAs($this->anaUser)->get(route('excuses.file', $excuse))->assertOk();
    }

    // ---------- Privacy ----------

    public function test_the_privacy_notice_is_public_and_linked_from_sign_in(): void
    {
        $this->get(route('privacy'))->assertOk()->assertSee('<title>Privacy notice · SIES Campus Portal</title>', false)->assertSee('Republic Act No. 10173');
        $this->get(route('login'))->assertOk()->assertSee(route('privacy'), false);
    }

    public function test_registering_a_student_needs_privacy_consent_and_records_it(): void
    {
        $payload = [
            'first_name' => 'Mia', 'last_name' => 'Santos', 'gender' => 'Female', 'birth_date' => '2008-01-01',
            'program_id' => $this->program->id, 'year_level' => 1, 'student_type' => 'new',
            'guardians' => [['full_name' => 'Rey Santos', 'relationship' => 'Father', 'contact_number' => '0917 222 3333', 'is_emergency_contact' => 1]],
        ];

        $this->actingAs($this->registrar)->post(route('students.store'), $payload)->assertSessionHasErrors('privacy_consent');

        $this->actingAs($this->registrar)->post(route('students.store'), $payload + ['privacy_consent' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull(Student::where('last_name', 'Santos')->value('privacy_consent_at'));
    }

    // ---------- Smaller fixes ----------

    public function test_the_attendance_sheet_puts_the_list_first_on_phones(): void
    {
        $this->actingAs($this->teacher)->get(route('attendance.sheet', ['class_offering' => $this->class->id, 'date' => self::TODAY]))->assertOk()
            ->assertSee('class="col-lg-9 order-1"', false)->assertSee('class="col-lg-3 order-2"', false)
            ->assertSee('class="d-flex d-lg-none gap-2 mb-2"', false);
    }

    public function test_percentages_say_how_late_is_counted(): void
    {
        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertOk()
            ->assertSee('late counts as attended')->assertSee('How percentages are counted: Late counts as attended · excused is left out.');

        $this->actingAs($this->registrar)->get(route('reports.show', 'class-summary'))->assertOk()
            ->assertSee('How percentages are counted:');
    }

    public function test_the_student_is_sent_to_the_registrar_not_told_to_choose_classes(): void
    {
        $this->actingAs($this->anaUser)->get(route('student.dashboard'))->assertOk()
            ->assertSee('Available courses')->assertDontSee('Choose classes');
    }

    public function test_the_unused_sign_up_and_password_reset_code_is_gone(): void
    {
        foreach (['RegisteredUserController', 'PasswordResetLinkController', 'NewPasswordController', 'VerifyEmailController'] as $controller) {
            $this->assertFileDoesNotExist(app_path("Http/Controllers/Auth/{$controller}.php"));
        }
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }
}
