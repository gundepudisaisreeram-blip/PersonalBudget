<?php

use App\Console\Commands\GenerateMonthlyObligations;
use App\Domain\Exceptions\AllocationException;
use App\Domain\Exceptions\BudgetException;
use App\Domain\Exceptions\InvalidTransactionException;
use App\Domain\Exceptions\OwnershipViolationException;
use App\Domain\Exceptions\StatementImportException;
use Illuminate\Console\Scheduling\Schedule;
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
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(GenerateMonthlyObligations::class)->monthly();
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

        // An obligation-allocation domain rule violation (ineligible transaction
        // type, aggregate exceeds planned amount, obligation not active, or a
        // terminal-state transition attempted while active allocations exist):
        // rejected before any write, same redirect-back contract as above.
        $exceptions->render(function (AllocationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return back()->withInput()->withErrors(['obligation' => $e->getMessage()]);
            }
        });

        // A budget domain rule violation (overlapping period for the same
        // category): rejected before any write, same redirect-back contract.
        $exceptions->render(function (BudgetException $e, Request $request) {
            if (! $request->expectsJson()) {
                return back()->withInput()->withErrors(['budget' => $e->getMessage()]);
            }
        });

        // A statement import domain rule violation (exact file already
        // imported for this account): rejected before any write, same
        // redirect-back contract.
        $exceptions->render(function (StatementImportException $e, Request $request) {
            if (! $request->expectsJson()) {
                return back()->withInput()->withErrors(['import' => $e->getMessage()]);
            }
        });
    })->create();
