<?php

use App\Domain\Exceptions\InvalidTransactionException;
use App\Domain\Exceptions\OwnershipViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/accounts');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // A closed account, an invalid Refund/Reversal parent relationship, or a
        // same-account Transfer: rejected before any write, surfaced to the user
        // as a normal validation-style redirect (10_IMPLEMENTATION_CONTRACT.md §7).
        $exceptions->render(function (InvalidTransactionException $e, Request $request) {
            if (! $request->expectsJson()) {
                return back()->withInput()->withErrors(['transaction' => $e->getMessage()]);
            }
        });

        // A cross-tenant account/category reference reaching the domain layer:
        // a generic 403 that never echoes the exception message, so it never
        // discloses whether the referenced record exists or belongs to someone else.
        $exceptions->render(function (OwnershipViolationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return response('Forbidden.', 403);
            }
        });
    })->create();
