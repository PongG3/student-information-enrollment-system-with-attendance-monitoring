<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Add academic year" opens a dialog on the Academic Years & Terms page. */
class AcademicYearDialogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin', 'is_active' => true, 'must_change_password' => false])->save();
    }

    public function test_the_page_has_the_add_year_dialog(): void
    {
        $this->actingAs($this->admin)->get(route('academic-years.index'))->assertOk()
            ->assertSee('data-bs-target="#addYearModal"', false)
            ->assertSee('id="addYearModal"', false)
            ->assertSee('name="_from_dialog"', false);
    }

    public function test_a_year_added_from_the_dialog_returns_to_the_page(): void
    {
        $this->actingAs($this->admin)->from(route('academic-years.index'))->post(route('academic-years.store'), [
            '_from_dialog' => 1, 'name' => '2027-2028', 'start_date' => '2027-08-01', 'end_date' => '2028-05-31',
        ])->assertRedirect(route('academic-years.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('academic_years', ['name' => '2027-2028']);
    }

    public function test_a_failed_save_reopens_the_dialog_with_what_was_typed(): void
    {
        $this->actingAs($this->admin)->from(route('academic-years.index'))->followingRedirects()->post(route('academic-years.store'), [
            '_from_dialog' => 1, 'name' => '2027-2028', 'start_date' => '2028-05-31', 'end_date' => '2027-08-01',
        ])->assertOk()
            ->assertSee('The academic year was not saved.')
            ->assertSee("getOrCreateInstance(document.getElementById('addYearModal')).show()", false)
            ->assertSee('value="2027-2028"', false);

        $this->assertSame(0, AcademicYear::count());
    }
}
