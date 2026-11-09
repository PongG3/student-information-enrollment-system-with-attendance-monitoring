<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Standard browser protections, added to every web response. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Only over HTTPS: tell browsers to keep using HTTPS for this site.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // Pages of signed-in users are not kept by the browser, so the Back button on a
        // shared school computer cannot show someone else's records after logout.
        // (Downloads such as PDF and Excel files are left alone.)
        if ($request->user() && ! $response->headers->has('Content-Disposition')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
