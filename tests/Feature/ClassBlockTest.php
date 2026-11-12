<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassOffering;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\ScheduleCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Classes belong to a block (BSCS 1-C) and get a CvSU-style schedule code (202610007). */
class ClassBlockTest extends TestCase
{
    use RefreshDatabase;

    private User $registrar;
    private User $teacher;
    private Term $first;
    private Term $second;
    private Subject $subject;
    private Program $bscs;
    private Program $bsit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrar = $this->makeUser('registrar');
        $this->teacher = $this->makeUser('teacher');

        $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true]);
        // Created out of order on purpose: the term number follows the dates, not the id.
        $this->second = Term::create(['academic_year_id' => $year->id, 'name' => '2nd Semester', 'start_date' => '2027-01-11', 'end_date' => '2027-05-21']);
        $this->first = Term::create(['academic_year_id' => $year->id, 'name' => '1st Semester', 'start_date' => '2026-08-03', 'end_date' => '2026-12-18', 'is_current' => true]);

        $this->subject = Subject::create(['code' => 'CS102', 'title' => 'Computer Programming 1', 'units' => 3]);
        $this->bscs = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        $this->bsit = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'term_id' => $this->first->id, 'subject_id' => $this->subject->id,
            'program_id' => $this->bscs->id, 'year_level' => 1, 'section' => 'c',
            'teacher_id' => $this->teacher->id, 'capacity' => 40, 'status' => 'open',
            'meetings' => [['day' => 1, 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101']],
        ], $overrides);
    }

    private int $slot = 0;

    /** Each class gets its own day, so teacher and room conflicts never get in the way. */
    private function create(array $overrides = [])
    {
        $day = ($this->slot++ % 6) + 1;

        return $this->actingAs($this->registrar)->post(route('classes.store'), $this->payload($overrides + [
            'meetings' => [['day' => $day, 'start' => '08:00', 'end' => '09:30', 'room' => 'Room 101']],
        ]));
    }

    // ---------- Schedule codes ----------

    public function test_schedule_codes_are_year_term_and_number(): void
    {
        $this->assertSame('20261', ScheduleCode::prefix($this->first));
        $this->assertSame('20262', ScheduleCode::prefix($this->second));

        $this->create()->assertSessionHasNoErrors();
        $this->create(['section' => 'D', 'class_code' => 'HACKED'])->assertSessionHasNoErrors();
        $this->create(['term_id' => $this->second->id])->assertSessionHasNoErrors();

        $this->assertSame(['202610001', '202610002', '202620001'], ClassOffering::orderBy('id')->pluck('class_code')->all());
    }

    public function test_moving_a_class_to_another_term_gives_it_that_terms_code(): void
    {
        $this->create()->assertSessionHasNoErrors();
        $class = ClassOffering::firstOrFail();

        $this->actingAs($this->registrar)->put(route('classes.update', $class), $this->payload(['term_id' => $this->second->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame('202620001', $class->fresh()->class_code);

        // Editing anything else keeps the code.
        $this->actingAs($this->registrar)->put(route('classes.update', $class), $this->payload(['term_id' => $this->second->id, 'capacity' => 30]))
            ->assertSessionHasNoErrors();
        $this->assertSame('202620001', $class->fresh()->class_code);
    }

    // ---------- Add Class dialog on the class list ----------

    public function test_the_class_list_has_an_add_class_dialog(): void
    {
        $this->actingAs($this->registrar)->get(route('classes.index'))->assertOk()
            ->assertSee('data-bs-target="#addClassModal"', false)
            ->assertSee('id="addClassModal"', false)
            ->assertSee('name="_from_dialog"', false)
            ->assertSee('Weekly schedule')->assertSee('Create class');
    }

    public function test_a_class_added_from_the_dialog_returns_to_the_list(): void
    {
        $this->actingAs($this->registrar)->from(route('classes.index'))
            ->post(route('classes.store'), $this->payload(['_from_dialog' => 1]))
            ->assertRedirect(route('classes.index', ['term' => $this->first->id]))
            ->assertSessionHas('success', 'Class 202610001 created: CS102 · BSCS 1-C.');
    }

    public function test_a_failed_save_reopens_the_dialog_with_what_was_typed(): void
    {
        $this->actingAs($this->registrar)->from(route('classes.index'))->followingRedirects()
            ->post(route('classes.store'), $this->payload(['_from_dialog' => 1, 'teacher_id' => '', 'section' => 'Q']))
            ->assertOk()
            ->assertSee('The class was not saved.')
            ->assertSee("getOrCreateInstance(document.getElementById('addClassModal')).show()", false)
            ->assertSee('value="Q"', false);

        $this->assertSame(0, ClassOffering::count());
    }

    // ---------- Blocks ----------

    public function test_a_class_shows_its_block(): void
    {
        $this->create()->assertSessionHasNoErrors();
        $class = ClassOffering::firstOrFail();

        $this->assertSame('C', $class->section);
        $this->assertSame('BSCS 1-C', $class->block_label);

        $this->actingAs($this->registrar)->get(route('classes.show', $class))->assertOk()
            ->assertSee('202610001')->assertSee('BSCS 1-C');
        $this->actingAs($this->registrar)->get(route('classes.index'))->assertOk()->assertSee('BSCS 1-C');
    }

    public function test_different_programs_can_use_the_same_section(): void
    {
        $this->create()->assertSessionHasNoErrors();                                 // BSCS 1-C
        $this->create(['program_id' => $this->bsit->id])->assertSessionHasNoErrors(); // BSIT 1-C
        $this->create(['year_level' => 2])->assertSessionHasNoErrors();             // BSCS 2-C

        $this->assertSame(['BSCS 1-C', 'BSIT 1-C', 'BSCS 2-C'], ClassOffering::with('program')->orderBy('id')->get()->pluck('block_label')->all());
    }

    public function test_the_same_block_cannot_have_the_subject_twice(): void
    {
        $this->create()->assertSessionHasNoErrors();

        $this->create(['section' => 'C'])->assertSessionHasErrors('section');
        $this->assertSame(1, ClassOffering::count());
    }

    public function test_a_class_can_be_open_to_any_block(): void
    {
        // No program: open to irregular students from any block. A stray year level is dropped.
        $this->create(['program_id' => '', 'year_level' => 2, 'section' => 'x'])->assertSessionHasNoErrors();
        $class = ClassOffering::firstOrFail();

        $this->assertNull($class->program_id);
        $this->assertNull($class->year_level);
        $this->assertSame('Section X', $class->block_label);

        // Only one such class per subject and section letter.
        $this->create(['program_id' => '', 'section' => 'X'])->assertSessionHasErrors('section');
    }

    public function test_a_block_needs_a_year_level_and_a_simple_section(): void
    {
        $this->create(['year_level' => ''])->assertSessionHasErrors('year_level');
        $this->create(['section' => 'C/D'])->assertSessionHasErrors('section');
        $this->assertSame(0, ClassOffering::count());
    }
}
