<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Notice;
use App\Support\StudentEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** One email per student: the profile email is the sign-in email, and a new one is confirmed from its inbox. */
class StudentEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $anaUser;
    private Student $ana;
    private User $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->anaUser = User::factory()->create(['email' => 'ana@sies.test']);
        $this->anaUser->forceFill(['role' => 'student', 'is_active' => true, 'must_change_password' => false])->save();
        $program = Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science']);
        $this->ana = Student::create(['student_number' => '2026-0001', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'gender' => 'Female',
            'birth_date' => '2006-01-01', 'program_id' => $program->id, 'year_level' => 1, 'status' => 'active',
            'user_id' => $this->anaUser->id, 'email' => 'ana@sies.test']);

        $this->registrar = User::factory()->create();
        $this->registrar->forceFill(['role' => 'registrar', 'is_active' => true, 'must_change_password' => false])->save();
    }

    private function confirmLink(): string
    {
        $url = null;
        Notification::assertSentOnDemand(Notice::class, function (Notice $n) use (&$url) {
            if ($n->subject === 'Confirm your new email') {
                $url = $n->actionUrl;
            }

            return true;
        });

        return $url;
    }

    public function test_a_new_email_waits_for_confirmation_then_becomes_the_sign_in_email(): void
    {
        $this->actingAs($this->anaUser)->put(route('student.profile.contact'), ['email' => 'Ana.Cruz@CvSU.edu.ph', 'contact_number' => '0917 000 0000'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'We sent a confirmation link to ana.cruz@cvsu.edu.ph'));

        // Nothing switched yet: the old email still signs in.
        $this->assertSame('ana@sies.test', $this->anaUser->fresh()->email);
        $this->assertSame('ana.cruz@cvsu.edu.ph', $this->ana->fresh()->pending_email);
        Notification::assertSentOnDemand(Notice::class, fn (Notice $n, $c, $to) => $n->subject === 'Confirm your new email' && $to->routes['mail'] === 'ana.cruz@cvsu.edu.ph');
        $this->actingAs($this->anaUser)->get(route('student.profile'))->assertSee('Waiting for you to confirm');

        // The link from the new inbox (signed out is fine) switches both copies and tells the old address.
        auth()->logout();
        $this->get($this->confirmLink())->assertRedirect(route('login'));
        $this->assertSame('ana.cruz@cvsu.edu.ph', $this->anaUser->fresh()->email);
        $this->assertSame('ana.cruz@cvsu.edu.ph', $this->ana->fresh()->email);
        $this->assertNull($this->ana->fresh()->pending_email);
        Notification::assertSentOnDemand(Notice::class, fn (Notice $n, $c, $to) => $n->subject === 'Your portal email was changed' && $to->routes['mail'] === 'ana@sies.test');
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.email_changed']);

        // And "Forgot password" with the student number sends the link to the new email.
        $this->post(route('password.email'), ['identifier' => '2026-0001']);
        Notification::assertSentOnDemand(Notice::class, fn (Notice $n, $c, $to) => $n->subject === 'Reset your password' && $to->routes['mail'] === 'ana.cruz@cvsu.edu.ph');
    }

    public function test_an_old_or_altered_link_does_not_change_anything(): void
    {
        $this->actingAs($this->anaUser)->put(route('student.profile.contact'), ['email' => 'first@cvsu.edu.ph']);
        $first = $this->confirmLink();
        // Asked for another address: the first link is useless now.
        $this->actingAs($this->anaUser)->put(route('student.profile.contact'), ['email' => 'second@cvsu.edu.ph']);

        $this->actingAs($this->anaUser)->get($first)->assertRedirect(route('student.profile'))->assertSessionHas('error');
        $this->assertSame('ana@sies.test', $this->anaUser->fresh()->email);
    }

    public function test_the_email_cannot_be_removed_or_taken_from_another_account(): void
    {
        $this->actingAs($this->anaUser)->put(route('student.profile.contact'), ['email' => ''])->assertSessionHasErrors('email');
        $this->actingAs($this->anaUser)->put(route('student.profile.contact'), ['email' => $this->registrar->email])->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    }

    public function test_a_pending_change_can_be_cancelled(): void
    {
        $this->actingAs($this->anaUser)->put(route('student.profile.contact'), ['email' => 'typo@cvsu.edu.ph']);
        $this->actingAs($this->anaUser)->delete(route('student.profile.email.cancel'))->assertSessionHas('info');
        $this->assertNull($this->ana->fresh()->pending_email);
        $this->assertSame('ana@sies.test', $this->anaUser->fresh()->email);
    }

    public function test_staff_changes_keep_both_copies_the_same(): void
    {
        // User Management -> the record follows.
        $this->registrar->forceFill(['role' => 'admin'])->save();
        $this->actingAs($this->registrar)->put(route('users.update', $this->anaUser), ['name' => $this->anaUser->name, 'email' => 'ana.new@cvsu.edu.ph']);
        $this->assertSame('ana.new@cvsu.edu.ph', $this->ana->fresh()->email);

        // Student record -> the sign-in email follows.
        StudentEmail::sync($this->ana->fresh('user'), 'ana.final@cvsu.edu.ph');
        $this->assertSame('ana.final@cvsu.edu.ph', $this->anaUser->fresh()->email);
    }
}
