<?php

namespace App\Domain\Services;

use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use DateTimeInterface;

/**
 * Creates Payment Obligations under the approved identity rules.
 *
 * An obligation is strictly Recurring (template + occurrence_key) or
 * One-time (idempotency_key). Generation is idempotent: creating the same
 * occurrence/idempotency key twice never produces a duplicate row (BR-018,
 * 09 section 3 "Identity invariant").
 */
class PaymentObligationService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function createRecurringOccurrence(
        User $user,
        RecurringPaymentTemplate $template,
        string $occurrenceKey,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        DateTimeInterface|string $dueDate,
        string $plannedAmount,
        ?Category $category = null,
    ): PaymentObligation {
        $this->ownership->assertRecurringTemplateOwnership($template, $user->id);

        $resolvedCategory = $category ?? $template->category;
        $this->ownership->assertCategoryOwnership($resolvedCategory, $user->id);

        return PaymentObligation::query()->firstOrCreate(
            [
                'recurring_payment_template_id' => $template->id,
                'occurrence_key' => $occurrenceKey,
            ],
            [
                'user_id' => $user->id,
                'idempotency_key' => null,
                'category_id' => $resolvedCategory->id,
                'planned_account_id' => $template->default_account_id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'planned_amount' => $plannedAmount,
                'status' => 'PENDING',
                'is_mandatory' => $template->is_mandatory,
            ],
        );
    }

    public function createOneTime(
        User $user,
        string $idempotencyKey,
        Category $category,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        DateTimeInterface|string $dueDate,
        string $plannedAmount,
        bool $isMandatory = true,
        ?int $plannedAccountId = null,
    ): PaymentObligation {
        $this->ownership->assertCategoryOwnership($category, $user->id);

        return PaymentObligation::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $user->id,
                'recurring_payment_template_id' => null,
                'occurrence_key' => null,
                'category_id' => $category->id,
                'planned_account_id' => $plannedAccountId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'planned_amount' => $plannedAmount,
                'status' => 'PENDING',
                'is_mandatory' => $isMandatory,
            ],
        );
    }
}
