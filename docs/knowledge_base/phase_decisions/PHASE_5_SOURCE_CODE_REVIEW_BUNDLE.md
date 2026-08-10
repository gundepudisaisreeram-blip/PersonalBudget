# PHASE 5 — SOURCE CODE REVIEW BUNDLE

Exact, unabridged current contents of every new/modified Phase 5 file, reproduced directly from the repository. Audit observations are kept separate from source listings.

---

## A. `app/Domain/Services/SafeToSpendService.php` (new)

```php
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
```

**Auditor checklist:**
- BR-028: `foreach ($assetAccounts as $account) { $total = Money::add($total, $this->accountBalance->calculate($account)); }` — per-account service call, no raw SQL SUM.
- BR-029/030 mutual exclusivity: identical query twice, differing only in `$operator` (`'='` vs `'!='`) applied to `categories.category_type` against `'INVESTMENT'` — a logical complement over the same base filter.
- Unfloored outstanding amount: `Money::sub($plannedTotal, $allocatedTotal)` — no `MAX(...,0)` anywhere in this method.
- BR-031/032: single-line `Money::sub()` compositions, verbatim to the frozen formulas.
- BR-033/027: `Money::isPositive($remaining) ? $remaining : '0.00'` — the per-budget floor, applied before summing into `$reserve`.
- `BudgetService::calculateUtilization($budget)` is the only place utilization is computed — called once per budget, never reimplemented.

---

## B. `app/Http/Controllers/DashboardController.php` (new)

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Money;
use App\Domain\Services\AccountBalanceService;
use App\Domain\Services\BudgetService;
use App\Domain\Services\SafeToSpendService;
use App\Models\Budget;
use App\Models\PaymentObligation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Thin, read-only dashboard controller (Phase 5 Decision Package v1.2.3).
 * Every financial figure is delegated to SafeToSpendService/BudgetService/
 * AccountBalanceService -- no calculation occurs here.
 */
class DashboardController extends Controller
{
    public function index(
        Request $request,
        SafeToSpendService $safeToSpend,
        BudgetService $budgets,
        AccountBalanceService $accountBalance,
    ): View {
        $user = $request->user();
        $timezone = $user->timezone ?? config('app.timezone');
        $today = Carbon::now($timezone)->startOfDay();
        $todayString = $today->toDateString();

        $snapshot = $safeToSpend->snapshot($user, $today);
        $paidObligations = $safeToSpend->calculatePaidObligations($user, $today);

        $currentBudgets = Budget::query()
            ->where('user_id', $user->id)
            ->where('period_start', '<=', $todayString)
            ->where('period_end', '>=', $todayString)
            ->with('category')
            ->get()
            ->map(function (Budget $budget) use ($budgets) {
                $utilization = $budgets->calculateUtilization($budget);
                $percent = Money::isZero($budget->budget_amount)
                    ? '0.00'
                    : bcmul(bcdiv($utilization, $budget->budget_amount, 6), '100', 2);

                return [
                    'budget' => $budget,
                    'utilization' => $utilization,
                    'remaining' => Money::sub($budget->budget_amount, $utilization),
                    'percent' => $percent,
                    'overspend' => Money::isGreaterThan($utilization, $budget->budget_amount),
                ];
            })
            ->sort(function (array $a, array $b) {
                $byUtilizationDesc = bccomp($b['percent'], $a['percent'], 2);

                return $byUtilizationDesc !== 0
                    ? $byUtilizationDesc
                    : ($a['budget']->category?->name <=> $b['budget']->category?->name);
            })
            ->values();

        $upcomingPayments = PaymentObligation::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['PENDING', 'PARTIALLY_PAID'])
            ->where('due_date', '>=', $todayString)
            ->where('due_date', '<=', $today->copy()->addDays(13)->toDateString())
            ->with(['category', 'plannedAccount'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $overdueObligations = PaymentObligation::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['PENDING', 'PARTIALLY_PAID'])
            ->where('due_date', '<', $todayString)
            ->with('category')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $overspentBudgets = $currentBudgets->filter(fn (array $row) => $row['overspend'])->values();

        $accounts = $user->accounts()
            ->orderBy('name')
            ->get()
            ->map(fn ($account) => [
                'account' => $account,
                'balance' => $accountBalance->calculate($account),
            ]);

        $attentionCenter = $this->buildAttentionCenter(
            $snapshot['safe_to_spend'],
            $overdueObligations,
            $overspentBudgets,
        );

        return view('dashboard.index', [
            'snapshot' => $snapshot,
            'safeToSpendNegative' => Money::compare($snapshot['safe_to_spend'], '0.00') < 0,
            'paidObligations' => $paidObligations,
            'budgets' => $currentBudgets,
            'upcomingPayments' => $upcomingPayments,
            'accounts' => $accounts,
            'attentionCenter' => $attentionCenter,
        ]);
    }

    /**
     * Exactly the three Decision Package v1.2.3 section 13 rules, in the
     * frozen priority order: Negative Safe-to-Spend, Overdue Obligation,
     * Budget Overspend. No other alert type is evaluated.
     *
     * @return array<int, array{type: string, severity: string, priority: int, item: mixed}>
     */
    private function buildAttentionCenter(string $safeToSpend, $overdueObligations, $overspentBudgets): array
    {
        $items = [];

        if (Money::compare($safeToSpend, '0.00') < 0) {
            $items[] = [
                'type' => 'negative_safe_to_spend',
                'severity' => 'critical',
                'priority' => 1,
                'item' => $safeToSpend,
            ];
        }

        foreach ($overdueObligations as $obligation) {
            $items[] = [
                'type' => 'overdue_obligation',
                'severity' => 'high',
                'priority' => 2,
                'item' => $obligation,
            ];
        }

        foreach ($overspentBudgets as $row) {
            $items[] = [
                'type' => 'budget_overspend',
                'severity' => 'medium',
                'priority' => 3,
                'item' => $row,
            ];
        }

        return $items;
    }
}
```

**Auditor checklist:**
- Zero writes: no `::create()`, `::update()`, `::delete()`, or `DB::transaction()` anywhere in this file.
- Upcoming Payments window: `where('due_date', '>=', $todayString)->where('due_date', '<=', $today->copy()->addDays(13)->toDateString())` — exactly `today` through `today+13`, matching Decision Package §15.
- Overdue: `where('due_date', '<', $todayString)` — strictly before today, ordered `due_date` then `id`.
- Attention Center priority: negative Safe-to-Spend pushed first (priority 1), then all overdue obligations (priority 2, in `due_date`/`id` order from the query), then all overspent budgets (priority 3, in the pre-sorted order from `$currentBudgets`) — array insertion order matches the frozen priority list.
- Budget Snapshot ordering: `bccomp($b['percent'], $a['percent'], 2)` (descending) then category-name `<=>` (ascending tie-break).

---

## C. `resources/views/dashboard/index.blade.php` (new)

Not reproduced in full here (163 lines of presentation markup, no financial calculation) — directly readable in the working tree. Confirmed by inspection: every dynamic value is either a pass-through of a controller-computed string (`{{ $snapshot['...'] }}`, `{{ $row['...'] }}`) or plain conditional display logic (`@if`/`@foreach`) — no arithmetic operator appears anywhere in the file except within the already-reviewed `DashboardController.php`. The `Set up your first account to begin.` empty-state string matches `05_UI_UX_SPECIFICATION.md` §13 verbatim.

---

## D. Supporting file — `app/Domain/Services/ObligationAllocationService.php` (Phase 4, certified, UNMODIFIED)

Reproduced for the P3 hard-stop verification only — this file was not touched:

```php
<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\AllocationException;
use App\Domain\Money;
use App\Models\AuditLog;
use App\Models\ObligationAllocation;
use App\Models\PaymentObligation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ObligationAllocationService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function allocate(
        User $user,
        PaymentObligation $obligation,
        Transaction $transaction,
        string $amount,
    ): ObligationAllocation {
        $this->ownership->assertPaymentObligationOwnership($obligation, $user->id);
        $this->ownership->assertTransactionOwnership($transaction, $user->id);

        if (! Money::isPositive($amount)) {
            throw new AllocationException('Allocated amount must be greater than zero.');
        }

        if (! $transaction->isEligibleForAllocation()) {
            throw new AllocationException('Only EXPENSE and TRANSFER transactions may fulfill a payment obligation.');
        }

        return DB::transaction(function () use ($user, $obligation, $transaction, $amount) {
            $locked = PaymentObligation::query()->lockForUpdate()->findOrFail($obligation->id);

            if (! $locked->isActive()) {
                throw new AllocationException('Cannot allocate against a SKIPPED or CANCELLED obligation.');
            }

            $currentTotal = $this->allocatedTotal($locked->id);
            $newTotal = Money::add($currentTotal, $amount);

            if (Money::isGreaterThan($newTotal, $locked->planned_amount)) {
                throw new AllocationException('Allocation total cannot exceed the obligation\'s planned amount.');
            }

            $allocation = ObligationAllocation::create([
                'user_id' => $user->id,
                'payment_obligation_id' => $locked->id,
                'transaction_id' => $transaction->id,
                'allocated_amount' => $amount,
            ]);

            $this->recalculateStatus($locked, $newTotal);

            AuditLog::create([/* ... audit fields, unchanged ... */]);

            return $allocation;
        });
    }

    // removeAllocation(), skip(), cancel(), transitionToTerminalState(),
    // allocatedTotal(), recalculateStatus() -- all unchanged, not reproduced
    // here (not relevant to the P3 invariant question).
}
```

**Auditor checklist for the P3 invariant:** the `if (Money::isGreaterThan($newTotal, $locked->planned_amount)) { throw ... }` check occurs strictly before `ObligationAllocation::create()`, inside a `DB::transaction()` that began with `PaymentObligation::query()->lockForUpdate()->findOrFail(...)` — the row lock serializes concurrent callers, so the check-then-write is race-free. Grep confirms `ObligationAllocation::create(` appears exactly once in `app/`. Conclusion: `SUM(allocated_amount) <= planned_amount` is guaranteed for every row written through the application.

---

## E. Diffs — modified files

**`routes/web.php`:**
```diff
+use App\Http\Controllers\DashboardController;
...
+    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
```

**`resources/views/layouts/app.blade.php`:**
```diff
+                        <li class="nav-item">
+                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active fw-bold' : '' }}" href="{{ url('/dashboard') }}">Dashboard</a>
+                        </li>
```

**`bootstrap/app.php`:**
```diff
-        $middleware->redirectUsersTo('/accounts');
+        $middleware->redirectUsersTo('/dashboard');
```

**`app/Http/Controllers/Auth/AuthenticatedSessionController.php`:**
```diff
-        return redirect()->intended('/accounts');
+        return redirect()->intended('/dashboard');
```
This is the fix documented in the Implementation Report's "P0 governance conflict" section — necessary to complete the redirect, not on the §19b forbidden list, but the direct cause of the two now-failing `AuthenticationTest.php` assertions (which were NOT modified, per the forbidden-file rule).

---

## ITEMS FOR INDEPENDENT AUDITOR ATTENTION

1. **The P0 governance conflict** (Implementation Report, Audit Bundle section O) — requires explicit resolution.
2. `SafeToSpendService`'s method signatures deviate from the Decision Package §6 proposal (`Carbon $today` instead of `Carbon $periodStart, Carbon $periodEnd`) — explicitly permitted as an implementation detail, flagged for confirmation.
3. `bcmul`/`bcdiv` are used directly in `DashboardController` for the display-only utilization percentage (not part of any frozen formula in section 9) — the auditor may wish to confirm this is acceptable as a presentation-layer computation rather than a "financial calculation in the controller," since `03_ARCHITECTURE.md`/the Decision Package's "no financial calculation in the controller" principle is otherwise upheld for every actual money figure (all of which come from `SafeToSpendService`/`BudgetService`/`AccountBalanceService`).

---

## GOVERNANCE STATUS

SOURCE-CODE REVIEW BUNDLE COMPLETE.

PHASE 5 IMPLEMENTATION IS NOT CERTIFIED.

AWAITING INDEPENDENT CHATGPT/GEMINI SOURCE-CODE ADVERSARIAL REVIEW.

NO COMMIT.
NO PUSH.
NO PHASE 6.
