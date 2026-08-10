<?php

namespace Tests\Feature\Budgets;

use App\Domain\Services\BudgetService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\RefundService;
use App\Domain\Services\ReversalService;
use App\Domain\Services\TransactionService;
use App\Domain\Services\TransferService;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the frozen formula: Net Budget Utilization = SUM(OUTFLOW) -
 * SUM(INFLOW), scoped to ledger entries whose transaction matches the
 * budget's category and falls within its period -- including the frozen
 * Reversal-of-Reversal worked example and the BR-059 period-placement rule.
 */
class BudgetUtilizationTest extends TestCase
{
    use RefreshDatabase;

    private BudgetService $budgets;

    private TransactionService $transactions;

    private TransferService $transfers;

    private RefundService $refunds;

    private ReversalService $reversals;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = new OwnershipGuard;
        $this->budgets = new BudgetService($guard);
        $this->transactions = new TransactionService($guard);
        $this->transfers = new TransferService($guard);
        $this->refunds = new RefundService($guard);
        $this->reversals = new ReversalService($guard);
    }

    private function makeBudget(User $user, Category $category, string $start = '2026-01-01', string $end = '2026-01-31'): Budget
    {
        return $this->budgets->createBudget($user, $category, $start, $end, '100000.00');
    }

    public function test_a_single_expense_increases_utilization(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create();
        $budget = $this->makeBudget($user, $category);

        $this->transactions->recordExpense($user, $account, '1000.00', '2026-01-05', 'Groceries', $category);

        $this->assertSame('1000.00', $this->budgets->calculateUtilization($budget));
    }

    public function test_a_refund_reduces_utilization(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create();
        $budget = $this->makeBudget($user, $category);

        $expense = $this->transactions->recordExpense($user, $account, '1000.00', '2026-01-05', 'Purchase', $category);
        $this->refunds->refund($user, $expense, $account, '300.00', '2026-01-10', 'Partial refund');

        $this->assertSame('700.00', $this->budgets->calculateUtilization($budget));
    }

    public function test_a_reversal_of_an_expense_reduces_utilization(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create();
        $budget = $this->makeBudget($user, $category);

        $expense = $this->transactions->recordExpense($user, $account, '1000.00', '2026-01-05', 'Purchase', $category);
        $this->reversals->reverse($user, $expense, '2026-01-06', 'Bank reversed it');

        $this->assertSame('0.00', $this->budgets->calculateUtilization($budget));
    }

    public function test_the_frozen_reversal_of_reversal_worked_example(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create();
        $budget = $this->makeBudget($user, $category);

        $expense = $this->transactions->recordExpense($user, $account, '10000.00', '2026-01-05', 'Expense', $category);
        $this->assertSame('10000.00', $this->budgets->calculateUtilization($budget));

        $reversal1 = $this->reversals->reverse($user, $expense, '2026-01-06', 'Reversal #1');
        $this->assertSame('0.00', $this->budgets->calculateUtilization($budget));

        $this->reversals->reverse($user, $reversal1, '2026-01-07', 'Reversal #2');
        $this->assertSame('10000.00', $this->budgets->calculateUtilization($budget));
    }

    public function test_a_transfer_never_affects_utilization_even_when_categorized(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create();
        $accountB = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create();
        $budget = $this->makeBudget($user, $category);

        $this->transfers->transfer($user, $accountA, $accountB, '5000.00', '2026-01-05', 'Move funds', $category);

        $this->assertSame('0.00', $this->budgets->calculateUtilization($budget));
    }

    public function test_an_adjustment_never_affects_utilization_even_when_categorized(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create();
        $budget = $this->makeBudget($user, $category);

        $this->transactions->recordAdjustment($user, $account, 'OUTFLOW', '500.00', '2026-01-05', 'Correction', 'Bank fee correction', $category);

        $this->assertSame('0.00', $this->budgets->calculateUtilization($budget));
    }

    public function test_a_refund_issued_in_a_later_period_affects_that_periods_budget_not_the_original(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create();
        $januaryBudget = $this->makeBudget($user, $category, '2026-01-01', '2026-01-31');
        $februaryBudget = $this->budgets->createBudget($user, $category, '2026-02-01', '2026-02-28', '100000.00');

        $expense = $this->transactions->recordExpense($user, $account, '1000.00', '2026-01-05', 'Purchase', $category);
        $this->refunds->refund($user, $expense, $account, '1000.00', '2026-02-10', 'Late refund');

        $this->assertSame('1000.00', $this->budgets->calculateUtilization($januaryBudget));
        $this->assertSame('-1000.00', $this->budgets->calculateUtilization($februaryBudget));
    }

    public function test_utilization_is_scoped_to_the_budgets_own_category_only(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $categoryA = Category::factory()->for($user)->create();
        $categoryB = Category::factory()->for($user)->create();
        $budget = $this->makeBudget($user, $categoryA);

        $this->transactions->recordExpense($user, $account, '1000.00', '2026-01-05', 'Different category', $categoryB);

        $this->assertSame('0.00', $this->budgets->calculateUtilization($budget));
    }
}
