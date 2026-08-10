<?php

namespace App\Domain\Services;

use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Orchestrates idempotent recurring obligation generation (BR-018;
 * 03_ARCHITECTURE.md section 8; Phase 4 Decision Package section 8).
 *
 * Grammar is frozen: frequency = MONTHLY only, due_rule = "DAY:N" (N 1-31),
 * clamped to the target month's last calendar day. An occurrence is
 * generated only when its clamped due date falls on/after the template's
 * starts_on and, when ends_on exists, on/before ends_on -- both boundaries
 * inclusive, evaluated strictly after clamping.
 *
 * This service contains all generation logic. The manual HTTP trigger and
 * the scheduled console command both call generate() and must never
 * duplicate any part of this orchestration.
 */
class MonthlyGenerationService
{
    public function __construct(private readonly PaymentObligationService $obligations) {}

    /**
     * @return Collection<int, PaymentObligation>
     */
    public function generate(User $user, ?string $targetPeriod = null): Collection
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod($user, $targetPeriod);

        $templates = $user->recurringPaymentTemplates()
            ->where('status', 'ACTIVE')
            ->where('starts_on', '<=', $periodEnd->toDateString())
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('ends_on')->orWhere('ends_on', '>=', $periodStart->toDateString());
            })
            ->get();

        $occurrenceKey = $periodStart->format('Y-m');
        $created = new Collection;

        foreach ($templates as $template) {
            $dueDate = $this->calculateDueDate($template, $periodStart);

            if ($dueDate->lt($template->starts_on)) {
                continue;
            }

            if ($template->ends_on !== null && $dueDate->gt($template->ends_on)) {
                continue;
            }

            $created->push($this->obligations->createRecurringOccurrence(
                $user,
                $template,
                $occurrenceKey,
                $periodStart,
                $periodEnd,
                $dueDate,
                (string) $template->amount,
                $template->category,
            ));
        }

        return $created;
    }

    /**
     * due_rule = "DAY:N". Clamping to the target month's last calendar day
     * MUST occur before any starts_on/ends_on boundary evaluation.
     */
    private function calculateDueDate(RecurringPaymentTemplate $template, Carbon $periodStart): Carbon
    {
        [, $day] = explode(':', $template->due_rule);

        $clampedDay = min((int) $day, $periodStart->daysInMonth);

        return $periodStart->copy()->day($clampedDay);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(User $user, ?string $targetPeriod): array
    {
        $timezone = $user->timezone ?? config('app.timezone');

        $periodStart = $targetPeriod !== null
            ? Carbon::createFromFormat('Y-m-d', $targetPeriod.'-01', $timezone)->startOfMonth()
            : Carbon::now($timezone)->startOfMonth();

        return [$periodStart->copy()->startOfDay(), $periodStart->copy()->endOfMonth()->startOfDay()];
    }
}
