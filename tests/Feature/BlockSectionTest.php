<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockSectionTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Alex', 'last_name' => 'Rivera', 'gender' => 'Male', 'birth_date' => '2007-03-14',
            'program_id' => $this->program->id, 'year_level' => 1, 'student_type' => 'new', 'privacy_consent' => 1,
            'guardians' => [
                0 => ['full_name' => 'Pat Rivera', 'relationship' => 'Parent', 'contact_number' => '0912 345 6789', 'is_emergency_contact' => 1],
            ],
        ], $overrides);
    }

    private function makeStudent(string $number, ?string $section, int $year = 1): Student
    {
        return Student::create([
            'student_number' => $number, 'first_name' => 'Student', 'last_name' => $number, 'gender' => 'Female',
            'birth_date' => '2006-05-05', 'program_id' => $this->program->id, 'year_level' => $year, 'section' => $section,
        ]);
    }

    public function test_a_student_can_be_registered_with_a_block_section(): void
    {
        $this->actingAs($this->registrar)->post(route('students.store'), $this->payload(['section' => ' a ']))->assertRedirect();

        $student = Student::firstOrFail();
        $this->assertSame('A', $student->section);
        $this->assertSame('BSIT 1-A', $student->block_section);

        $this->actingAs($this->registrar)->get(route('students.show', $student))->assertOk()->assertSee('BSIT 1-A');
    }

    public function test_the_section_is_optional_and_can_be_changed_or_cleared(): void
    {
        $this->actingAs($this->registrar)->post(route('students.store'), $this->payload())->assertRedirect();
        $student = Student::firstOrFail();
        $this->assertNull($student->section);
        $this->assertNull($student->block_section);
        $this->actingAs($this->registrar)->get(route('students.show', $student))->assertSee('Not assigned');

        $edit = $this->payload(['section' => 'B', 'year_level' => 2, 'status' => 'active']);
        $this->actingAs($this->registrar)->put(route('students.update', $student), $edit)->assertRedirect();
        $this->assertSame('BSIT 2-B', $student->fresh()->block_section);

        $this->actingAs($this->registrar)->put(route('students.update', $student), array_merge($edit, ['section' => '']))->assertRedirect();
        $this->assertNull($student->fresh()->section);
    }

    public function test_odd_section_names_are_refused(): void
    {
        foreach (['A/B', '<b>', str_repeat('A', 21), '-A'] as $bad) {
            $this->actingAs($this->registrar)->post(route('students.store'), $this->payload(['section' => $bad]))
                ->assertSessionHasErrors('section');
        }

        $this->assertSame(0, Student::count());
    }

    public function test_the_student_list_filters_and_shows_sections(): void
    {
        $this->makeStudent('2026-0001', 'A');
        $this->makeStudent('2026-0002', 'B');
        $this->makeStudent('2026-0003', null);

        $this->actingAs($this->registrar)->get(route('students.index'))
            ->assertOk()->assertSee('All sections')->assertSee('Section A')->assertSee('Section B');

        $this->actingAs($this->registrar)->get(route('students.index', ['section' => 'b']))
            ->assertSee('2026-0002')->assertDontSee('2026-0001')->assertDontSee('2026-0003');
    }

    public function test_the_csv_export_includes_the_section(): void
    {
        $this->makeStudent('2026-0001', 'C');

        $csv = $this->actingAs($this->registrar)->get(route('students.export'))->streamedContent();

        $this->assertStringContainsString('"Year level",Section,Type', $csv);
        $this->assertMatchesRegularExpression('/2026-0001,.*,BSIT,1,C,/', $csv);
    }

    public function test_the_master_list_report_filters_by_section(): void
    {
        $this->makeStudent('2026-0001', 'A');
        $this->makeStudent('2026-0002', 'B');

        $this->actingAs($this->registrar)->get('/reports/student-master-list?section=A')
            ->assertOk()->assertSee('Block section')->assertSee('2026-0001')->assertDontSee('2026-0002');
    }
}
