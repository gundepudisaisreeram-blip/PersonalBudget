<?php

namespace App\Domain\Services;

use App\Domain\Money;
use App\Models\Account;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Computes the Phase 5 dashboard's headline financial figures (BR-027 through
 * BR-034; Phase 5 Decision Package v1.2.3 section 9). Every formula here is
 * either a direct composition of certified Phase 1-4 service outputs
 * (AccountBalanceService::calculate(), BudgetService::calculateUtilization())
 * or a read-only aggregate query -- this service never reimplements ledger or
 * budget-utilization mathematics of its own.
 */
class SafeToSpendService
{
    public function __construct(
        private readonly AccountBalanceService $accountBalance,
        private readonly BudgetService $budgets,
    ) {}

    /**
     * @return array{
     *     current_asset_balance: string,
     *     pending_mandatory_fixed_obligations: string,
     *     pending_mandatory_investments: string,
     *     safe_balance: string,
     *     variable_budget_reserve: string,
     *     safe_to_spend: string,
     * }
     */
    public function snapshot(User $user, Carbon $today): array
    {
        $currentAssetBalance = $this->calculateCurrentAssetBalance($user);
        $fixedObligations = $this->calculatePendingMandatoryFixedObligations($user, $today);
        $investments = $this->calculatePendingMandatoryInvestments($user, $today);
        $safeBalance = $this->calculateSafeBalance($currentAssetBalance, $fixedObligations, $investments);
        $variableBudgetReserve = $this->calculateVariableBudgetReserve($user, $today);
        $safeToSpend = $this->calculateSafeToSpend($safeBalance, $variableBudgetReserve);

        return [
            'current_asset_balance' => $currentAssetBalance,
            'pending_mandatory_fixed_obligations' => $fixedObligations,
            'pending_mandatory_investments' => $investments,
            'safe_balance' => $safeBalance,
            'variable_budget_reserve' => $variableBudgetReserve,
            'safe_to_spend' => $safeToSpend,
        ];
    }

    /**
     * BR-028. Sums AccountBalanceService::calculate() once per included asset
     * account -- deliberately not a raw SQL SUM(), per the Decision Package's
     * frozen requirement that AccountBalanceService remain the sole
     * authoritative source. Includes CLOSED asset accounts; never includes
     * liability accounts regardless of status.
     */
    public function calculateCurrentAssetBalance(User $user): string
    {
        $total = '0.00';

        $assetAccounts = Account::query()
            ->where('user_id', $user->id)
            ->where('account_type', 'ASSET')
            ->get();

        foreach ($assetAccounts as $account) {
            $total = Money::add($total, $this->accountBalance->calculate($account));
        }

        return $total;
    }

    /**
     * BR-029. Mandatory, active (PENDING/PARTIALLY_PAID), current-period
     * obligations whose category is NOT the Investment classification.
     */
    public function calculatePendingMandatoryFixedObligations(User $user, Carbon $today): string
    {
        return $this->calculateOutstandingMandatoryObligations($user, $today, investment: false);
    }

    /**
     * BR-030. Same scope as BR-029, restricted to the Investment
     * classification (category_type === 'INVESTMENT'). Mutually exclusive
     * with calculatePendingMandatoryFixedObligations() by construction --
     * both share the identical base filter and partition on the same
     * boolean condition.
     */
    public function calculatePendingMandatoryInvestments(User $user, Carbon $today): string
    {
        return $this->calculateOutstandingMandatoryObligations($user, $today, investment: true);
    }

    private function calculateOutstandingMandatoryObligations(User $user, Carbon $today, bool $investment): string
    {
        $todayString = $today->toDateString();
        $operator = $investment ? '=' : '!=';

        $plannedTotal = (string) DB::table('payment_obligations')
            ->join('categories', 'categories.id', '=', 'payment_obligations.category_id')
            ->where('payment_obligations.user_id', $user->id)
            ->where('payment_obligations.is_mandatory', true)
            ->whereIn('payment_obligations.status', ['PENDING', 'PARTIALLY_PAID'])
            ->where('payment_obligations.period_start', '<=', $todayString)
            ->where('payment_obligations.period_end', '>=', $todayString)
            ->where('categories.category_type', $operator, 'INVESTMENT')
            ->selectRaw('COALESCE(SUM(payment_obligations.planned_amount), 0) as total')
            ->value('total');

        $allocatedTotal = (string) DB::table('obligation_allocations')
            ->join('payment_obligations', 'payment_obligations.id', '=', 'obligation_allocations.payment_obligation_id')
            ->join('categories', 'categories.id', '=', 'payment_obligations.category_id')
            ->where('payment_obligations.user_id', $user->id)
            ->where('payment_obligations.is_mandatory', true)
            ->whereIn('payment_obligations.status', ['PENDING', 'PARTIALLY_PAID'])
            ->where('payment_obligations.period_start', '<=', $todayString)
            ->where('payment_obligations.period_end', '>=', $todayString)
            ->where('categories.category_type', $operator, 'INVESTMENT')
            ->selectRaw('COALESCE(SUM(obligation_allocations.allocated_amount), 0) as total')
            ->value('total');

        // planned_amount - SUM(allocated_amount), unfloored, per the approved
        // Decision Package formula. Not floored at 0 -- the certified Phase 4
        // ObligationAllocationService::allocate() guarantees, for every row
        // written through the application, that the aggregate allocation
        // against an obligation can never exceed its planned_amount (see the
        // Implementation Report's P3 invariant verification).
        return Money::sub($plannedTotal, $allocatedTotal);
    }

    /** BR-031, verbatim formula. */
    public function calculateSafeBalance(string $currentAssetBalance, string $fixedObligations, string $investments): string
    {
        return Money::sub(Money::sub($currentAssetBalance, $fixedObligations), $investments);
    }

    /**
     * BR-033 + BR-027. Sums MAX(budget_amount - utilization, 0) per current-
     * period budget. BudgetService::calculateUtilization() is consumed
     * exactly as certified in Phase 4 -- no budget-utilization mathematics
     * are reimplemented here.
     */
    public function calculateVariableBudgetReserve(User $user, Carbon $today): string
    {
        $todayString = $today->toDateString();

        $budgets = Budget::query()
            ->where('user_id', $user->id)
            ->where('period_start', '<=', $todayString)
            ->where('period_end', '>=', $todayString)
            ->get();

        $reserve = '0.00';

        foreach ($budgets as $budget) {
            $remaining = Money::sub($budget->budget_amount, $this->budgets->calculateUtilization($budget));
            $floored = Money::isPositive($remaining) ? $remaining : '0.00';
            $reserve = Money::add($reserve, $floored);
        }

        return $reserve;
    }

    /** BR-032, verbatim formula. */
    public function calculateSafeToSpend(string $safeBalance, string $variableBudgetReserve): string
    {
        return Money::sub($safeBalance, $variableBudgetReserve);
    }

    /**
     * Phase 5 presentation metric (not a business rule): count and total
     * planned amount of every PAID obligation in the current period,
     * mandatory and non-mandatory alike.
     *
     * @return array{count: int, total: string}
     */
    public function calculatePaidObligations(User $user, Carbon $today): array
    {
        $todayString = $today->toDateString();

        $row = DB::table('payment_obligations')
            ->where('user_id', $user->id)
            ->where('status', 'PAID')
            ->where('period_start', '<=', $todayString)
            ->where('period_end', '>=', $todayString)
            ->selectRaw('COUNT(*) as obligation_count, COALESCE(SUM(planned_amount), 0) as total_amount')
            ->first();

        return [
            'count' => (int) $row->obligation_count,
            'total' => (string) $row->total_amount,
        ];
    }
}
