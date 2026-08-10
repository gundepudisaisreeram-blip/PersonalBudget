<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\BudgetException;
use App\Domain\Money;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates Variable Budgets, enforcing the no-overlapping-period
 * rule per category, and computes Budget Utilization from the certified
 * ledger architecture (BR-025; Phase 4 Decision Package sections 9-10).
 *
 * Overlap enforcement locks the authenticated user's own `users` row as the
 * serialization resource, because a brand-new Budget has no existing row of
 * its own to lock before insertion -- the same `lockForUpdate()` mechanism
 * ObligationAllocationService already uses, applied to a different anchor.
 */
class BudgetService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function createBudget(
        User $user,
        Category $category,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        string $budgetAmount,
        bool $isMandatoryReserve = false,
    ): Budget {
        $this->ownership->assertCategoryOwnership($category, $user->id);

        return DB::transaction(function () use ($user, $category, $periodStart, $periodEnd, $budgetAmount, $isMandatoryReserve) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            if ($this->overlaps($user->id, $category->id, $periodStart, $periodEnd)) {
                throw new BudgetException('A budget already exists for this category during an overlapping period.');
            }

            return Budget::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'budget_amount' => $budgetAmount,
                'is_mandatory_reserve' => $isMandatoryReserve,
            ]);
        });
    }

    public function updateBudget(
        User $user,
        Budget $budget,
        Category $category,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        string $budgetAmount,
        bool $isMandatoryReserve = false,
    ): Budget {
        $this->ownership->assertBudgetOwnership($budget, $user->id);
        $this->ownership->assertCategoryOwnership($category, $user->id);

        return DB::transaction(function () use ($user, $budget, $category, $periodStart, $periodEnd, $budgetAmount, $isMandatoryReserve) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            if ($this->overlaps($user->id, $category->id, $periodStart, $periodEnd, excludeBudgetId: $budget->id)) {
                throw new BudgetException('Another budget already exists for this category during an overlapping period.');
            }

            $budget->update([
                'category_id' => $category->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'budget_amount' => $budgetAmount,
                'is_mandatory_reserve' => $isMandatoryReserve,
            ]);

            return $budget->fresh();
        });
    }

    /**
     * Net Budget Utilization = SUM(OUTFLOW) - SUM(INFLOW), restricted to
     * ledger entries whose transaction belongs to the budget's category and
     * whose own transaction_date falls within the budget's period (BR-059).
     *
     * The eligible-transaction-type restriction (EXPENSE/REFUND/REVERSAL)
     * is retained: it is mathematically inert for TRANSFER (whose balanced
     * OUTFLOW/INFLOW pair always nets to zero) and is the only thing
     * excluding ADJUSTMENT, per BR-025. The calculation itself is a single
     * uniform direction-based sum, never a per-type formula -- a Reversal
     * of an Expense, and a Reversal of that Reversal, are handled with no
     * type-specific branching whatsoever.
     */
    public function calculateUtilization(Budget $budget): string
    {
        $eligibleTypes = ['EXPENSE', 'REFUND', 'REVERSAL'];

        $outflow = (string) DB::table('ledger_entries')
            ->join('transactions', 'transactions.id', '=', 'ledger_entries.transaction_id')
            ->where('transactions.user_id', $budget->user_id)
            ->where('transactions.category_id', $budget->category_id)
            ->whereIn('transactions.transaction_type', $eligibleTypes)
            ->whereBetween('transactions.transaction_date', [$budget->period_start, $budget->period_end])
            ->where('ledger_entries.direction', 'OUTFLOW')
            ->selectRaw('COALESCE(SUM(ledger_entries.amount), 0) as total')
            ->value('total');

        $inflow = (string) DB::table('ledger_entries')
            ->join('transactions', 'transactions.id', '=', 'ledger_entries.transaction_id')
            ->where('transactions.user_id', $budget->user_id)
            ->where('transactions.category_id', $budget->category_id)
            ->whereIn('transactions.transaction_type', $eligibleTypes)
            ->whereBetween('transactions.transaction_date', [$budget->period_start, $budget->period_end])
            ->where('ledger_entries.direction', 'INFLOW')
            ->selectRaw('COALESCE(SUM(ledger_entries.amount), 0) as total')
            ->value('total');

        return Money::sub($outflow, $inflow);
    }

    private function overlaps(
        int $userId,
        int $categoryId,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        ?int $excludeBudgetId = null,
    ): bool {
        return Budget::query()
            ->where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('period_start', '<=', $periodEnd)
            ->where('period_end', '>=', $periodStart)
            ->when($excludeBudgetId !== null, fn ($query) => $query->where('id', '!=', $excludeBudgetId))
            ->exists();
    }
}
