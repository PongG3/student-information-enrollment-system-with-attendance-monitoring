<?php

namespace Database\Seeders;

use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * FAKE sample data so you can see the screens while developing.
 * Never runs in production. Run with: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Skipped: demo data is never created in production.');

            return;
        }

        $term = Term::current();
        if (! $term) {
            $this->command?->warn('No current term found. Run: php artisan db:seed');

            return;
        }

        $teacher1 = User::where('email', 'teacher@sies.test')->first();
        $registrar = User::where('email', 'registrar@sies.test')->first();

        $teacher2 = User::firstOrNew(['email' => 'teacher2@sies.test']);
        $teacher2->forceFill([
            'name' => 'Daniel Cruz',
            'password' => Hash::make('Sies-Test-2026!'),
            'role' => 'teacher',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        if (! $teacher1) {
            $this->command?->warn('Teacher test account missing. Run: php artisan db:seed');

            return;
        }

        // ---- Classes ----
        $offerings = [
            ['CL-1001', 'CS101', $teacher1, [[1, '08:00', '09:30', 'Room 101'], [3, '08:00', '09:30', 'Room 101']]],
            ['CL-1002', 'CS102', $teacher1, [[2, '10:00', '11:30', 'Lab 1'], [4, '10:00', '11:30', 'Lab 1']]],
            ['CL-1003', 'MATH101', $teacher2, [[1, '10:00', '11:30', 'Room 202'], [3, '10:00', '11:30', 'Room 202']]],
            ['CL-1004', 'ENG101', $teacher2, [[2, '13:00', '14:30', 'Room 203'], [4, '13:00', '14:30', 'Room 203']]],
            ['CL-1005', 'HUM101', $teacher1, [[5, '09:00', '12:00', 'Room 105']]],
            ['CL-1006', 'PE101', $teacher2, [[6, '08:00', '10:00', 'Gym']]],
        ];

        $classes = [];
        foreach ($offerings as [$classCode, $subjectCode, $teacher, $meetings]) {
            $subject = Subject::where('code', $subjectCode)->first();

            $class = ClassOffering::firstOrCreate(
                ['term_id' => $term->id, 'class_code' => $classCode],
                ['subject_id' => $subject->id, 'section' => 'A', 'teacher_id' => $teacher->id, 'capacity' => 40],
            );

            if ($class->wasRecentlyCreated) {
                foreach ($meetings as [$day, $start, $end, $room]) {
                    $class->meetings()->create([
                        'day_of_week' => $day, 'start_time' => $start, 'end_time' => $end, 'room' => $room,
                    ]);
                }
            }

            $classes[$classCode] = $class;
        }

        // ---- Students (all fake) ----
        $people = [
            ['2026-0001', 'Alex', 'Rivera', 'Male', '2007-03-14', 'BSCS', 1],
            ['2026-0002', 'Jamie', 'Lee', 'Female', '2007-06-02', 'BSCS', 1],
            ['2026-0003', 'Sam', 'Torres', 'Male', '2006-11-21', 'BSCS', 1],
            ['2026-0004', 'Casey', 'Lim', 'Female', '2007-01-30', 'BSCS', 1],
            ['2026-0005', 'Morgan', 'Reyes', 'Male', '2006-09-08', 'BSIT', 2],
            ['2026-0006', 'Taylor', 'Cruz', 'Female', '2007-04-17', 'BSBA', 1],
        ];

        $studentAccount = User::where('email', 'student@sies.test')->first();
        $students = [];

        foreach ($people as $i => [$number, $first, $last, $gender, $birth, $programCode, $year]) {
            $student = Student::firstOrCreate(
                ['student_number' => $number],
                [
                    'user_id' => $number === '2026-0001' ? $studentAccount?->id : null,
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'birth_date' => $birth,
                    'address' => 'Sample Street, Sample City',
                    'contact_number' => '0900-000-000' . ($i + 1),
                    'email' => strtolower($first) . '@students.sies.test',
                    'program_id' => Program::where('code', $programCode)->first()->id,
                    'year_level' => $year,
                    'section' => $i % 2 === 0 ? 'A' : 'B',
                    'student_type' => 'new',
                    'previous_school' => 'Sample High School',
                ],
            );

            if ($student->wasRecentlyCreated) {
                $student->guardians()->create([
                    'full_name' => 'Guardian of ' . $first . ' ' . $last,
                    'relationship' => 'Parent',
                    'contact_number' => '0911-000-000' . ($i + 1),
                    'is_emergency_contact' => true,
                ]);
            }

            // Requirements: verified for the first four students, pending for the rest.
            foreach (Requirement::all() as $requirement) {
                $verified = $i < 4;
                StudentRequirement::firstOrCreate(
                    ['student_id' => $student->id, 'requirement_id' => $requirement->id],
                    [
                        'status' => $verified ? 'verified' : 'pending',
                        'submitted_at' => $verified ? now() : null,
                        'verified_by' => $verified ? $registrar?->id : null,
                        'verified_at' => $verified ? now() : null,
                    ],
                );
            }

            $students[$number] = $student;
        }

        // ---- Enrollments in different statuses ----
        $enrollments = [
            ['2026-0001', 'CL-1001', 'enrolled'],
            ['2026-0001', 'CL-1002', 'enrolled'],
            ['2026-0001', 'CL-1005', 'pending'],
            ['2026-0002', 'CL-1001', 'enrolled'],
            ['2026-0002', 'CL-1003', 'approved'],
            ['2026-0003', 'CL-1001', 'enrolled'],
            ['2026-0003', 'CL-1004', 'pending'],
            ['2026-0004', 'CL-1001', 'enrolled'],
            ['2026-0004', 'CL-1002', 'rejected'],
            ['2026-0005', 'CL-1003', 'pending'],
            ['2026-0006', 'CL-1006', 'enrolled'],
        ];

        foreach ($enrollments as [$number, $classCode, $status]) {
            $reviewed = $status !== 'pending';

            Enrollment::firstOrCreate(
                ['student_id' => $students[$number]->id, 'class_offering_id' => $classes[$classCode]->id],
                [
                    'status' => $status,
                    'requested_by' => $registrar?->id,
                    'requested_at' => now(),
                    'reviewed_by' => $reviewed ? $registrar?->id : null,
                    'reviewed_at' => $reviewed ? now() : null,
                    'enrolled_at' => $status === 'enrolled' ? $term->start_date->copy()->addDays(7)->setTime(9, 0) : null,
                    'status_reason' => $status === 'rejected' ? 'Schedule conflict (sample)' : null,
                ],
            );
        }
    }
}
