<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edit / add pages open inside the shared page dialog (?embed=1, see resources/js/page-dialog.js
 * and App\Http\Middleware\DialogEmbed).
 */
class PageDialogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin', 'is_active' => true, 'must_change_password' => false])->save();
    }

    public function test_list_pages_open_their_forms_in_the_dialog(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('users.index'))->assertOk()
            ->assertSee('id="pageDialog"', false)
            ->assertSee('href="'.route('users.create').'" data-dialog', false);

        $this->get(route('students.index'))->assertOk()
            ->assertSee('href="'.route('students.create').'" data-dialog data-dialog-size="xl"', false);

        $this->get(route('enrollments.index'))->assertOk()
            ->assertSee('href="'.route('enrollments.create').'" data-dialog data-dialog-size="lg"', false);
    }

    public function test_an_embedded_page_has_no_sidebar_and_names_its_dialog(): void
    {
        $this->actingAs($this->admin)->get(route('users.create', ['embed' => 1]))->assertOk()
            ->assertSee('class="is-embedded"', false)
            ->assertSee('name="dialog-title" content="', false)
            ->assertSee('data-dialog-close', false)
            ->assertDontSee('id="pageDialog"', false)
            ->assertDontSee('class="sidebar', false);
    }

    public function test_a_save_from_the_dialog_sends_the_main_window_on_and_keeps_the_messages(): void
    {
        $response = $this->actingAs($this->admin)->from(route('users.create', ['embed' => 1]))->post(route('users.store'), [
            '_embed' => 1, 'name' => 'New Registrar', 'email' => 'registrar2@example.test', 'role' => 'registrar',
        ]);

        $response->assertOk()->assertViewIs('dialog-done')->assertViewHas('url', route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'registrar2@example.test']);

        // The one-time temporary password is still there for the main window's next page.
        $this->assertNotNull(session('temp_password'));
        $this->get(route('users.index'))->assertOk()
            ->assertSee('User created.')
            ->assertSee('registrar2@example.test');
    }

    public function test_a_failed_save_stays_inside_the_dialog(): void
    {
        $embedded = route('users.create', ['embed' => 1]);

        $this->actingAs($this->admin)->from($embedded)->post(route('users.store'), [
            '_embed' => 1, 'name' => '', 'email' => 'not-an-email', 'role' => 'registrar',
        ])->assertRedirect($embedded)->assertSessionHasErrors(['name', 'email']);
    }

    public function test_a_dialog_page_that_sends_you_elsewhere_moves_the_main_window(): void
    {
        // e.g. the session ran out: the sign-in page opens in the main window, not inside the dialog.
        $this->get(route('users.create', ['embed' => 1]))
            ->assertOk()->assertViewIs('dialog-done')->assertViewHas('url', route('login'));
    }

    public function test_class_pages_open_edit_class_in_the_dialog(): void
    {
        $this->actingAs($this->admin)->get(route('classes.index'))->assertOk();
        $this->assertStringContainsString(
            'data-dialog data-dialog-size="xl"',
            (string) file_get_contents(resource_path('views/classes/show.blade.php'))
        );
    }

    public function test_a_save_without_the_dialog_still_redirects_normally(): void
    {
        $year = AcademicYear::create(['name' => '2030-2031', 'start_date' => '2030-08-01', 'end_date' => '2031-05-31']);

        $this->actingAs($this->admin)->put(route('academic-years.update', $year), [
            'name' => '2031-2032', 'start_date' => '2030-08-01', 'end_date' => '2031-05-31',
        ])->assertRedirect(route('academic-years.index'));
    }
}
