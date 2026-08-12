<?php

namespace App\Domain\Services;

use App\Domain\Money;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 read-only reporting engine (PHASE_6_DECISION_PACKAGE.md v1.4.0).
 *
 * Every figure here is either a direct composition of an already-certified
 * Phase 1-5 service (BudgetService::calculateUtilization()) or a new,
 * independent read-only aggregate query owned entirely by this service.
 * AccountBalanceService, BudgetService's mutation methods, and every other
 * certified financial write path are never touched or reimplemented here.
 *
 * Sign convention (mirrors AccountBalanceService::calculate() exactly):
 * ASSET + INFLOW = +amount, ASSET + OUTFLOW = -amount,
 * LIABILITY + INFLOW = -amount, LIABILITY + OUTFLOW = +amount.
 * Every bucket total below (Income/Expenses/Transfers/Investments/
 * Adjustments) is built from this identical signed contribution, which is
 * what makes the section 4.7 reconciliation invariant
 * (Opening + Income - Expenses +/- Transfers +/- Investments +/- Adjustments
 * = Closing) a structural guarantee: the five buckets partition every
 * ledger entry in the period exactly once, so their signed sum always
 * equals Closing - Opening by construction.
 */
class ReportingService
{
    /**
     * Canonical Date-Range Contract (Decision Package section 4.0 / Open
     * Decision 4). Both-omitted -> last 30 days. Both-supplied is validated
     * upstream by the Form Request (one-sided input rejected there); this
     * method only resolves the final Carbon boundaries.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveDateRange(?string $start, ?string $end, string $timezone): array
    {
        if ($start === null && $end === null) {
            $endDate = Carbon::now($timezone)->startOfDay();

            return [$endDate->copy()->subDays(29), $endDate];
        }

        return [
            Carbon::parse($start, $timezone)->startOfDay(),
            Carbon::parse($end, $timezone)->startOfDay(),
        ];
    }

    /**
     * Open Decision 14: default universe = all ASSET+LIABILITY accounts;
     * an explicit selection is a tenant-validated multi-account set (Open
     * Decision 13, Option D -- repeated/duplicate values collapse to a set).
     * Every ID is independently validated via the certified tenant-scoped
     * existence-check pattern (`$user->accounts()->findOrFail($id)`) --
     * a foreign/unowned ID throws ModelNotFoundException, never silently
     * excluded and never allowed to contribute data.
     *
     * @param  array<int, mixed>  $accountIds
     * @return Collection<int, Account>
     */
    public function resolveAccountScope(User $user, array $accountIds): Collection
    {
        if ($accountIds === []) {
            return $user->accounts()
                ->whereIn('account_type', ['ASSET', 'LIABILITY'])
                ->get()
                ->keyBy('id');
        }

        $uniqueIds = collect($accountIds)->map(fn ($id) => (int) $id)->unique();

        return $uniqueIds->mapWithKeys(function (int $id) use ($user) {
            $account = $user->accounts()->findOrFail($id);

            return [$account->id => $account];
        });
    }

    /**
     * Open Decision 2, Option B: HistoricalBalance(account, D) = the
     * account's balance as of the end of D, computed entirely from existing
     * ledger data. AccountBalanceService is never modified or called here --
     * this is an independent calculation that MUST satisfy the Historical
     * Balance Equivalence Invariant against it at D = today (proven by
     * HistoricalBalanceEquivalenceTest, not by sharing code).
     */
    public function historicalBalance(Account $account, Carbon $asOfDate): string
    {
        $boundary = $asOfDate->copy()->endOfDay()->toDateString();

        $inflow = (string) DB::table('ledger_entries')
            ->join('transactions', 'transactions.id', '=', 'ledger_entries.transaction_id')
            ->where('ledger_entries.account_id', $account->id)
            ->where('ledger_entries.direction', 'INFLOW')
            ->where('transactions.transaction_date', '<=', $boundary)
            ->selectRaw('COALESCE(SUM(ledger_entries.amount), 0) as total')
            ->value('total');

        $outflow = (string) DB::table('ledger_entries')
            ->join('transactions', 'transactions.id', '=', 'ledger_entries.transaction_id')
            ->where('ledger_entries.account_id', $account->id)
            ->where('ledger_entries.direction', 'OUTFLOW')
            ->where('transactions.transaction_date', '<=', $boundary)
            ->selectRaw('COALESCE(SUM(ledger_entries.amount), 0) as total')
            ->value('total');

        $netInflow = Money::sub($inflow, $outflow);

        return $account->isAsset()
            ? Money::add($account->opening_balance, $netInflow)
            : Money::sub($account->opening_balance, $netInflow);
    }

    /**
     * Combined selected-scope balance (Open Decision 14-C = C1), summed via
     * the certified per-account-loop pattern (mirrors
     * SafeToSpendService::calculateCurrentAssetBalance()), not a raw SQL SUM.
     *
     * @param  Collection<int, Account>  $accounts
     */
    public function combinedHistoricalBalance(Collection $accounts, Carbon $asOfDate): string
    {
        $total = '0.00';

        foreach ($accounts as $account) {
            $total = Money::add($total, $this->historicalBalance($account, $asOfDate));
        }

        return $total;
    }

    /**
     * Full six-line Cash Flow Report (Decision Package section 4.1) plus
     * the section 4.7 reconciliation check.
     *
     * @param  Collection<int, Account>  $accounts
     * @return array<string, mixed>
     */
    public function cashFlow(User $user, Collection $accounts, Carbon $start, Carbon $end): array
    {
        $accountIds = $accounts->keys()->all();
        $opening = $this->combinedHistoricalBalance($accounts, $start->copy()->subDay());
        $closing = $this->combinedHistoricalBalance($accounts, $end);

        $reversalLegs = $this->reversalLegCounts($user, $start, $end);
        $expenseIds = $reversalLegs['single_leg'];
        $transferIds = $reversalLegs['two_leg'];

        $income = $this->bucketSignedTotal($user, $accountIds, $start, $end, function ($query) {
            $query->where('transaction_type', 'INCOME');
        });

        $expensesSigned = $this->bucketSignedTotal($user, $accountIds, $start, $end, function ($query) use ($expenseIds) {
            $query->where(function ($q) use ($expenseIds) {
                $q->whereIn('transactions.transaction_type', ['EXPENSE', 'REFUND'])
                    ->orWhereIn('transactions.id', $expenseIds === [] ? [-1] : $expenseIds);
            });
        });

        $adjustments = $this->bucketSignedTotal($user, $accountIds, $start, $end, function ($query) {
            $query->where('transactions.transaction_type', 'ADJUSTMENT');
        });

        $transferSplit = $this->transferAndInvestmentTotals($user, $accountIds, $start, $end, $transferIds);

        $expenses = Money::sub('0.00', $expensesSigned);

        $reconciliationTotal = Money::add(
            Money::add($income, $transferSplit['transfers']),
            Money::add($transferSplit['investments'], $adjustments)
        );
        $reconciledClosing = Money::add($opening, Money::sub($reconciliationTotal, $expenses));

        return [
            'opening_balance' => $opening,
            'income' => $income,
            'expenses' => $expenses,
            'transfers' => $transferSplit['transfers'],
            'outbound_transfer' => $transferSplit['outbound'],
            'inbound_transfer' => $transferSplit['inbound'],
            'investments' => $transferSplit['investments'],
            'adjustments' => $adjustments,
            'closing_balance' => $closing,
            'reconciled' => Money::compare($reconciledClosing, $closing) === 0,
            'residual' => Money::sub($closing, $reconciledClosing),
        ];
    }

    /**
     * Category Spending Report (Decision Package section 4.1a, Open
     * Decision 10). Reuses the identical Open Decision 12 NET Expense
     * eligibility used by cashFlow()'s Expenses line -- grouped by
     * category instead of aggregated, with a presentation-only
     * "Uncategorized" bucket for NULL category_id.
     *
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, array{category: ?Category, net_expenses: string}>
     */
    public function categorySpending(User $user, Collection $accounts, Carbon $start, Carbon $end): Collection
    {
        $accountIds = $accounts->keys()->all();
        $reversalLegs = $this->reversalLegCounts($user, $start, $end);
        $expenseIds = $reversalLegs['single_leg'];

        $rows = DB::table('ledger_entries')
            ->join('transactions', 'transactions.id', '=', 'ledger_entries.transaction_id')
            ->join('accounts', 'accounts.id', '=', 'ledger_entries.account_id')
            ->where('transactions.user_id', $user->id)
            ->whereIn('ledger_entries.account_id', $accountIds === [] ? [-1] : $accountIds)
            ->whereBetween('transactions.transaction_date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($q) use ($expenseIds) {
                $q->whereIn('transactions.transaction_type', ['EXPENSE', 'REFUND'])
                    ->orWhereIn('transactions.id', $expenseIds === [] ? [-1] : $expenseIds);
            })
            ->groupBy('transactions.category_id', 'accounts.account_type', 'ledger_entries.direction')
            ->selectRaw('transactions.category_id, accounts.account_type, ledger_entries.direction, COALESCE(SUM(ledger_entries.amount), 0) as total')
            ->get();

        $byCategory = $rows->groupBy(fn ($row) => $row->category_id ?? 'uncategorized');

        $categories = Category::query()
            ->whereIn('id', $byCategory->keys()->filter(fn ($key) => $key !== 'uncategorized'))
            ->get()
            ->keyBy('id');

        return $byCategory->map(function (Collection $rows, $key) use ($categories) {
            $signed = $this->reduceSignedRows($rows);
            $netExpenses = Money::sub('0.00', $signed);

            return [
                'category' => $key === 'uncategorized' ? null : $categories->get($key),
                'net_expenses' => $netExpenses,
            ];
        })->values();
    }

    /**
     * Budget Report (section 4.2). Actual is delegated entirely to the
     * certified BudgetService::calculateUtilization() -- no budget
     * mathematics is reimplemented. Period membership uses Overlap
     * semantics (Open Decision 7); amount/period values use the Budget
     * row's currently stored values (Open Decision 11.A/11.B, Option A).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function budgetReport(User $user, BudgetService $budgets, Carbon $start, Carbon $end, ?int $categoryId = null): Collection
    {
        return Budget::query()
            ->where('user_id', $user->id)
            ->where('period_start', '<=', $end->toDateString())
            ->where('period_end', '>=', $start->toDateString())
            ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
            ->with('category')
            ->orderBy('period_start')
            ->get()
            ->map(function (Budget $budget) use ($budgets) {
                $actual = $budgets->calculateUtilization($budget);
                $remaining = Money::sub($budget->budget_amount, $actual);
                $utilization = Money::isZero($budget->budget_amount)
                    ? '0.00'
                    : bcmul(bcdiv($actual, $budget->budget_amount, 6), '100', 2);

                return [
                    'budget' => $budget,
                    'actual' => $actual,
                    'remaining' => $remaining,
                    'variance' => $remaining,
                    'utilization_percent' => $utilization,
                ];
            });
    }

    /**
     * Obligations Report (section 4.3). Period membership uses Overlap
     * semantics (Open Decision 7). Historical status is reconstructed as of
     * the report's end date via the frozen Open Decision 3 rule; Overdue is
     * evaluated as of that same end-date boundary (Open Decision 8, Option
     * C) -- never the obligation's live `status` column.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function obligationsReport(User $user, Carbon $start, Carbon $end, ?int $categoryId = null, ?string $status = null): Collection
    {
        $obligations = PaymentObligation::query()
            ->where('user_id', $user->id)
            ->where('period_start', '<=', $end->toDateString())
            ->where('period_end', '>=', $start->toDateString())
            ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
            ->with(['category', 'plannedAccount'])
            ->orderBy('due_date')
            ->get();

        $boundary = $end->copy()->endOfDay();

        $result = $obligations->map(function (PaymentObligation $obligation) use ($boundary) {
            $historicalStatus = $this->historicalObligationStatus($obligation, $boundary);
            $isOverdue = in_array($historicalStatus, ['PENDING', 'PARTIALLY_PAID'], true)
                && Carbon::parse($obligation->due_date)->lte($boundary);

            return [
                'obligation' => $obligation,
                'historical_status' => $historicalStatus,
                'is_overdue' => $isOverdue,
            ];
        });

        if ($status !== null) {
            $result = $status === 'OVERDUE'
                ? $result->filter(fn (array $row) => $row['is_overdue'])
                : $result->filter(fn (array $row) => $row['historical_status'] === $status);
        }

        return $result->values();
    }

    /**
     * Reconstructs a PaymentObligation's status as of a historical boundary
     * (Open Decision 3, Option B): baseline PENDING, then the latest
     * applicable OBLIGATION_STATUS_CHANGED audit transition at or before
     * the boundary. Never reads the live `status` column for a past date.
     */
    public function historicalObligationStatus(PaymentObligation $obligation, Carbon $boundary): string
    {
        $latest = AuditLog::query()
            ->where('entity_type', PaymentObligation::class)
            ->where('entity_id', $obligation->id)
            ->where('action', 'OBLIGATION_STATUS_CHANGED')
            ->where('created_at', '<=', $boundary)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return 'PENDING';
        }

        $newValues = is_array($latest->new_values) ? $latest->new_values : json_decode((string) $latest->new_values, true);

        return $newValues['status'] ?? 'PENDING';
    }

    /**
     * Trends Report (section 4.4): the Cash Flow formulas computed once per
     * calendar month across the requested range, plus the frozen Savings
     * formula (Open Decision 9): Savings = Income - Net Expenses -
     * Investments. The same account scope and date-range contract are held
     * constant across every plotted month.
     *
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, array<string, mixed>>
     */
    public function trends(User $user, Collection $accounts, Carbon $start, Carbon $end): Collection
    {
        $months = collect();
        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $monthStart = $cursor->copy()->max($start);
            $monthEnd = $cursor->copy()->endOfMonth()->min($end);

            $cashFlow = $this->cashFlow($user, $accounts, $monthStart, $monthEnd);

            // cashFlow['investments'] is signed the same way as the section
            // 4.7 reconciliation equation's +/-Investments term (negative
            // when money leaves the selected scope into an investment,
            // positive when it enters). Adding it here is mathematically
            // equivalent to subtracting the investment *magnitude*, which is
            // what the frozen Savings formula (Open Decision 9) describes.
            $savings = Money::add(Money::sub($cashFlow['income'], $cashFlow['expenses']), $cashFlow['investments']);

            $months->push([
                'month' => $cursor->copy()->format('Y-m'),
                'label' => $cursor->copy()->format('F Y'),
                'cash_flow' => $cashFlow,
                'savings' => $savings,
            ]);

            $cursor = $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return $months;
    }

    /**
     * Month Review (section 4.6): a read-only composition of Cash Flow,
     * Budget, Obligations, and Category Spending for one calendar month.
     * No new formula, no mutation, no close-state of any kind.
     *
     * @param  Collection<int, Account>  $accounts
     * @return array<string, mixed>
     */
    public function monthReview(User $user, BudgetService $budgets, Collection $accounts, Carbon $monthStart, Carbon $monthEnd): array
    {
        return [
            'cash_flow' => $this->cashFlow($user, $accounts, $monthStart, $monthEnd),
            'budgets' => $this->budgetReport($user, $budgets, $monthStart, $monthEnd),
            'obligations' => $this->obligationsReport($user, $monthStart, $monthEnd),
            'category_spending' => $this->categorySpending($user, $accounts, $monthStart, $monthEnd),
        ];
    }

    /**
     * Splits every REVERSAL transaction in the period into single-leg
     * (Expense/Refund/Reversal NET bucket, Open Decision 12) versus
     * two-leg (Reversal-of-Transfer, governed by Open Decision 14-G, not
     * the Expense formula) by directly inspecting its own ledger-entry
     * count -- the authoritative signal per 04_DATABASE_SPECIFICATION.md's
     * "Reversal mirrors the parent's complete ledger-entry structure".
     *
     * @return array{single_leg: array<int, int>, two_leg: array<int, int>}
     */
    private function reversalLegCounts(User $user, Carbon $start, Carbon $end): array
    {
        $counts = DB::table('ledger_entries')
            ->join('transactions', 'transactions.id', '=', 'ledger_entries.transaction_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.transaction_type', 'REVERSAL')
            ->whereBetween('transactions.transaction_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('ledger_entries.transaction_id')
            ->selectRaw('ledger_entries.transaction_id, COUNT(*) as leg_count')
            ->get();

        return [
            'single_leg' => $counts->where('leg_count', 1)->pluck('transaction_id')->all(),
            'two_leg' => $counts->where('leg_count', 2)->pluck('transaction_id')->all(),
        ];
    }

    /**
     * Signed contribution total (see class docblock) for every ledger entry
     * matching the transaction-level filter, restricted to the selected
     * account scope and date range.
     *
     * @param  array<int, int>  $accountIds
     */
    private function bucketSignedTotal(User $user, array $accountIds, Carbon $start, Carbon $end, \Closure $transactionFilter): string
    {
        $query = DB::table('ledger_entries')
            ->join('transactions', 'transactions.id', '=', 'ledger_entries.transaction_id')
            ->join('accounts', 'accounts.id', '=', 'ledger_entries.account_id')
            ->where('transactions.user_id', $user->id)
            ->whereIn('ledger_entries.account_id', $accountIds === [] ? [-1] : $accountIds)
            ->whereBetween('transactions.transaction_date', [$start->toDateString(), $end->toDateString()]);

        $transactionFilter($query);

        $rows = $query
            ->groupBy('accounts.account_type', 'ledger_entries.direction')
            ->selectRaw('accounts.account_type, ledger_entries.direction, COALESCE(SUM(ledger_entries.amount), 0) as total')
            ->get();

        return $this->reduceSignedRows($rows);
    }

    /**
     * @param  Collection<int, object{account_type: string, direction: string, total: mixed}>  $rows
     */
    private function reduceSignedRows(Collection $rows): string
    {
        $total = '0.00';

        foreach ($rows as $row) {
            $amount = (string) $row->total;
            $positive = ($row->account_type === 'ASSET' && $row->direction === 'INFLOW')
                || ($row->account_type === 'LIABILITY' && $row->direction === 'OUTFLOW');

            $total = $positive ? Money::add($total, $amount) : Money::sub($total, $amount);
        }

        return $total;
    }

    /**
     * Open Decision 5 (mutually exclusive Transfers/Investments partition)
     * composed with Open Decision 14-G (selected-scope Transfer
     * mathematics: NET for both-selected, explicit Outbound/Inbound term
     * for boundary-crossing, nothing for neither-selected).
     *
     * @param  array<int, int>  $accountIds
     * @param  array<int, int>  $transferReversalIds
     * @return array{transfers: string, investments: string, outbound: string, inbound: string}
     */
    private function transferAndInvestmentTotals(User $user, array $accountIds, Carbon $start, Carbon $end, array $transferReversalIds): array
    {
        $transferIds = DB::table('transactions')
            ->where('user_id', $user->id)
            ->where('transaction_type', 'TRANSFER')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('id')
            ->merge($transferReversalIds)
            ->unique()
            ->all();

        if ($transferIds === []) {
            return ['transfers' => '0.00', 'investments' => '0.00', 'outbound' => '0.00', 'inbound' => '0.00'];
        }

        $investmentIds = DB::table('transactions')
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->whereIn('transactions.id', $transferIds)
            ->where('categories.category_type', 'INVESTMENT')
            ->pluck('transactions.id')
            ->all();

        $ordinaryIds = array_values(array_diff($transferIds, $investmentIds));

        $accountSet = array_flip($accountIds);
        $legs = DB::table('ledger_entries')
            ->whereIn('transaction_id', $transferIds)
            ->select('transaction_id', 'account_id', 'direction', 'amount')
            ->get()
            ->groupBy('transaction_id');

        $accountTypes = Account::query()->whereIn('id', $accountIds)->pluck('account_type', 'id');

        $signedByTransaction = [];
        $outbound = '0.00';
        $inbound = '0.00';

        foreach ($legs as $transactionId => $transactionLegs) {
            $signed = '0.00';
            $outflowLeg = $transactionLegs->firstWhere('direction', 'OUTFLOW');
            $inflowLeg = $transactionLegs->firstWhere('direction', 'INFLOW');
            $sourceInScope = $outflowLeg && isset($accountSet[$outflowLeg->account_id]);
            $destinationInScope = $inflowLeg && isset($accountSet[$inflowLeg->account_id]);

            foreach ($transactionLegs as $leg) {
                if (! isset($accountSet[$leg->account_id])) {
                    continue;
                }

                $accountType = $accountTypes[$leg->account_id] ?? 'ASSET';
                $positive = ($accountType === 'ASSET' && $leg->direction === 'INFLOW')
                    || ($accountType === 'LIABILITY' && $leg->direction === 'OUTFLOW');
                $signed = $positive ? Money::add($signed, (string) $leg->amount) : Money::sub($signed, (string) $leg->amount);
            }

            $signedByTransaction[$transactionId] = $signed;

            if ($sourceInScope && ! $destinationInScope) {
                $outbound = Money::add($outbound, (string) $outflowLeg->amount);
            } elseif ($destinationInScope && ! $sourceInScope) {
                $inbound = Money::add($inbound, (string) $inflowLeg->amount);
            }
        }

        $transfersTotal = '0.00';
        foreach ($ordinaryIds as $id) {
            $transfersTotal = Money::add($transfersTotal, $signedByTransaction[$id] ?? '0.00');
        }

        $investmentsTotal = '0.00';
        foreach ($investmentIds as $id) {
            $investmentsTotal = Money::add($investmentsTotal, $signedByTransaction[$id] ?? '0.00');
        }

        return [
            'transfers' => $transfersTotal,
            'investments' => $investmentsTotal,
            'outbound' => $outbound,
            'inbound' => $inbound,
        ];
    }
}
