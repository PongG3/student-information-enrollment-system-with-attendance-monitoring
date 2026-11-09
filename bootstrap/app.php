<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

    // Before the session starts: the admin's session timeout from System Settings.
    $middleware->web(prepend: [
        \App\Http\Middleware\ApplySessionTimeout::class,
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // "419 Page Expired": a form was sent after the session ended (for example
        // pressing Log out after being idle). Instead of an error page:
        //  - signed out already: go to the sign-in page, which says the session timed out;
        //  - still signed in (page left open too long): go back and ask to try again.
        $expired = function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return null;
            }
            if ($request->routeIs('login')) {
                return redirect()->route('login')->withInput($request->only('email'))
                    ->with('status', 'The sign-in page was open for too long. Please sign in again.');
            }
            if (! $request->user()) {
                return redirect()->guest(route('login'));
            }

            return back()->with('error', 'This page was open for too long. Please try again.')
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password']));
        };

        $exceptions->render(fn (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) => $expired($e, $request));
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) use ($expired) {
            return $e->getStatusCode() === 419 ? $expired($e, $request) : null;
        });
    })->create();
