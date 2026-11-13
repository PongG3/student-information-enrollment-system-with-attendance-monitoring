<?php

namespace Tests\Feature;

use App\Models\AbsenceExcuse;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassOffering;
use App\Models\DataBreach;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Follow-ups from the quality audit: uploads, personal-data export, breach register, DPO, excuse ranges. */
class AuditFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $registrar;
    private User $teacher;
    private User $anaUser;
    private Student $ana;
    private ClassOffering $class;
    private ClassOffering $other;

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
        $this->anaUser = $this->makeUser('student', ['email' => 'ana@students.test']);

        $this->class = $this->makeClass($term, 'CS101', 'CL-1001', 13);
        $this->other = $this->makeClass($term, 'CS102', 'CL-1002', 15);
        $this->ana = Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female', 'birth_date' => '2006-01-01', 'program_id' => $program->id, 'year_level' => 1, 'status' => 'active', 'user_id' => $this->anaUser->id]);
        foreach ([$this->class, $this->other] as $class) {
            Enrollment::create(['student_id' => $this->ana->id, 'class_offering_id' => $class->id, 'status' => 'enrolled', 'requested_at' => now(), 'enrolled_at' => now()]);
        }
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

    private function makeClass(Term $term, string $subject, string $code, int $hour): ClassOffering
    {
        $s = Subject::create(['code' => $subject, 'title' => $subject . ' title', 'units' => 3]);
        $class = ClassOffering::create(['term_id' => $term->id, 'subject_id' => $s->id, 'section' => 'A', 'class_code' => $code, 'teacher_id' => $this->teacher->id ?? null, 'capacity' => 40]);
        foreach ([1, 3] as $day) {
            $class->meetings()->create(['day_of_week' => $day, 'start_time' => sprintf('%02d:00', $hour), 'end_time' => sprintf('%02d:30', $hour + 1), 'room' => 'Room 1']);
        }

        return $class;
    }

    private function mark(ClassOffering $class, string $date, string $status): void
    {
        $session = AttendanceSession::create(['class_offering_id' => $class->id, 'session_date' => $date, 'taken_by' => $this->teacher->id]);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $this->ana->id, 'status' => $status, 'recorded_by' => $this->teacher->id]);
    }

    // ---------- Requirement uploads ----------

    public function test_a_student_uploads_a_requirement_and_the_registrar_downloads_it(): void
    {
        Storage::fake('local');
        $requirement = Requirement::create(['name' => 'PSA Birth Certificate', 'is_required' => true, 'is_active' => true]);

        $this->actingAs($this->anaUser)->post(route('student.requirements.upload', $requirement), [
            'file' => UploadedFile::fake()->create('psa.pdf', 200, 'application/pdf'),
        ])->assertSessionHas('success');

        $row = StudentRequirement::firstOrFail();
        $this->assertSame('submitted', $row->status);
        Storage::disk('local')->assertExists($row->file_path);

        // The registrar is told and can download it; the download is an attachment, never shown in the portal.
        $this->actingAs($this->registrar)->get(route('registrar.dashboard'))->assertSee('uploaded document is waiting to be verified');
        $this->actingAs($this->registrar)->get(route('requirements.file', $row))->assertOk()
            ->assertHeader('content-disposition');
        $this->actingAs($this->teacher)->get(route('requirements.file', $row))->assertForbidden();

        // Once verified it cannot be replaced.
        $row->forceFill(['status' => 'verified'])->save();
        $this->actingAs($this->anaUser)->post(route('student.requirements.upload', $requirement), [
            'file' => UploadedFile::fake()->create('again.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('requirement');
    }

    public function test_a_wrong_requirement_file_is_refused(): void
    {
        $requirement = Requirement::create(['name' => 'ID Photo', 'is_required' => true, 'is_active' => true]);
        $this->actingAs($this->anaUser)->from(route('student.profile'))->post(route('student.requirements.upload', $requirement), [
            'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
            '_requirement' => $requirement->id,
        ])->assertSessionHasErrors('file');
        $this->assertSame(0, StudentRequirement::count());

        // Following the redirect back to the profile: the same document's window opens again with the reason.
        $this->actingAs($this->anaUser)->from(route('student.profile'))->followingRedirects()
            ->post(route('student.requirements.upload', $requirement), [
                'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
                '_requirement' => $requirement->id,
            ])
            ->assertSee(json_encode(route('student.requirements.upload', $requirement)), false)
            ->assertSee('bootstrap.Modal.getOrCreateInstance(requirementModal).show()', false);
    }

    public function test_the_upload_window_checks_for_a_file_before_sending(): void
    {
        Requirement::create(['name' => 'ID Photo', 'is_required' => true, 'is_active' => true]);

        // The drop box is marked required, so the window says "choose a file" instead of closing.
        $this->actingAs($this->anaUser)->get(route('student.profile'))
            ->assertSee('data-required="Choose the file of this document first, then press Upload."', false);
    }

    // ---------- Photo rejected with a reason ----------

    public function test_a_rejected_photo_tells_the_student_why(): void
    {
        $this->ana->forceFill(['pending_photo_path' => 'student-photos/pending/x.jpg'])->save();
        Storage::fake('local');

        $this->actingAs($this->registrar)->post(route('students.photo.reject', $this->ana), ['reason' => 'Face is not clear'])
            ->assertSessionHas('success');

        $this->assertSame('Face is not clear', $this->ana->fresh()->photo_rejected_reason);
        $this->actingAs($this->anaUser)->get(route('student.profile'))->assertSee('Your last photo was not approved: Face is not clear');
    }

    // ---------- Personal data export ----------

    public function test_staff_and_the_student_can_download_the_personal_data_pdf(): void
    {
        $this->ana->guardians()->create(['full_name' => 'Maria Cruz', 'relationship' => 'Mother', 'contact_number' => '0917 000 0000', 'is_emergency_contact' => true]);

        $this->actingAs($this->registrar)->get(route('students.personal-data', $this->ana))->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->anaUser)->get(route('student.personal-data'))->assertOk()->assertHeader('content-type', 'application/pdf');

        // Teachers cannot, and the export is recorded.
        $this->actingAs($this->teacher)->get(route('students.personal-data', $this->ana))->assertForbidden();
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'student.data_exported')->count());
    }

    // ---------- Breach register and DPO ----------

    public function test_the_admin_records_and_updates_a_breach_and_nobody_else_can(): void
    {
        $this->actingAs($this->admin)->post(route('breaches.store'), [
            'discovered_at' => '2026-11-08 09:00', 'summary' => 'A printed class list was left in the library', 'people_affected' => 40,
        ])->assertRedirect(route('breaches.index'));

        $breach = DataBreach::firstOrFail();
        $this->assertSame('open', $breach->status);
        $this->actingAs($this->admin)->get(route('breaches.index'))->assertOk()
            ->assertSee('A printed class list was left in the library')->assertSee('Due by Nov 11');

        $this->actingAs($this->admin)->put(route('breaches.update', $breach), [
            'discovered_at' => '2026-11-08 09:00', 'summary' => $breach->summary, 'npc_notified_on' => '2026-11-09', 'closed' => 1,
        ]);
        $this->assertSame('closed', $breach->fresh()->status);
        $this->assertNotNull($breach->fresh()->npc_notified_on);

        $this->actingAs($this->registrar)->get(route('breaches.index'))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('breaches.store'), ['discovered_at' => '2026-11-08 09:00', 'summary' => 'x'])->assertForbidden();
    }

    public function test_the_dpo_is_named_in_the_privacy_notice_once_set(): void
    {
        $this->get(route('privacy'))->assertOk()->assertDontSee('Our Data Protection Officer');

        Setting::put('dpo_name', 'Atty. Rosa Mendoza');
        Setting::put('dpo_email', 'dpo@school.test');
        $this->get(route('privacy'))->assertOk()->assertSee('Our Data Protection Officer')->assertSee('Atty. Rosa Mendoza')->assertSee('dpo@school.test');
    }

    // ---------- Excuse letters for several days ----------

    public function test_one_letter_covers_every_missed_meeting_in_a_date_range(): void
    {
        Storage::fake('local');
        // Absent in CS101 on Mon Nov 2 and Wed Nov 4, and in CS102 on Nov 2.
        $this->mark($this->class, '2026-11-02', 'absent');
        $this->mark($this->class, '2026-11-04', 'absent');
        $this->mark($this->other, '2026-11-02', 'absent');

        // This class only, across the range.
        $this->actingAs($this->anaUser)->post(route('student.attendance.excuse', $this->class), [
            'date' => '2026-11-02', 'until' => '2026-11-04', 'reason' => 'I was in the hospital all week.',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, '2 missed meetings over 2 days'));
        $this->assertSame(2, AbsenceExcuse::where('class_offering_id', $this->class->id)->count());
        $this->assertSame(0, AbsenceExcuse::where('class_offering_id', $this->other->id)->count());

        // All classes across the range (the others that still accept a letter).
        AbsenceExcuse::query()->delete();
        $this->actingAs($this->anaUser)->post(route('student.attendance.excuse', $this->class), [
            'date' => '2026-11-02', 'until' => '2026-11-04', 'all_day' => 1, 'reason' => 'I was in the hospital all week.',
        ]);
        $this->assertSame(3, AbsenceExcuse::count());

        $this->actingAs($this->anaUser)->post(route('student.attendance.excuse', $this->class), [
            'date' => '2026-11-04', 'until' => '2026-11-02', 'reason' => 'The dates are the wrong way round.',
        ])->assertSessionHasErrors('until');
    }
}
