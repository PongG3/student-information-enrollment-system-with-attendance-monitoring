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
}
