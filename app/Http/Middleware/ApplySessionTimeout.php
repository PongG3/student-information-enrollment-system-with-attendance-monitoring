<?php

namespace App\Http\Middleware;

use App\Support\SessionTimeout;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the administrator's session timeout from System Settings.
 * Runs before the session starts, so the session and its cookie use the new length.
 */
class ApplySessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        config(['session.lifetime' => SessionTimeout::minutes()]);

        return $next($request);
    }
}
