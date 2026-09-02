<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'validate.session' => \App\Http\Middleware\ValidateSession::class,
        ]);

        // Add security headers to all web responses
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Add ValidateSession middleware to web group
        // This validates session integrity on each request for authenticated users
        $middleware->web(append: [
            \App\Http\Middleware\ValidateSession::class,
        ]);

        // Configure authentication redirects
        // Redirect unauthenticated users to login page
        $middleware->redirectGuestsTo('/login');

        // Redirect authenticated users to dashboard
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*'),
        );
    })->create();
