<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Where to go right after signing in.
 *
 * When a signed-out visitor opens a protected page (an expired session, the Back button,
 * a restored tab), Laravel remembers that page and returns to it after the next sign-in.
 * On a shared computer the next person may have another role: an administrator's
 * /audit-logs must not send a student into a 403 page. So the remembered page is used
 * only when the person who just signed in may open it; otherwise they go to their dashboard.
 */
final class SignInRedirect
{
    public static function for(User $user, ?string $intended): string
    {
        $home = route('dashboard', absolute: false);

        return $intended && self::allows($user, $intended) ? $intended : $home;
    }

    /** True when the address is a page of this site that this user's role may open. */
    public static function allows(User $user, string $url): bool
    {
        // Only pages of this site.
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== null && $host !== request()->getHost()) {
            return false;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($url, 'GET'));
        } catch (Throwable) {
            return false; // unknown page, or not something to open with GET (such as /logout)
        }

        // Never "return" to the sign-in page itself.
        if ($route->getName() === 'login') {
            return false;
        }

        // Every "role:a,b" on the route must include this user's role.
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                $roles = explode(',', substr($middleware, 5));
                if (! in_array($user->role, $roles, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
