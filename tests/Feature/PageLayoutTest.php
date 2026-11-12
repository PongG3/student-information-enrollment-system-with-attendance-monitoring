<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Program;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shared page building blocks: header actions, row counts, empty states with a next step. */
class PageLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $registrar;
    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrar = User::factory()->create();
        $this->registrar->forceFill(['role' => 'registrar', 'is_active' => true, 'must_change_password' => false])->save();
        $this->program = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
    }

    private function makeStudent(string $number, string $last): Student
    {
        return Student::create([
            'student_number' => $number, 'first_name' => 'Sam', 'last_name' => $last, 'gender' => 'Female',
            'birth_date' => '2006-05-05', 'program_id' => $this->program->id, 'year_level' => 1,
        ]);
    }

    public function test_an_empty_list_offers_the_first_step(): void
    {
        $this->actingAs($this->registrar)->get(route('students.index'))->assertOk()
            ->assertSee('No students are registered yet.')
            ->assertSee('Add the first student');
    }

    public function test_a_filtered_empty_list_offers_to_clear_the_filters(): void
    {
        $this->makeStudent('2026-0001', 'Rivera');

        $this->actingAs($this->registrar)->get(route('students.index', ['search' => 'zzz']))->assertOk()
            ->assertSee('No students match your filters.')
            ->assertSee('Clear filters')
            ->assertDontSee('Add the first student');
    }

    public function test_lists_say_how_many_rows_there_are(): void
    {
        foreach (range(1, 17) as $i) {
            $this->makeStudent(sprintf('2026-%04d', $i), "Student{$i}");
        }

        $this->actingAs($this->registrar)->get(route('students.index'))->assertOk()
            ->assertSeeInOrder(['Showing', '1–15', 'of', '17', 'students'])
            ->assertSee('aria-label="Next page"', false);
    }

    public function test_the_main_action_sits_in_the_page_header(): void
    {
        $this->actingAs($this->registrar)->get(route('students.index'))->assertOk()
            ->assertSeeInOrder(['class="page-actions"', 'Export CSV', 'Add student', 'class="card-sies"'], false);
    }

    public function test_a_new_enrollment_request_shows_its_steps(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        Term::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18',
            'is_current' => true, 'enrollment_open' => true,
        ]);

        $this->actingAs($this->registrar)->get(route('enrollments.create'))->assertOk()
            ->assertSee('aria-current="step"', false)
            ->assertSeeInOrder(['Find the student', 'Choose classes']);
    }

    public function test_the_student_form_is_tabbed_and_opens_where_the_error_is(): void
    {
        // A clean form opens on the first tab.
        $this->actingAs($this->registrar)->get(route('students.create'))->assertOk()
            ->assertSeeInOrder(['Personal &amp; contact', 'Academic', 'Guardians', 'Photo'], false)
            ->assertSee('class="tab-pane fade show active" id="pane-personal"', false);

        // Everything filled in except the guardians: the form comes back on the Guardians tab, marked.
        $this->actingAs($this->registrar)->from(route('students.create'))->followingRedirects()->post(route('students.store'), [
            'first_name' => 'Alex', 'last_name' => 'Rivera', 'gender' => 'Male', 'birth_date' => '2007-03-14',
            'program_id' => $this->program->id, 'year_level' => 1, 'student_type' => 'new', 'privacy_consent' => 1,
        ])->assertOk()
            ->assertSee('class="tab-pane fade show active" id="pane-guardians"', false)
            ->assertSee('class="form-tab-error"', false)
            ->assertSee('Add at least one parent or guardian.');

        $this->assertSame(0, Student::count());
    }

    public function test_reports_are_cards_that_open(): void
    {
        $this->actingAs($this->registrar)->get(route('reports.index'))->assertOk()
            ->assertSee('class="report-card"', false)->assertSee('Open report');
    }
}
