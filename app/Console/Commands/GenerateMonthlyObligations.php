<?php

namespace App\Console\Commands;

use App\Domain\Services\MonthlyGenerationService;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Thin scheduler entry point. Contains no generation logic of its own --
 * every user's active recurring templates are generated for the current
 * period via the single shared MonthlyGenerationService, identical to the
 * manual HTTP recovery trigger.
 */
class GenerateMonthlyObligations extends Command
{
    protected $signature = 'obligations:generate-monthly';

    protected $description = 'Idempotently generate this period\'s payment obligations for every user\'s active recurring templates.';

    public function handle(MonthlyGenerationService $generator): int
    {
        User::query()->each(function (User $user) use ($generator) {
            $generator->generate($user);
        });

        return self::SUCCESS;
    }
}
