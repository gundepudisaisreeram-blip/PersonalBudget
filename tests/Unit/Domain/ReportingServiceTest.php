<?php

namespace Tests\Unit\Domain;

use App\Domain\Services\AccountBalanceService;
use App\Domain\Services\BudgetService;
use App\Domain\Services\ObligationAllocationService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\PaymentObligationService;
use App\Domain\Services\RefundService;
use App\Domain\Services\ReportingService;
use App\Domain\Services\ReversalService;
use App\Domain\Services\TransactionService;
use App\Domain\Services\TransferService;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Deterministic, hand-computed formula tests for ReportingService
 * (PHASE_6_DECISION_PACKAGE.md v1.4.0, sections 4.0-4.7).
 */
class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportingService $reports;

    private TransactionService $transactions;

    private TransferService $transfers;

    private RefundService $refunds;

    private ReversalService $reversals;

    private BudgetService $budgets;

    private PaymentObligationService $obligations;

    private ObligationAllocationService $allocations;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = new OwnershipGuard;
        $this->reports = new ReportingService;
        $this->transactions = new TransactionService($guard);
        $this->transfers = new TransferService($guard);
        $this->refunds = new RefundService($guard);
        $this->reversals = new ReversalService($guard);
        $this->budgets = new BudgetService($guard);
        $this->obligations = new PaymentObligationService($guard);
        $this->allocations = new ObligationAllocationService($guard);
    }

    private function today(): Carbon
    {
        return Carbon::now(config('app.timezone'))->startOfDay();
    }

    // -----------------------------------------------------------------
    // Canonical Date-Range Contract (Open Decision 4)
    // -----------------------------------------------------------------

    public function test_omitting_both_dates_resolves_to_the_last_thirty_days(): void
    {
        [$start, $end] = $this->reports->resolveDateRange(null, null, 'UTC');

        $this->assertEquals(29, $start->diffInDays($end));
        $this->assertTrue($end->isToday());
    }

    public function test_supplying_both_dates_resolves_exactly_as_given(): void
    {
        [$start, $end] = $this->reports->resolveDateRange('2026-01-01', '2026-01-31', 'UTC');

        $this->assertSame('2026-01-01', $start->toDateString());
        $this->assertSame('2026-01-31', $end->toDateString());
    }

    // -----------------------------------------------------------------
    // Account Scope Resolution (Open Decision 14-A/B/C)
    // -----------------------------------------------------------------

    public function test_no_filter_resolves_to_all_asset_and_liability_accounts(): void
    {
        $user = User::factory()->create();
        $asset = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        $liability = Account::factory()->for($user)->create(['account_type' => 'LIABILITY']);

        $scope = $this->reports->resolveAccountScope($user, []);

        $this->assertCount(2, $scope);
        $this->assertTrue($scope->has($asset->id));
        $this->assertTrue($scope->has($liability->id));
    }

    public function test_explicit_filter_narrows_to_the_selected_accounts(): void
    {
        $user = User::factory()->create();
        $selected = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $scope = $this->reports->resolveAccountScope($user, [$selected->id]);

        $this->assertCount(1, $scope);
        $this->assertTrue($scope->has($selected->id));
    }

    public function test_duplicate_account_ids_collapse_to_a_set_open_decision_13(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $scope = $this->reports->resolveAccountScope($user, [$account->id, $account->id]);

        $this->assertCount(1, $scope);
    }

    public function test_a_foreign_account_id_is_rejected(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $foreign = Account::factory()->for($owner)->create(['account_type' => 'ASSET']);

        $this->expectException(ModelNotFoundException::class);

        $this->reports->resolveAccountScope($attacker, [$foreign->id]);
    }

    // -----------------------------------------------------------------
    // Historical Balance Equivalence Invariant (Open Decision 2)
    // -----------------------------------------------------------------

    public function test_historical_balance_equals_account_balance_service_for_today_asset(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $this->transactions->recordExpense($user, $account, '300.00', $this->today(), 'Groceries');
        $this->transactions->recordIncome($user, $account, '150.00', $this->today(), 'Refund from friend');

        $accountBalanceService = new AccountBalanceService;

        $this->assertSame(
            $accountBalanceService->calculate($account),
            $this->reports->historicalBalance($account, $this->today())
        );
    }

    public function test_historical_balance_equals_account_balance_service_for_today_liability(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'LIABILITY', 'opening_balance' => '500.00']);
        $this->transactions->recordExpense($user, $account, '200.00', $this->today(), 'Credit card purchase');
        $this->transactions->recordIncome($user, $account, '50.00', $this->today(), 'Payment towards debt');

        $accountBalanceService = new AccountBalanceService;

        $this->assertSame(
            $accountBalanceService->calculate($account),
            $this->reports->historicalBalance($account, $this->today())
        );
    }

    public function test_historical_balance_equals_account_balance_service_for_a_zero_balance_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);

        $accountBalanceService = new AccountBalanceService;

        $this->assertSame('0.00', $this->reports->historicalBalance($account, $this->today()));
        $this->assertSame($accountBalanceService->calculate($account), $this->reports->historicalBalance($account, $this->today()));
    }

    public function test_historical_balance_equals_account_balance_service_with_transfers_on_both_legs(): void
    {
        $user = User::factory()->create();
        $a1 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $a2 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '500.00']);
        $this->transfers->transfer($user, $a1, $a2, '200.00', $this->today(), 'Move to savings');

        $accountBalanceService = new AccountBalanceService;

        $this->assertSame($accountBalanceService->calculate($a1), $this->reports->historicalBalance($a1, $this->today()));
        $this->assertSame($accountBalanceService->calculate($a2), $this->reports->historicalBalance($a2, $this->today()));
    }

    public function test_historical_balance_equals_account_balance_service_with_adjustments(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $this->transactions->recordAdjustment($user, $account, 'OUTFLOW', '75.00', $this->today(), 'Bank fee correction', 'Fee reversal');

        $accountBalanceService = new AccountBalanceService;

        $this->assertSame($accountBalanceService->calculate($account), $this->reports->historicalBalance($account, $this->today()));
    }

    public function test_historical_balance_equals_account_balance_service_with_refunds(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $expense = $this->transactions->recordExpense($user, $account, '300.00', $this->today(), 'Shoes');
        $this->refunds->refund($user, $expense, $account, '100.00', $this->today(), 'Partial refund');

        $accountBalanceService = new AccountBalanceService;

        $this->assertSame($accountBalanceService->calculate($account), $this->reports->historicalBalance($account, $this->today()));
    }

    public function test_historical_balance_equals_account_balance_service_with_reversal_of_reversal(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $expense = $this->transactions->recordExpense($user, $account, '300.00', $this->today(), 'Shoes');
        $reversal = $this->reversals->reverse($user, $expense, $this->today(), 'Reverse the expense');
        $this->reversals->reverse($user, $reversal, $this->today(), 'Reverse the reversal');

        $accountBalanceService = new AccountBalanceService;

        $this->assertSame($accountBalanceService->calculate($account), $this->reports->historicalBalance($account, $this->today()));
    }

    public function test_historical_balance_before_the_accounts_first_transaction_equals_opening_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $this->transactions->recordExpense($user, $account, '300.00', $this->today(), 'Shoes');

        $this->assertSame('1000.00', $this->reports->historicalBalance($account, $this->today()->copy()->subDays(10)));
    }

    // -----------------------------------------------------------------
    // Cash Flow Report (section 4.1) and Reconciliation Invariant (4.7)
    // -----------------------------------------------------------------

    public function test_cash_flow_income_and_net_expenses_for_a_single_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $today = $this->today();
        $this->transactions->recordIncome($user, $account, '1000.00', $today, 'Salary');
        $this->transactions->recordExpense($user, $account, '300.00', $today, 'Groceries');

        $accounts = collect([$account->id => $account]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);

        $this->assertSame('1000.00', $cashFlow['income']);
        $this->assertSame('300.00', $cashFlow['expenses']);
        $this->assertTrue($cashFlow['reconciled']);
        $this->assertSame('0.00', $cashFlow['residual']);
    }

    public function test_cash_flow_refund_reduces_net_expenses(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $today = $this->today();
        $expense = $this->transactions->recordExpense($user, $account, '500.00', $today, 'Shoes');
        $this->refunds->refund($user, $expense, $account, '200.00', $today, 'Refund');

        $accounts = collect([$account->id => $account]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);

        $this->assertSame('300.00', $cashFlow['expenses']);
        $this->assertTrue($cashFlow['reconciled']);
    }

    public function test_cash_flow_reversal_of_reversal_nets_back_toward_the_original_expense(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $today = $this->today();
        $expense = $this->transactions->recordExpense($user, $account, '500.00', $today, 'Shoes');
        $reversal = $this->reversals->reverse($user, $expense, $today, 'Reverse');
        $this->reversals->reverse($user, $reversal, $today, 'Reverse the reversal');

        $accounts = collect([$account->id => $account]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);

        $this->assertSame('500.00', $cashFlow['expenses']);
        $this->assertTrue($cashFlow['reconciled']);
    }

    public function test_cash_flow_ordinary_transfer_both_legs_selected_nets_to_zero(): void
    {
        $user = User::factory()->create();
        $a1 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $a2 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '500.00']);
        $today = $this->today();
        $this->transfers->transfer($user, $a1, $a2, '200.00', $today, 'Move funds');

        $accounts = collect([$a1->id => $a1, $a2->id => $a2]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);

        $this->assertSame('0.00', $cashFlow['transfers']);
        $this->assertTrue($cashFlow['reconciled']);
    }

    public function test_cash_flow_transfer_source_only_selected_is_an_outbound_transfer(): void
    {
        $user = User::factory()->create();
        $a1 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $a2 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '500.00']);
        $today = $this->today();
        $this->transfers->transfer($user, $a1, $a2, '200.00', $today, 'Move funds');

        $accounts = collect([$a1->id => $a1]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);

        $this->assertSame('200.00', $cashFlow['outbound_transfer']);
        $this->assertSame('-200.00', $cashFlow['transfers']);
        $this->assertSame('0.00', $cashFlow['income']);
        $this->assertSame('0.00', $cashFlow['expenses']);
        $this->assertTrue($cashFlow['reconciled']);
    }

    public function test_cash_flow_transfer_destination_only_selected_is_an_inbound_transfer(): void
    {
        $user = User::factory()->create();
        $a1 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $a2 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '500.00']);
        $today = $this->today();
        $this->transfers->transfer($user, $a1, $a2, '200.00', $today, 'Move funds');

        $accounts = collect([$a2->id => $a2]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);

        $this->assertSame('200.00', $cashFlow['inbound_transfer']);
        $this->assertSame('200.00', $cashFlow['transfers']);
        $this->assertSame('0.00', $cashFlow['income']);
        $this->assertTrue($cashFlow['reconciled']);
    }

    public function test_cash_flow_investment_classified_transfer_is_excluded_from_ordinary_transfers(): void
    {
        $user = User::factory()->create();
        $a1 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $a2 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $investmentCategory = Category::factory()->for($user)->create(['category_type' => 'INVESTMENT']);
        $today = $this->today();
        $this->transfers->transfer($user, $a1, $a2, '400.00', $today, 'Buy mutual fund', $investmentCategory);

        $accounts = collect([$a1->id => $a1, $a2->id => $a2]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);

        $this->assertSame('0.00', $cashFlow['transfers']);
        $this->assertSame('0.00', $cashFlow['investments']);
        $this->assertTrue($cashFlow['reconciled']);
    }

    public function test_cash_flow_adjustment_is_an_explicit_named_term(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $today = $this->today();
        $this->transactions->recordAdjustment($user, $account, 'OUTFLOW', '50.00', $today, 'Bank fee', 'correction');

        $accounts = collect([$account->id => $account]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);

        $this->assertSame('-50.00', $cashFlow['adjustments']);
        $this->assertSame('0.00', $cashFlow['expenses']);
        $this->assertTrue($cashFlow['reconciled']);
    }

    public function test_cash_flow_reconciles_with_mixed_asset_and_liability_accounts(): void
    {
        $user = User::factory()->create();
        $a1 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $l1 = Account::factory()->for($user)->create(['account_type' => 'LIABILITY', 'opening_balance' => '200.00']);
        $today = $this->today();
        $this->transactions->recordExpense($user, $a1, '300.00', $today, 'Groceries');
        $this->transactions->recordExpense($user, $l1, '150.00', $today, 'Credit card purchase');

        $accounts = collect([$a1->id => $a1, $l1->id => $l1]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);

        $this->assertTrue($cashFlow['reconciled']);
        $this->assertSame('0.00', $cashFlow['residual']);
    }

    public function test_cash_flow_full_worked_example_reconciles_with_zero_residual(): void
    {
        $user = User::factory()->create();
        $a1 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '5000.00']);
        $a2 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $investmentCategory = Category::factory()->for($user)->create(['category_type' => 'INVESTMENT']);
        $today = $this->today();

        $this->transactions->recordIncome($user, $a1, '2000.00', $today, 'Salary');
        $expense = $this->transactions->recordExpense($user, $a1, '500.00', $today, 'Shopping');
        $this->refunds->refund($user, $expense, $a1, '100.00', $today, 'Refund');
        $reversalTarget = $this->transactions->recordExpense($user, $a1, '80.00', $today, 'Mistake charge');
        $this->reversals->reverse($user, $reversalTarget, $today, 'Reverse mistaken charge');
        $this->transfers->transfer($user, $a1, $a2, '300.00', $today, 'Move to a2');
        $this->transfers->transfer($user, $a1, $a2, '250.00', $today, 'Buy investment', $investmentCategory);
        $this->transactions->recordAdjustment($user, $a1, 'OUTFLOW', '20.00', $today, 'Bank fee', 'correction');

        $accounts = collect([$a1->id => $a1, $a2->id => $a2]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);

        $this->assertTrue($cashFlow['reconciled']);
        $this->assertSame('0.00', $cashFlow['residual']);
    }

    // -----------------------------------------------------------------
    // Category Spending Report (section 4.1a, Open Decision 10)
    // -----------------------------------------------------------------

    public function test_category_spending_sum_equals_aggregate_cash_flow_net_expenses(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $groceries = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $fuel = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $today = $this->today();

        $this->transactions->recordExpense($user, $account, '300.00', $today, 'Groceries', $groceries);
        $this->transactions->recordExpense($user, $account, '150.00', $today, 'Fuel', $fuel);
        $this->transactions->recordExpense($user, $account, '75.00', $today, 'No category');

        $accounts = collect([$account->id => $account]);
        $cashFlow = $this->reports->cashFlow($user, $accounts, $today, $today);
        $categories = $this->reports->categorySpending($user, $accounts, $today, $today);

        $sum = $categories->reduce(fn ($carry, $row) => bcadd($carry, $row['net_expenses'], 2), '0.00');

        $this->assertSame($cashFlow['expenses'], $sum);
    }

    public function test_category_spending_groups_uncategorized_transactions_without_dropping_them(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $today = $this->today();
        $this->transactions->recordExpense($user, $account, '75.00', $today, 'No category');

        $accounts = collect([$account->id => $account]);
        $categories = $this->reports->categorySpending($user, $accounts, $today, $today);

        $this->assertCount(1, $categories);
        $this->assertNull($categories->first()['category']);
        $this->assertSame('75.00', $categories->first()['net_expenses']);
    }

    // -----------------------------------------------------------------
    // Budget Report (section 4.2, Open Decisions 7, 11.A/11.B)
    // -----------------------------------------------------------------

    public function test_budget_report_reuses_calculate_utilization_without_divergence(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '5000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $today = $this->today();
        $budget = $this->budgets->createBudget($user, $category, $today->copy()->startOfMonth(), $today->copy()->endOfMonth(), '1000.00');
        $this->transactions->recordExpense($user, $account, '400.00', $today, 'Groceries', $category);

        $rows = $this->reports->budgetReport($user, $this->budgets, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $this->assertSame($this->budgets->calculateUtilization($budget), $rows->first()['actual']);
        $this->assertSame('600.00', $rows->first()['remaining']);
    }

    public function test_budget_report_uses_overlap_membership_semantics(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $this->budgets->createBudget($user, $category, Carbon::parse('2026-08-15'), Carbon::parse('2026-09-14'), '1000.00');

        $rows = $this->reports->budgetReport($user, $this->budgets, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertCount(1, $rows);
    }

    public function test_budget_report_excludes_a_budget_moved_entirely_outside_the_range(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $this->budgets->createBudget($user, $category, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), '1000.00');

        $rows = $this->reports->budgetReport($user, $this->budgets, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertCount(0, $rows);
    }

    // -----------------------------------------------------------------
    // Obligations Report (section 4.3, Open Decisions 3, 7, 8)
    // -----------------------------------------------------------------

    public function test_historical_status_reconstruction_worked_example(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '5000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);

        $obligation = $this->obligations->createOneTime(
            $user, 'obligation-1', $category, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), Carbon::parse('2026-08-05'), '1000.00'
        );

        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
        $expense = $this->transactions->recordExpense($user, $account, '1000.00', Carbon::parse('2026-08-10'), 'Payment');
        $this->allocations->allocate($user, $obligation, $expense, '1000.00');
        Carbon::setTestNow();

        $rows = $this->reports->obligationsReport($user, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame('PENDING', $rows->first()['historical_status']);
        $this->assertTrue($rows->first()['is_overdue']);
    }

    public function test_overdue_is_evaluated_as_of_the_report_periods_end_date_not_today(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '5000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);

        $obligation = $this->obligations->createOneTime(
            $user, 'obligation-2', $category, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), Carbon::parse('2026-08-05'), '1000.00'
        );

        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
        $expense = $this->transactions->recordExpense($user, $account, '1000.00', Carbon::parse('2026-08-10'), 'Payment');
        $this->allocations->allocate($user, $obligation, $expense, '1000.00');
        Carbon::setTestNow();

        $rows = $this->reports->obligationsReport($user, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertSame('PAID', $rows->first()['historical_status']);
        $this->assertFalse($rows->first()['is_overdue']);
    }

    public function test_audit_completeness_captures_partially_paid_then_paid_transitions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '5000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);

        $obligation = $this->obligations->createOneTime(
            $user, 'obligation-3', $category, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), Carbon::parse('2026-08-20'), '1000.00'
        );

        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));
        $first = $this->transactions->recordExpense($user, $account, '400.00', Carbon::parse('2026-08-05'), 'First payment');
        $this->allocations->allocate($user, $obligation, $first, '400.00');
        Carbon::setTestNow();

        $boundaryAfterPartial = Carbon::parse('2026-08-05 10:00:00');
        $this->assertSame(
            'PARTIALLY_PAID',
            $this->reports->historicalObligationStatus($obligation->fresh(), $boundaryAfterPartial)
        );

        Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00'));
        $second = $this->transactions->recordExpense($user, $account, '600.00', Carbon::parse('2026-08-15'), 'Final payment');
        $this->allocations->allocate($user, $obligation, $second, '600.00');
        Carbon::setTestNow();

        $boundaryAfterFull = Carbon::parse('2026-08-15 10:00:00');
        $this->assertSame(
            'PAID',
            $this->reports->historicalObligationStatus($obligation->fresh(), $boundaryAfterFull)
        );

        $boundaryBeforeAnyPayment = Carbon::parse('2026-08-01 00:00:00');
        $this->assertSame(
            'PENDING',
            $this->reports->historicalObligationStatus($obligation->fresh(), $boundaryBeforeAnyPayment)
        );
    }

    public function test_audit_completeness_captures_allocation_removal_reverting_status(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '5000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);

        $obligation = $this->obligations->createOneTime(
            $user, 'obligation-4', $category, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), Carbon::parse('2026-08-20'), '1000.00'
        );

        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));
        $expense = $this->transactions->recordExpense($user, $account, '1000.00', Carbon::parse('2026-08-05'), 'Full payment');
        $allocation = $this->allocations->allocate($user, $obligation, $expense, '1000.00');
        Carbon::setTestNow();

        Carbon::setTestNow(Carbon::parse('2026-08-06 09:00:00'));
        $this->allocations->removeAllocation($user, $allocation);
        Carbon::setTestNow();

        $boundary = Carbon::parse('2026-08-06 10:00:00');
        $this->assertSame('PENDING', $this->reports->historicalObligationStatus($obligation->fresh(), $boundary));
    }

    public function test_audit_completeness_captures_skipped_and_cancelled_transitions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);

        $skipped = $this->obligations->createOneTime(
            $user, 'obligation-5', $category, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), Carbon::parse('2026-08-20'), '1000.00'
        );
        $cancelled = $this->obligations->createOneTime(
            $user, 'obligation-6', $category, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), Carbon::parse('2026-08-20'), '500.00'
        );

        Carbon::setTestNow(Carbon::parse('2026-08-02 09:00:00'));
        $this->allocations->skip($user, $skipped);
        $this->allocations->cancel($user, $cancelled);
        Carbon::setTestNow();

        $boundary = Carbon::parse('2026-08-02 10:00:00');
        $this->assertSame('SKIPPED', $this->reports->historicalObligationStatus($skipped->fresh(), $boundary));
        $this->assertSame('CANCELLED', $this->reports->historicalObligationStatus($cancelled->fresh(), $boundary));
    }

    // -----------------------------------------------------------------
    // Trends Report / Savings Formula (section 4.4, Open Decision 9)
    // -----------------------------------------------------------------

    public function test_savings_worked_example_one_investment_classified_transfer(): void
    {
        $user = User::factory()->create();
        $a1 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $a2 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $investmentCategory = Category::factory()->for($user)->create(['category_type' => 'INVESTMENT']);
        $today = $this->today();

        $this->transactions->recordIncome($user, $a1, '10000.00', $today, 'Salary');
        $this->transactions->recordExpense($user, $a1, '4000.00', $today, 'Bills');
        $this->transfers->transfer($user, $a1, $a2, '2000.00', $today, 'Buy fund', $investmentCategory);

        // a2 (the investment destination) is intentionally left out of the
        // selected scope -- an Investment-classified Transfer composes with
        // Open Decision 14-G exactly like an ordinary Transfer (section
        // 4.1's "Composition with Open Decision 5"), so with both legs
        // selected it nets to zero (Case 1) for the *combined* scope. This
        // reproduces the Decision Package's worked example, where the full
        // ₹2,000 leaves the tracked/selected scope (Case 2 -- Outbound).
        $accounts = collect([$a1->id => $a1]);
        $months = $this->reports->trends($user, $accounts, $today, $today);

        $this->assertSame('4000.00', $months->first()['savings']);
    }

    public function test_savings_worked_example_ordinary_transfer_is_not_deducted(): void
    {
        $user = User::factory()->create();
        $a1 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $a2 = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $today = $this->today();

        $this->transactions->recordIncome($user, $a1, '10000.00', $today, 'Salary');
        $this->transactions->recordExpense($user, $a1, '4000.00', $today, 'Bills');
        $this->transfers->transfer($user, $a1, $a2, '2000.00', $today, 'Move to savings account');

        $accounts = collect([$a1->id => $a1, $a2->id => $a2]);
        $months = $this->reports->trends($user, $accounts, $today, $today);

        $this->assertSame('6000.00', $months->first()['savings']);
    }

    public function test_trends_produces_one_row_per_calendar_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);

        $accounts = collect([$account->id => $account]);
        $months = $this->reports->trends($user, $accounts, Carbon::parse('2026-06-15'), Carbon::parse('2026-08-10'));

        $this->assertSame(['2026-06', '2026-07', '2026-08'], $months->pluck('month')->all());
    }

    // -----------------------------------------------------------------
    // Month Review (section 4.6, Open Decision 1) -- read-only composition
    // -----------------------------------------------------------------

    public function test_month_review_composes_cash_flow_budget_obligations_and_category_spending(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $today = $this->today();
        $this->budgets->createBudget($user, $category, $today->copy()->startOfMonth(), $today->copy()->endOfMonth(), '500.00');
        $this->transactions->recordExpense($user, $account, '100.00', $today, 'Groceries', $category);

        $accounts = collect([$account->id => $account]);
        $review = $this->reports->monthReview($user, $this->budgets, $accounts, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $this->assertArrayHasKey('cash_flow', $review);
        $this->assertArrayHasKey('budgets', $review);
        $this->assertArrayHasKey('obligations', $review);
        $this->assertArrayHasKey('category_spending', $review);
        $this->assertSame(0, PaymentObligation::query()->count());
    }
}
