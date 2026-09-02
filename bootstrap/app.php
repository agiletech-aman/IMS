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
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419) {
                return redirect()->route('login')->withErrors([
                    'session' => 'Your session has expired. Please log in again.',
                ]);
            }

            // Business-rule aborts (e.g. "select a centre first") carry a
            // friendly message and should show as a toast, not a debug page.
            if ($e->getStatusCode() === 422 && $e->getMessage() !== '' && ! $request->expectsJson()) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        });
    })->create();
