<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The list pages filter live (resources/js/live-filter.js): no Filter/Search button unless JavaScript is off. */
class LiveFilterPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_pages_filter_live_and_keep_a_button_only_without_javascript(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin', 'is_active' => true, 'must_change_password' => false])->save();
        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true]);

        foreach (['classes.index', 'subjects.index', 'programs.index', 'audit-logs.index', 'users.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()
                ->assertSee('data-live-filter', false)
                ->assertSee('data-live-region="results"', false)
                ->assertSee('<noscript><button class="btn btn-outline-secondary btn-sm-sies">', false)
                ->assertDontSee('onchange="this.form.submit()"', false);
        }

        // On Class Management the term reloads the whole page (the add and copy windows follow it).
        $this->actingAs($admin)->get(route('classes.index'))->assertSee('data-live-keep data-live-reload', false);
    }

    public function test_attendance_holidays_my_classes_and_courses_filter_live_too(): void
    {
        $make = function (string $role) {
            $user = User::factory()->create();
            $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

            return $user;
        };
        $admin = $make('admin');
        $teacher = $make('teacher');
        $studentUser = $make('student');
        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true]);
        $program = \App\Models\Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        \App\Models\Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female', 'birth_date' => '2006-01-01',
            'program_id' => $program->id, 'year_level' => 1, 'status' => 'active', 'user_id' => $studentUser->id]);
        $class = \App\Models\ClassOffering::create(['term_id' => $term->id, 'subject_id' => \App\Models\Subject::create(['code' => 'CS1', 'title' => 'CS', 'units' => 3])->id,
            'section' => 'A', 'class_code' => 'C1', 'teacher_id' => $teacher->id, 'capacity' => 40]);

        $pages = [
            [$admin, route('attendance.index')],
            [$admin, route('attendance.summary', $class)],
            [$admin, route('holidays.index')],
            [$teacher, route('teacher.classes')],
            [$studentUser, route('student.courses')],
        ];
        foreach ($pages as [$user, $url]) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('data-live-filter', false)
                ->assertSee('data-live-region=', false)
                ->assertDontSee('onchange="this.form.submit()"', false);
        }

        // Attendance Monitoring refreshes its numbers, range line and export links; the term reloads the page.
        $this->actingAs($admin)->get(route('attendance.index'))
            ->assertSee('data-live-region="body"', false)->assertSee('data-live-region="range"', false)
            ->assertSee('data-live-region="export"', false)->assertSee('data-live-keep data-live-reload', false);
    }
}
