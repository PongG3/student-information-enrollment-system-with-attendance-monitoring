<?php

namespace App\Providers;

use App\Http\Middleware\SecurityHeaders;
use App\Models\AuditLog;
use App\Support\RoleLabel;
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

        // Browser protections on every response.
        $this->app->make(HttpKernel::class)->pushMiddleware(SecurityHeaders::class);

        // On the real server (APP_URL starts with https://) every generated link uses HTTPS.
        if (str_starts_with((string) config('app.url'), 'https://') && ! $this->app->environment('local', 'testing')) {
            URL::forceScheme('https');
        }

        // Sign-in and sign-out are written to the audit log, with the person's role.
        Event::listen(Login::class, function (Login $event) {
            $role = $event->user->role ?? null;
            $this->audit('login', $event->user, 'Signed in as ' . (RoleLabel::for($role) ?? 'user'));
        });

        Event::listen(Logout::class, function (Logout $event) {
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
