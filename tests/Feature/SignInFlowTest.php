<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SessionNotice;
use App\Support\SignInRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SignInFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['email' => "{$role}@school.test"]);
        $user->forceFill(['role' => $role, 'is_active' => true, 'must_change_password' => false])->save();

        return $user;
    }

    private function signIn(User $user)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    // ---------- Where to go after signing in ----------

    public function test_another_role_is_not_sent_to_the_admin_page_left_open(): void
    {
        $student = $this->makeUser('student');

        // An administrator's session ended on the audit log; the page asked for a sign-in.
        $this->get('/audit-logs')->assertRedirect(route('login'));

        // A student signs in on the same computer: their dashboard, not a 403 page.
        $this->signIn($student)->assertRedirect(route('dashboard', absolute: false));
        $this->followingRedirects()->get(route('dashboard'))->assertOk();
    }

    public function test_registrar_and_teacher_also_avoid_the_admin_page(): void
    {
        foreach (['registrar', 'teacher'] as $role) {
            $user = $this->makeUser($role);
            $this->get('/settings');
            $this->signIn($user)->assertRedirect(route('dashboard', absolute: false));
            auth()->logout();
        }
    }

    public function test_the_same_administrator_returns_to_the_page_they_were_on(): void
    {
        $admin = $this->makeUser('admin');

        $this->get('/audit-logs?action=login');
        $this->signIn($admin)->assertRedirect('/audit-logs?action=login');
    }

    public function test_a_page_shared_by_several_roles_is_kept_when_allowed(): void
    {
        $registrar = $this->makeUser('registrar');

        $this->get('/students');
        $this->signIn($registrar)->assertRedirect('/students');
    }

    public function test_odd_remembered_addresses_fall_back_to_the_dashboard(): void
    {
        $admin = $this->makeUser('admin');
        $home = route('dashboard', absolute: false);

        $this->assertSame($home, SignInRedirect::for($admin, null));
        $this->assertSame($home, SignInRedirect::for($admin, 'https://evil.example/audit-logs'));
        $this->assertSame($home, SignInRedirect::for($admin, url('/logout')));       // POST only
        $this->assertSame($home, SignInRedirect::for($admin, url('/login')));        // never back to sign-in
        $this->assertSame($home, SignInRedirect::for($admin, url('/no-such-page')));
        $this->assertSame(url('/users'), SignInRedirect::for($admin, url('/users')));
    }

    // ---------- "Session timed out" notice ----------

    public function test_signing_in_leaves_a_marker_and_signing_out_removes_it(): void
    {
        $teacher = $this->makeUser('teacher');

        $this->signIn($teacher)->assertCookie(SessionNotice::COOKIE);
        $this->post('/logout')->assertCookieExpired(SessionNotice::COOKIE);
    }

    public function test_the_sign_in_page_says_when_a_session_timed_out(): void
    {
        $this->withCookie(SessionNotice::COOKIE, '1')->get('/login')->assertOk()
            ->assertSee('Session timed out.')->assertSee('Please sign in again.')
            ->assertCookieExpired(SessionNotice::COOKIE); // shown once
    }

    public function test_a_normal_visit_to_the_sign_in_page_has_no_notice(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Session timed out');
    }

    // ---------- Forms sent after the session ended (419) ----------

    public function test_an_expired_form_from_a_signed_out_visitor_goes_to_sign_in(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => throw new TokenMismatchException());

        $this->post('/_test/expired')->assertRedirect(route('login'));
    }

    public function test_an_expired_form_from_a_signed_in_user_goes_back_with_a_message(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => throw new TokenMismatchException());
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->from('/settings')->post('/_test/expired', ['school_name' => 'Kept', 'password' => 'secret'])
            ->assertRedirect('/settings')
            ->assertSessionHas('error', 'This page was open for too long. Please try again.')
            ->assertSessionHasInput('school_name', 'Kept')
            ->assertSessionMissing('_old_input.password');
    }
}
