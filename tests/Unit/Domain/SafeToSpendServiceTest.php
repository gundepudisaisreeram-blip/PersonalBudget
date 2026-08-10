<?php

namespace Tests\Unit\Domain;

use App\Domain\Services\AccountBalanceService;
use App\Domain\Services\BudgetService;
use App\Domain\Services\ObligationAllocationService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\SafeToSpendService;
use App\Domain\Services\TransactionService;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Deterministic-formula tests for SafeToSpendService (Phase 5 Decision
 * Package v1.2.3 section 9). Every scenario is hand-computed.
 */
class SafeToSpendServiceTest extends TestCase
{
    use RefreshDatabase;

    private SafeToSpendService $safeToSpend;

    private TransactionService $transactions;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = new OwnershipGuard;
        $this->safeToSpend = new SafeToSpendService(new AccountBalanceService, new BudgetService($guard));
        $this->transactions = new TransactionService($guard);
    }

    private function today(): Carbon
    {
        return Carbon::now(config('app.timezone'))->startOfDay();
    }

    private function makeObligation(User $user, Category $category, string $plannedAmount, bool $mandatory, string $status, ?Carbon $today = null): PaymentObligation
    {
        $today ??= $this->today();

        return PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-'.uniqid(),
            'category_id' => $category->id,
            'period_start' => $today->copy()->startOfMonth(),
            'period_end' => $today->copy()->endOfMonth(),
            'due_date' => $today,
            'planned_amount' => $plannedAmount,
            'is_mandatory' => $mandatory,
            'status' => $status,
        ]);
    }

    // -----------------------------------------------------------------
    // BR-028 Current Asset Balance
    // -----------------------------------------------------------------

    public function test_current_asset_balance_sums_asset_accounts_and_excludes_liabilities(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '500.00']);
        Account::factory()->for($user)->create(['account_type' => 'LIABILITY', 'opening_balance' => '2000.00']);

        $this->assertSame('1500.00', $this->safeToSpend->calculateCurrentAssetBalance($user));
    }

    public function test_current_asset_balance_includes_a_closed_asset_account_with_a_balance(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00', 'status' => 'CLOSED']);

        $this->assertSame('1000.00', $this->safeToSpend->calculateCurrentAssetBalance($user));
    }

    public function test_current_asset_balance_excludes_a_closed_liability_account(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'LIABILITY', 'opening_balance' => '1000.00', 'status' => 'CLOSED']);

        $this->assertSame('0.00', $this->safeToSpend->calculateCurrentAssetBalance($user));
    }

    public function test_current_asset_balance_reflects_actual_ledger_activity_via_account_balance_service(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $this->transactions->recordExpense($user, $account, '300.00', $this->today(), 'Groceries');

        $this->assertSame('700.00', $this->safeToSpend->calculateCurrentAssetBalance($user));
    }

    // -----------------------------------------------------------------
    // BR-029 / BR-030 Fixed vs Investment partition
    // -----------------------------------------------------------------

    public function test_mandatory_obligation_on_a_non_investment_category_counts_as_fixed_only(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $this->makeObligation($user, $category, '1000.00', true, 'PENDING');

        $this->assertSame('1000.00', $this->safeToSpend->calculatePendingMandatoryFixedObligations($user, $this->today()));
        $this->assertSame('0.00', $this->safeToSpend->calculatePendingMandatoryInvestments($user, $this->today()));
    }

    public function test_mandatory_obligation_on_an_investment_category_counts_as_investment_only(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'INVESTMENT']);
        $this->makeObligation($user, $category, '1000.00', true, 'PENDING');

        $this->assertSame('0.00', $this->safeToSpend->calculatePendingMandatoryFixedObligations($user, $this->today()));
        $this->assertSame('1000.00', $this->safeToSpend->calculatePendingMandatoryInvestments($user, $this->today()));
    }

    public function test_non_mandatory_obligation_is_excluded_from_both_fixed_and_investment(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'INVESTMENT']);
        $this->makeObligation($user, $category, '1000.00', false, 'PENDING');

        $this->assertSame('0.00', $this->safeToSpend->calculatePendingMandatoryFixedObligations($user, $this->today()));
        $this->assertSame('0.00', $this->safeToSpend->calculatePendingMandatoryInvestments($user, $this->today()));
    }

    public function test_skipped_and_cancelled_obligations_are_excluded(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $this->makeObligation($user, $category, '1000.00', true, 'SKIPPED');
        $this->makeObligation($user, $category, '1000.00', true, 'CANCELLED');

        $this->assertSame('0.00', $this->safeToSpend->calculatePendingMandatoryFixedObligations($user, $this->today()));
    }

    public function test_paid_obligation_is_excluded_from_fixed_obligations(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $this->makeObligation($user, $category, '1000.00', true, 'PAID');

        $this->assertSame('0.00', $this->safeToSpend->calculatePendingMandatoryFixedObligations($user, $this->today()));
    }

    public function test_outstanding_amount_subtracts_allocated_total(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '5000.00']);
        $obligation = $this->makeObligation($user, $category, '1000.00', true, 'PARTIALLY_PAID');
        $expense = $this->transactions->recordExpense($user, $account, '400.00', $this->today(), 'Partial payment');

        $allocations = new ObligationAllocationService(new OwnershipGuard);
        $allocations->allocate($user, $obligation, $expense, '400.00');

        $this->assertSame('600.00', $this->safeToSpend->calculatePendingMandatoryFixedObligations($user, $this->today()));
    }

    public function test_obligation_outside_the_current_period_is_excluded(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $lastMonth = $this->today()->copy()->subMonthNoOverflow();
        PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-last-month',
            'category_id' => $category->id,
            'period_start' => $lastMonth->copy()->startOfMonth(),
            'period_end' => $lastMonth->copy()->endOfMonth(),
            'due_date' => $lastMonth,
            'planned_amount' => '1000.00',
            'is_mandatory' => true,
            'status' => 'PENDING',
        ]);

        $this->assertSame('0.00', $this->safeToSpend->calculatePendingMandatoryFixedObligations($user, $this->today()));
    }

    public function test_obligation_whose_period_start_equals_today_is_included(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $today = $this->today();
        PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-boundary-start',
            'category_id' => $category->id,
            'period_start' => $today,
            'period_end' => $today->copy()->addDays(5),
            'due_date' => $today,
            'planned_amount' => '1000.00',
            'is_mandatory' => true,
            'status' => 'PENDING',
        ]);

        $this->assertSame('1000.00', $this->safeToSpend->calculatePendingMandatoryFixedObligations($user, $today));
    }

    public function test_obligation_whose_period_end_equals_today_is_included(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $today = $this->today();
        PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-boundary-end',
            'category_id' => $category->id,
            'period_start' => $today->copy()->subDays(5),
            'period_end' => $today,
            'due_date' => $today,
            'planned_amount' => '1000.00',
            'is_mandatory' => true,
            'status' => 'PENDING',
        ]);

        $this->assertSame('1000.00', $this->safeToSpend->calculatePendingMandatoryFixedObligations($user, $today));
    }

    // -----------------------------------------------------------------
    // BR-031 / BR-032 / BR-034 composition
    // -----------------------------------------------------------------

    public function test_safe_balance_and_safe_to_spend_formula_composition_hand_computed(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $fixedCategory = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $investmentCategory = Category::factory()->for($user)->create(['category_type' => 'INVESTMENT']);
        $this->makeObligation($user, $fixedCategory, '2000.00', true, 'PENDING');
        $this->makeObligation($user, $investmentCategory, '1500.00', true, 'PENDING');

        $budgetCategory = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $budgetService = new BudgetService(new OwnershipGuard);
        $budgetService->createBudget($user, $budgetCategory, $this->today()->copy()->startOfMonth(), $this->today()->copy()->endOfMonth(), '1000.00');

        $snapshot = $this->safeToSpend->snapshot($user, $this->today());

        // Safe Balance = 10000 - 2000 - 1500 = 6500
        $this->assertSame('10000.00', $snapshot['current_asset_balance']);
        $this->assertSame('2000.00', $snapshot['pending_mandatory_fixed_obligations']);
        $this->assertSame('1500.00', $snapshot['pending_mandatory_investments']);
        $this->assertSame('6500.00', $snapshot['safe_balance']);

        // Variable Budget Reserve = MAX(1000 - 0, 0) = 1000
        $this->assertSame('1000.00', $snapshot['variable_budget_reserve']);

        // Safe-to-Spend = 6500 - 1000 = 5500
        $this->assertSame('5500.00', $snapshot['safe_to_spend']);
    }

    public function test_negative_safe_to_spend_is_not_clamped_to_zero(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '100.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $this->makeObligation($user, $category, '5000.00', true, 'PENDING');

        $snapshot = $this->safeToSpend->snapshot($user, $this->today());

        $this->assertSame('-4900.00', $snapshot['safe_balance']);
        $this->assertSame('-4900.00', $snapshot['safe_to_spend']);
    }

    // -----------------------------------------------------------------
    // BR-027 / BR-033 Variable Budget Reserve floor vs unfloored utilization
    // -----------------------------------------------------------------

    public function test_variable_budget_reserve_floors_an_overspent_budget_to_zero(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $budgetService = new BudgetService(new OwnershipGuard);
        $budgetService->createBudget($user, $category, $this->today()->copy()->startOfMonth(), $this->today()->copy()->endOfMonth(), '500.00');
        $this->transactions->recordExpense($user, $account, '800.00', $this->today(), 'Overspend', $category);

        // Utilization 800 > budget 500 -- unfloored remaining would be -300,
        // but the reserve contribution must floor to 0, never go negative.
        $this->assertSame('0.00', $this->safeToSpend->calculateVariableBudgetReserve($user, $this->today()));
    }

    public function test_paid_obligations_metric_counts_mandatory_and_non_mandatory_paid_obligations(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $this->makeObligation($user, $category, '1000.00', true, 'PAID');
        $this->makeObligation($user, $category, '500.00', false, 'PAID');
        $this->makeObligation($user, $category, '200.00', true, 'PENDING');

        $result = $this->safeToSpend->calculatePaidObligations($user, $this->today());

        $this->assertSame(2, $result['count']);
        $this->assertSame('1500.00', $result['total']);
    }
}
