<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Forgot password: a student number (or a staff email) -> a one-hour, one-use link to that account's email. */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $anaUser;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->anaUser = User::factory()->create(['email' => 'ana@students.test']);
        $this->anaUser->forceFill(['role' => 'student', 'is_active' => true, 'must_change_password' => false])->save();
        $program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female',
            'birth_date' => '2006-01-01', 'program_id' => $program->id, 'year_level' => 1, 'status' => 'active',
            'user_id' => $this->anaUser->id, 'email' => 'ana@students.test']);

        $this->teacher = User::factory()->create(['email' => 'teacher@school.test']);
        $this->teacher->forceFill(['role' => 'teacher', 'is_active' => true, 'must_change_password' => false])->save();
    }

    public function test_the_sign_in_page_links_to_forgot_password(): void
    {
        $this->get(route('login'))->assertSee('Forgot password?')->assertSee(route('password.request'), false);
        $this->get(route('password.request'))->assertOk()->assertSee('Student number')->assertSee('or staff email');
    }

    public function test_a_student_number_sends_the_link_to_that_students_email(): void
    {
        // Any letter case and spaces; the link goes to the account's email, never to whoever asked.
        $this->post(route('password.email'), ['identifier' => ' 2026-0001 '])
            ->assertSessionHas('status', fn ($s) => str_starts_with($s, 'If that matches an account, we sent a reset link to its email.'));
        Notification::assertSentOnDemand(Notice::class, fn (Notice $n, $channels, $to) => $n->subject === 'Reset your password'
            && $to->routes['mail'] === 'ana@students.test' && str_contains($n->actionUrl, '/set-password/'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'password.reset_requested']);
    }

    public function test_unknown_numbers_and_emails_look_the_same_and_send_nothing(): void
    {
        foreach (['2026-9999', 'nobody@school.test', 'not-anything'] as $identifier) {
            $this->post(route('password.email'), ['identifier' => $identifier])
                ->assertSessionHas('status', fn ($s) => str_starts_with($s, 'If that matches an account'));
        }
        Notification::assertNothingSent();
        $this->post(route('password.email'), ['identifier' => ''])->assertSessionHasErrors('identifier');
    }

    public function test_staff_use_their_email(): void
    {
        $this->post(route('password.email'), ['identifier' => 'Teacher@School.test']);
        Notification::assertSentOnDemand(Notice::class, fn (Notice $n, $c, $to) => $n->subject === 'Reset your password' && $to->routes['mail'] === 'teacher@school.test');
    }

    public function test_inactive_accounts_get_no_link(): void
    {
        $this->teacher->forceFill(['is_active' => false])->save();
        $this->anaUser->forceFill(['is_active' => false])->save();
        $this->post(route('password.email'), ['identifier' => 'teacher@school.test']);
        $this->post(route('password.email'), ['identifier' => '2026-0001']);
        Notification::assertNothingSent();
    }

    public function test_the_link_resets_once_signs_out_other_devices_and_tells_the_owner(): void
    {
        config(['session.driver' => 'database']);
        \Illuminate\Support\Facades\DB::table('sessions')->insert(['id' => 'old', 'user_id' => $this->anaUser->id, 'payload' => '', 'last_activity' => time()]);

        $this->post(route('password.email'), ['identifier' => '2026-0001']);
        $url = null;
        Notification::assertSentOnDemand(Notice::class, function (Notice $n) use (&$url) {
            if ($n->subject === 'Reset your password') {
                $url = $n->actionUrl;
            }

            return true;
        });

        $this->get($url)->assertOk()->assertSee('Choose your password');
        $this->post($url, ['password' => 'A-Brand-New-Pass1', 'password_confirmation' => 'A-Brand-New-Pass1'])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('A-Brand-New-Pass1', $this->anaUser->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'old']);
        Notification::assertSentOnDemand(Notice::class, fn (Notice $n) => $n->subject === 'Your password was changed');

        // Used once: the same link no longer works.
        $this->get($url)->assertForbidden();
    }

    public function test_an_expired_link_offers_a_new_one(): void
    {
        $url = \App\Support\Notify::setPasswordUrl($this->teacher, 60);
        $this->travel(61)->minutes();

        $this->get($url)->assertRedirect(route('password.request'))->assertSessionHasErrors('email');
    }

    public function test_requests_are_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('password.email'), ['identifier' => '2026-0001']);
        }
        // At most 3 emails per account in 15 minutes, and the form itself allows 5 tries a minute.
        Notification::assertSentOnDemandTimes(Notice::class, 3);
        $this->post(route('password.email'), ['identifier' => '2026-0001'])->assertStatus(429);
    }
}
