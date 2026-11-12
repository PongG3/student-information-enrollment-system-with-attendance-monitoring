<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\PrivacyPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The staff data privacy policy: the administrator publishes it, staff agree to each version. */
class PrivacyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser('admin');
        $this->teacher = $this->makeUser('teacher');
        $this->student = $this->makeUser('student');
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

        return $user;
    }

    private function publish(): void
    {
        $this->actingAs($this->admin)->put(route('privacy-policy.update'), ['text' => PrivacyPolicy::suggested(), 'publish' => 1])
            ->assertRedirect(route('privacy-policy.show'));
    }

    public function test_a_draft_policy_asks_nobody_and_reminds_the_admin(): void
    {
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('The data privacy policy is not published yet.');

        $this->actingAs($this->teacher)->get(route('privacy-policy.show'))->assertOk()
            ->assertSee('Who may see what')->assertSee('Draft');
    }

    public function test_once_published_staff_must_agree_before_working(): void
    {
        $this->publish();

        // The teacher is sent to the policy, from any page, and comes back afterwards.
        $this->actingAs($this->teacher)->get(route('attendance.index'))->assertRedirect(route('privacy-policy.show'));
        $this->actingAs($this->teacher)->get(route('privacy-policy.show'))->assertOk()->assertSee('I agree');

        $this->actingAs($this->teacher)->post(route('privacy-policy.agree'))->assertSessionHasErrors('agree');
        $this->actingAs($this->teacher)->post(route('privacy-policy.agree'), ['agree' => 1])->assertRedirect(route('attendance.index'));

        $this->assertSame(PrivacyPolicy::version(), $this->teacher->fresh()->privacy_policy_version);
        $this->assertNotNull($this->teacher->fresh()->privacy_policy_agreed_at);
        $this->assertTrue(AuditLog::where('action', 'privacy.policy_agreed')->exists());
        $this->actingAs($this->teacher)->get(route('attendance.index'))->assertOk();

        // Students are never asked: they get the privacy notice instead.
        $this->assertFalse(PrivacyPolicy::needsAgreement($this->student));
    }

    public function test_a_new_version_asks_everyone_again_but_a_quiet_fix_does_not(): void
    {
        Carbon::setTestNow('2026-11-12 08:00:00');
        $this->publish();
        $this->actingAs($this->admin)->post(route('privacy-policy.agree'), ['agree' => 1]);
        $this->actingAs($this->teacher)->post(route('privacy-policy.agree'), ['agree' => 1]);

        // A typo fix saved without publishing: nobody is asked again.
        $this->actingAs($this->admin)->put(route('privacy-policy.update'), ['text' => PrivacyPolicy::suggested() . "\n\nFixed a typo."]);
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk();

        // Published as a new version: everyone agrees again.
        Carbon::setTestNow('2026-11-13 08:00:00');
        $this->publish();
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertRedirect(route('privacy-policy.show'));
        Carbon::setTestNow();
    }

    public function test_only_the_admin_edits_and_the_text_cannot_carry_html(): void
    {
        $this->actingAs($this->teacher)->get(route('privacy-policy.edit'))->assertForbidden();

        $this->actingAs($this->admin)->put(route('privacy-policy.update'), [
            'text' => "## Rules\nBe careful with data. <script>alert(1)</script> Always lock your computer when you leave it.",
        ]);
        $this->assertSame(null, Setting::get(PrivacyPolicy::VERSION));

        $this->actingAs($this->admin)->get(route('privacy-policy.show'))->assertOk()
            ->assertSee('<h2>Rules</h2>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_account_menu_links_to_the_policy_for_staff_only(): void
    {
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertSee(route('privacy-policy.show'), false);
    }
}
