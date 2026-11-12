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
        $eli = $this->makeStudent('2026-0005', 'Eli');
        $request = $this->enroll($eli, $this->class, 'pending');

        $this->actingAs($this->registrar)->get(route('enrollments.index'))->assertOk()
            ->assertSee('Approve selected')->assertSee('Enroll selected')->assertSee('name="students[]" value="' . $eli->id . '"', false);

        $this->actingAs($this->teacher)->post(route('enrollments.bulk'), ['action' => 'approve', 'ids' => [$request->id]])->assertForbidden();
    }

    // ---------- Requests grouped by student ----------

    public function test_requests_are_grouped_by_student_and_the_page_opens_on_pending(): void
    {
        $eli = $this->makeStudent('2026-0005', 'Eli');
        $this->enroll($eli, $this->class, 'pending');
        $this->enroll($eli, $this->otherClass, 'pending');

        // Pending is the default tab while requests wait; Ana (enrolled) is not on it.
        $page = $this->actingAs($this->registrar)->get(route('enrollments.index'))->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSee('Tester, Eli')->assertDontSee('Tester, Ana')
            ->assertSee('Approve all 2')
            ->assertSee('6 of 24 units');
        $this->assertSame(1, substr_count($page->getContent(), 'Tester, Eli</a>'));   // the student once, not once per class

        // "All" shows everyone.
        $this->actingAs($this->registrar)->get(route('enrollments.index', ['status' => 'all']))->assertOk()
            ->assertSee('Tester, Ana')->assertSee('Tester, Eli');
    }

    public function test_requests_filter_by_program_and_incomplete_documents(): void
    {
        $bsit = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $eli = $this->makeStudent('2026-0005', 'Eli');
        $gus = $this->makeStudent('2026-0007', 'Gus');
        $gus->forceFill(['program_id' => $bsit->id])->save();
        $this->enroll($eli, $this->class, 'pending');
        $this->enroll($gus, $this->class, 'pending');

        $this->actingAs($this->registrar)->get(route('enrollments.index', ['program' => $bsit->id]))->assertOk()
            ->assertSee('Tester, Gus')->assertDontSee('Tester, Eli')->assertSee('Clear filters');

        // One required document; Eli has it verified, Gus does not.
        $requirement = \App\Models\Requirement::create(['name' => 'Birth certificate', 'is_required' => true, 'is_active' => true]);
        \App\Models\StudentRequirement::create(['student_id' => $eli->id, 'requirement_id' => $requirement->id, 'status' => 'verified']);

        $this->actingAs($this->registrar)->get(route('enrollments.index', ['docs' => 'incomplete']))->assertOk()
            ->assertSee('Tester, Gus')->assertDontSee('Tester, Eli')
            ->assertSee('0 of 1 documents');
    }

    public function test_enrolled_classes_are_dropped_not_cancelled(): void
    {
        $this->actingAs($this->registrar)->get(route('enrollments.index', ['status' => 'enrolled']))->assertOk()
            ->assertSee('>Drop</button>', false)->assertSee('Drop this class?')->assertDontSee('Withdraw this request')
            ->assertSee('data-request-toggle', false)->assertSee('Collapse all');
    }

    public function test_a_whole_student_is_approved_at_once(): void
    {
        // The two classes must not clash, or the second is (rightly) refused.
        $this->otherClass->meetings()->update(['start_time' => '13:00', 'end_time' => '14:30']);
        $eli = $this->makeStudent('2026-0005', 'Eli');
        $one = $this->enroll($eli, $this->class, 'pending');
        $two = $this->enroll($eli, $this->otherClass, 'pending');
        $this->enroll($this->makeStudent('2026-0006', 'Fay'), $this->class, 'pending');   // not selected

        $this->actingAs($this->registrar)->from(route('enrollments.index'))
            ->post(route('enrollments.bulk'), ['action' => 'approve', 'students' => [$eli->id], 'term' => $this->term->id])
            ->assertSessionHas('success', '2 requests approved.');

        $this->assertSame(['approved', 'approved'], [$one->fresh()->status, $two->fresh()->status]);
        $this->assertSame(1, Enrollment::where('status', 'pending')->count());

        // Nothing left to approve for Eli.
        $this->actingAs($this->registrar)->post(route('enrollments.bulk'), ['action' => 'approve', 'students' => [$eli->id], 'term' => $this->term->id])
            ->assertSessionHasErrors('enrollment');
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

    public function test_one_letter_can_cover_every_class_missed_that_day(): void
    {
        Storage::fake('local');
        // Ana also takes the other class, and missed both on Wednesday Nov 4.
        $this->otherClass->update(['capacity' => 5]);
        $this->enroll($this->ana, $this->otherClass, 'enrolled');
        $this->mark($this->class, '2026-11-04', [$this->ana->id => 'absent']);
        $this->mark($this->otherClass, '2026-11-04', [$this->ana->id => 'late']);

        // The dialog offers the other class for that day, and the button says what it does.
        $this->actingAs($this->anaUser)->get(route('student.attendance.show', $this->class))->assertOk()
            ->assertSee('data-excuse-others="CS202"', false)->assertSee('Submit excuse')->assertSee('0 / 500')
            ->assertSee('data-file-drop', false);

        $this->actingAs($this->anaUser)->post(route('student.attendance.excuse', $this->class), [
            'date' => '2026-11-04', 'reason' => 'I had a fever and stayed home.', 'all_day' => 1,
            'file' => UploadedFile::fake()->create('certificate.pdf', 80, 'application/pdf'),
        ])->assertSessionHas('success', fn ($message) => str_contains($message, 'for 2 classes (CS101, CS202)'));

        $letters = AbsenceExcuse::orderBy('class_offering_id')->get();
        $this->assertCount(2, $letters);
        $this->assertSame([$this->class->id, $this->otherClass->id], $letters->pluck('class_offering_id')->all());
        // Each class has its own copy of the file.
        $this->assertNotSame($letters[0]->file_path, $letters[1]->file_path);
        Storage::disk('local')->assertExists($letters[0]->file_path);
        Storage::disk('local')->assertExists($letters[1]->file_path);
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

    // ---------- Student self-service: contact details and photo ----------

    public function test_a_student_updates_their_own_contact_details_but_nothing_official(): void
    {
        $this->actingAs($this->anaUser)->get(route('student.profile'))->assertOk()
            ->assertSee('Academic status')->assertSee('Personal information')->assertSee('Contact details')
            ->assertSee('class="profile-lock"', false)->assertSee('id="contactModal"', false);

        $this->actingAs($this->anaUser)->put(route('student.profile.contact'), [
            'email' => 'ana.new@example.com', 'contact_number' => '0917 555 1234', 'address' => 'Imus, Cavite',
            // Not allowed to change these: ignored.
            'last_name' => 'Hacker', 'program_id' => 999, 'birth_date' => '2000-01-01',
        ])->assertRedirect(route('student.profile'))->assertSessionHas('success', 'Contact details saved.');

        $ana = $this->ana->fresh();
        $this->assertSame('0917 555 1234', $ana->contact_number);
        $this->assertSame('Imus, Cavite', $ana->address);
        $this->assertSame('Tester', $ana->last_name);
        $this->assertSame($this->program->id, $ana->program_id);
        $this->assertTrue(\App\Models\AuditLog::where('action', 'student.contact_updated')->exists());

        $this->actingAs($this->anaUser)->put(route('student.profile.contact'), ['contact_number' => 'call me'])
            ->assertSessionHasErrors('contact_number');
    }

    public function test_a_student_photo_waits_for_the_registrar_and_becomes_official_when_approved(): void
    {
        Storage::fake('local');

        $this->actingAs($this->anaUser)->post(route('student.profile.photo'), [
            'photo' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ])->assertSessionHas('success');

        $ana = $this->ana->fresh();
        $this->assertNull($ana->photo_path);
        $this->assertNotNull($ana->pending_photo_path);
        $this->actingAs($this->anaUser)->get(route('student.photo', ['pending' => 1]))->assertOk();

        // The registrar is told, sees it on the record, and approves it.
        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertSee('student photo is waiting for approval');
        $this->actingAs($this->registrar)->get(route('students.show', $ana))->assertOk()->assertSee('New photo waiting for approval');
        $this->actingAs($this->registrar)->post(route('students.photo.approve', $ana))->assertSessionHas('success');

        $ana->refresh();
        $this->assertNotNull($ana->photo_path);
        $this->assertNull($ana->pending_photo_path);
        $this->actingAs($this->anaUser)->get(route('student.photo'))->assertOk();
        $this->actingAs($this->teacher)->post(route('students.photo.approve', $ana))->assertForbidden();
    }

    public function test_a_rejected_photo_is_removed_and_tiny_photos_are_refused(): void
    {
        Storage::fake('local');
        $this->actingAs($this->anaUser)->post(route('student.profile.photo'), ['photo' => UploadedFile::fake()->image('tiny.png', 50, 50)])
            ->assertSessionHasErrors('photo');

        $this->actingAs($this->anaUser)->post(route('student.profile.photo'), ['photo' => UploadedFile::fake()->image('me.png', 300, 300)]);
        $path = $this->ana->fresh()->pending_photo_path;

        $this->actingAs($this->registrar)->post(route('students.photo.reject', $this->ana));
        $this->assertNull($this->ana->fresh()->pending_photo_path);
        Storage::disk('local')->assertMissing($path);
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
