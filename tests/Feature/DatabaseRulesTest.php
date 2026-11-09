<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseRulesTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(string $number = '2026-0001'): Student
    {
        $program = Program::firstOrCreate(['code' => 'BSCS'], ['name' => 'BS Computer Science']);

        return Student::create([
            'student_number' => $number,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'gender' => 'Female',
            'birth_date' => '2006-01-01',
            'program_id' => $program->id,
        ]);
    }

    private function makeClass(): ClassOffering
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31']);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18']);
        $subject = Subject::create(['code' => 'CS101', 'title' => 'Intro', 'units' => 3]);
        $teacher = User::factory()->create();

        return ClassOffering::create([
            'term_id' => $term->id, 'subject_id' => $subject->id, 'class_code' => 'CL-1001',
            'section' => 'A', 'teacher_id' => $teacher->id,
        ]);
    }

    public function test_archiving_a_student_keeps_the_record(): void
    {
        $student = $this->makeStudent();
        $student->delete();

        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->assertNotNull(Student::withTrashed()->find($student->id));
    }

    public function test_student_numbers_must_be_unique(): void
    {
        $this->makeStudent('2026-0001');

        $this->expectException(QueryException::class);
        $this->makeStudent('2026-0001');
    }

    public function test_a_student_cannot_enroll_twice_in_the_same_class(): void
    {
        $student = $this->makeStudent();
        $class = $this->makeClass();

        Enrollment::create(['student_id' => $student->id, 'class_offering_id' => $class->id]);

        $this->expectException(QueryException::class);
        Enrollment::create(['student_id' => $student->id, 'class_offering_id' => $class->id]);
    }

    public function test_one_attendance_sheet_per_class_and_date(): void
    {
        $class = $this->makeClass();
        $teacher = User::factory()->create();
        $data = ['class_offering_id' => $class->id, 'session_date' => '2026-11-09', 'taken_by' => $teacher->id];

        AttendanceSession::create($data);

        $this->expectException(QueryException::class);
        AttendanceSession::create($data);
    }

    public function test_one_attendance_record_per_student_per_session(): void
    {
        $class = $this->makeClass();
        $teacher = User::factory()->create();
        $student = $this->makeStudent();
        $session = AttendanceSession::create([
            'class_offering_id' => $class->id, 'session_date' => '2026-11-09', 'taken_by' => $teacher->id,
        ]);
        $data = [
            'attendance_session_id' => $session->id, 'student_id' => $student->id,
            'status' => AttendanceRecord::PRESENT, 'recorded_by' => $teacher->id,
        ];

        AttendanceRecord::create($data);

        $this->expectException(QueryException::class);
        AttendanceRecord::create($data);
    }

    public function test_audit_log_cannot_be_changed_or_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        AuditLogger::log('test', null, 'Original description', null, ['password' => 'secret', 'name' => 'x']);
        $log = AuditLog::first();

        $log->update(['description' => 'Tampered']);
        $log->delete();

        $this->assertSame('Original description', $log->fresh()->description);
        $this->assertSame(1, AuditLog::count());
        $this->assertArrayNotHasKey('password', $log->fresh()->new_values);
    }
}
