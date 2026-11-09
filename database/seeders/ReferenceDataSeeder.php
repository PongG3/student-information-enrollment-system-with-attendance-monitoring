<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\NonClassDay;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Setting;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Database\Seeder;

/**
 * Starting reference data. Safe to run again: existing rows are never overwritten,
 * so changes made later through the admin screens are kept.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::firstOrCreate(
            ['name' => '2026-2027'],
            ['start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true],
        );

        Term::firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => '1st Semester'],
            ['start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true, 'enrollment_open' => true],
        );
        Term::firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => '2nd Semester'],
            ['start_date' => '2027-01-11', 'end_date' => '2027-05-28'],
        );

        foreach ([
            ['BSCS', 'BS Computer Science'],
            ['BSIT', 'BS Information Technology'],
            ['BSBA', 'BS Business Administration'],
        ] as [$code, $name]) {
            Program::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach ([
            ['CS101', 'Introduction to Computing', 3],
            ['CS102', 'Computer Programming 1', 3],
            ['MATH101', 'College Algebra', 3],
            ['ENG101', 'Communication Skills', 3],
            ['HUM101', 'Humanities', 3],
            ['PE101', 'Physical Education 1', 2],
        ] as [$code, $title, $units]) {
            Subject::firstOrCreate(['code' => $code], ['title' => $title, 'units' => $units]);
        }

        foreach ([
            ['Transcript of Records / Form 138', 'Previous school record'],
            ['PSA Birth Certificate', 'Photocopy of the original'],
            ['Certificate of Good Moral Character', 'From the previous school'],
            ['2x2 ID Photo', 'Recent photo'],
        ] as [$name, $description]) {
            Requirement::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        foreach ([
            ['2026-11-30', 'Bonifacio Day'],
            ['2026-12-25', 'Christmas Day'],
            ['2026-12-30', 'Rizal Day'],
        ] as [$date, $description]) {
            NonClassDay::firstOrCreate(['date' => $date], ['description' => $description]);
        }

        // System settings (edited later on the Settings page).
        foreach ([
            'school_name' => 'School Name',
            'attendance_edit_window_days' => '7',   // teachers may edit records this many days back
            'absentee_threshold_percent' => '20',   // flag students absent more than this share
            'max_units_per_term' => '24',
            'late_counts_as_attended' => '1',       // Present and Late both count as attended
            'excused_in_percentage' => 'excluded',  // excused sessions are left out of the percentage
        ] as $name => $value) {
            Setting::firstOrCreate(['name' => $name], ['value' => $value]);
        }
    }
}
