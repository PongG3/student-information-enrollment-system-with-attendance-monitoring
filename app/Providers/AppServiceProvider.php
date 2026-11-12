<?php

namespace App\Providers;

use App\Http\Middleware\SecurityHeaders;
use App\Models\AbsenceExcuse;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Term;
use App\Models\User;
use App\Support\Navigation;
use App\Support\RoleLabel;
use App\Support\SessionNotice;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // The top bar's notifications are cached briefly; any change they depend on refreshes them at once.
        foreach ([Enrollment::class, AttendanceSession::class, AttendanceRecord::class, User::class, Term::class, AbsenceExcuse::class, Student::class] as $model) {
            $model::saved(fn () => Navigation::refreshNotifications());
            $model::deleted(fn () => Navigation::refreshNotifications());
        }

        // Browser protections on every response.
        $this->app->make(HttpKernel::class)->pushMiddleware(SecurityHeaders::class);

        // On the real server (APP_URL starts with https://) every generated link uses HTTPS.
        if (str_starts_with((string) config('app.url'), 'https://') && ! $this->app->environment('local', 'testing')) {
            URL::forceScheme('https');
        }

        // Sign-in and sign-out are written to the audit log, with the person's role.
        Event::listen(Login::class, function (Login $event) {
            SessionNotice::signedIn(); // so an expired session can be told apart later
            $role = $event->user->role ?? null;
            $this->audit('login', $event->user, 'Signed in as ' . (RoleLabel::for($role) ?? 'user'));
        });

        Event::listen(Logout::class, function (Logout $event) {
            SessionNotice::signedOut(); // signing out on purpose is not a timeout
            if ($event->user) {
                $role = $event->user->role ?? null;
                $this->audit('logout', $event->user, 'Signed out (' . (RoleLabel::for($role) ?? 'user') . ')');
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            $email = (string) ($event->credentials['email'] ?? 'unknown email');
            $label = $event->user ? ' (' . (RoleLabel::for($event->user->role ?? null) ?? 'user') . ' account)' : '';

            $this->audit('login.failed', $event->user, Str::limit('Failed sign-in attempt for ' . $email . $label, 250));
        });
    }

    private function audit(string $action, $user, string $description): void
    {
        AuditLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'user_role' => $user->role ?? null,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
