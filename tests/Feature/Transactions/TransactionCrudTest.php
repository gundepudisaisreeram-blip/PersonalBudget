<?php

namespace Tests\Feature\Transactions;

use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\RefundService;
use App\Domain\Services\ReversalService;
use App\Domain\Services\TransactionService;
use App\Domain\Services\TransferService;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCrudTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactions;

    private TransferService $transfers;

    private RefundService $refunds;

    private ReversalService $reversals;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = new OwnershipGuard;
        $this->transactions = new TransactionService($guard);
        $this->transfers = new TransferService($guard);
        $this->refunds = new RefundService($guard);
        $this->reversals = new ReversalService($guard);
    }

    // -----------------------------------------------------------------
    // Ledger pattern per type, created via HTTP
    // -----------------------------------------------------------------

    public function test_http_expense_creates_exactly_one_outflow_entry(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('transactions.expense.store'), [
            'account_id' => $account->id,
            'amount' => '150.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Groceries',
        ]);

        $transaction = Transaction::where('description', 'Groceries')->firstOrFail();
        $response->assertRedirect(route('transactions.show', $transaction));
        $this->assertSame('EXPENSE', $transaction->transaction_type);
        $this->assertSame('POSTED', $transaction->status);
        $this->assertCount(1, $transaction->ledgerEntries);
        $this->assertSame('OUTFLOW', $transaction->ledgerEntries->first()->direction);
        $this->assertSame('150.00', $transaction->ledgerEntries->first()->amount);
    }

    public function test_http_income_creates_exactly_one_inflow_entry(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('transactions.income.store'), [
            'account_id' => $account->id,
            'amount' => '5000.00',
            'transaction_date' => '2026-01-01',
            'description' => 'Salary',
        ]);

        $transaction = Transaction::where('description', 'Salary')->firstOrFail();
        $this->assertSame('INCOME', $transaction->transaction_type);
        $this->assertCount(1, $transaction->ledgerEntries);
        $this->assertSame('INFLOW', $transaction->ledgerEntries->first()->direction);
    }

    public function test_http_transfer_creates_two_balanced_entries(): void
    {
        $user = User::factory()->create();
        $from = Account::factory()->for($user)->create();
        $to = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('transactions.transfer.store'), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => '200.00',
            'transaction_date' => '2026-01-10',
            'description' => 'Move funds',
        ]);

        $transaction = Transaction::where('description', 'Move funds')->firstOrFail();
        $this->assertSame('TRANSFER', $transaction->transaction_type);
        $this->assertCount(2, $transaction->ledgerEntries);

        $outflow = $transaction->ledgerEntries->firstWhere('account_id', $from->id);
        $inflow = $transaction->ledgerEntries->firstWhere('account_id', $to->id);
        $this->assertSame('OUTFLOW', $outflow->direction);
        $this->assertSame('INFLOW', $inflow->direction);
        $this->assertSame($outflow->amount, $inflow->amount);
    }

    public function test_http_refund_creates_one_inflow_linked_to_expense_parent(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '300.00', '2026-01-05', 'Purchase');

        $this->actingAs($user)->post(route('transactions.refund.store'), [
            'parent_transaction_id' => $expense->id,
            'account_id' => $account->id,
            'amount' => '300.00',
            'transaction_date' => '2026-01-10',
            'description' => 'Refunded purchase',
        ]);

        $refund = Transaction::where('description', 'Refunded purchase')->firstOrFail();
        $this->assertSame('REFUND', $refund->transaction_type);
        $this->assertSame($expense->id, $refund->parent_transaction_id);
        $this->assertCount(1, $refund->ledgerEntries);
        $this->assertSame('INFLOW', $refund->ledgerEntries->first()->direction);
    }

    public function test_http_reversal_of_a_single_entry_transaction_inverts_direction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '400.00', '2026-01-05', 'Failed payment');

        $this->actingAs($user)->post(route('transactions.reversal.store'), [
            'parent_transaction_id' => $expense->id,
            'transaction_date' => '2026-01-06',
            'description' => 'Bank reversed the transaction',
        ]);

        $reversal = Transaction::where('description', 'Bank reversed the transaction')->firstOrFail();
        $this->assertSame('REVERSAL', $reversal->transaction_type);
        $this->assertSame($expense->id, $reversal->parent_transaction_id);
        $this->assertCount(1, $reversal->ledgerEntries);
        $this->assertSame('INFLOW', $reversal->ledgerEntries->first()->direction);
    }

    public function test_http_reversal_of_a_transfer_produces_two_mirrored_entries(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create();
        $accountB = Account::factory()->for($user)->create();
        $transfer = $this->transfers->transfer($user, $accountA, $accountB, '250.00', '2026-01-05', 'Move funds');

        $this->actingAs($user)->post(route('transactions.reversal.store'), [
            'parent_transaction_id' => $transfer->id,
            'transaction_date' => '2026-01-06',
            'description' => 'Undo transfer',
        ]);

        $reversal = Transaction::where('description', 'Undo transfer')->firstOrFail();
        $this->assertCount(2, $reversal->ledgerEntries);

        $reversedFromA = $reversal->ledgerEntries->firstWhere('account_id', $accountA->id);
        $reversedFromB = $reversal->ledgerEntries->firstWhere('account_id', $accountB->id);
        $this->assertSame('INFLOW', $reversedFromA->direction);
        $this->assertSame('OUTFLOW', $reversedFromB->direction);
    }

    public function test_http_adjustment_creates_one_entry_and_writes_an_audit_log(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)->post(route('transactions.adjustment.store'), [
            'account_id' => $account->id,
            'direction' => 'OUTFLOW',
            'amount' => '25.00',
            'transaction_date' => '2026-01-20',
            'description' => 'Bank fee correction',
            'reason' => 'Verified against February statement closing balance',
        ]);

        $adjustment = Transaction::where('description', 'Bank fee correction')->firstOrFail();
        $this->assertSame('ADJUSTMENT', $adjustment->transaction_type);
        $this->assertSame('ADJUSTMENT', $adjustment->source);
        $this->assertCount(1, $adjustment->ledgerEntries);

        $auditLog = AuditLog::where('entity_type', Transaction::class)
            ->where('entity_id', $adjustment->id)
            ->first();
        $this->assertNotNull($auditLog);
        $this->assertSame('ADJUSTMENT_CREATED', $auditLog->action);
        $this->assertSame('Verified against February statement closing balance', $auditLog->metadata['reason']);
    }

    // -----------------------------------------------------------------
    // Frozen decisions: unlimited refunds/reversals
    // -----------------------------------------------------------------

    public function test_multiple_refunds_against_the_same_expense_are_permitted_with_no_cumulative_ceiling(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '10000.00', '2026-01-05', 'Big purchase');

        $first = $this->actingAs($user)->post(route('transactions.refund.store'), [
            'parent_transaction_id' => $expense->id,
            'account_id' => $account->id,
            'amount' => '7000.00',
            'transaction_date' => '2026-01-10',
            'description' => 'Partial refund 1',
        ]);

        $second = $this->actingAs($user)->post(route('transactions.refund.store'), [
            'parent_transaction_id' => $expense->id,
            'account_id' => $account->id,
            'amount' => '5000.00',
            'transaction_date' => '2026-01-11',
            'description' => 'Partial refund 2',
        ]);

        $first->assertSessionDoesntHaveErrors();
        $second->assertSessionDoesntHaveErrors();
        $this->assertSame(2, Transaction::where('parent_transaction_id', $expense->id)->where('transaction_type', 'REFUND')->count());
    }

    public function test_a_transaction_can_be_reversed_more_than_once(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '400.00', '2026-01-05', 'Failed payment');

        $first = $this->actingAs($user)->post(route('transactions.reversal.store'), [
            'parent_transaction_id' => $expense->id,
            'transaction_date' => '2026-01-06',
            'description' => 'First reversal',
        ]);

        $second = $this->actingAs($user)->post(route('transactions.reversal.store'), [
            'parent_transaction_id' => $expense->id,
            'transaction_date' => '2026-01-07',
            'description' => 'Second reversal',
        ]);

        $first->assertSessionDoesntHaveErrors();
        $second->assertSessionDoesntHaveErrors();
        $this->assertSame(2, Transaction::where('parent_transaction_id', $expense->id)->where('transaction_type', 'REVERSAL')->count());
    }

    public function test_a_reversal_of_a_reversal_is_permitted(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '400.00', '2026-01-05', 'Failed payment');
        $reversal = $this->reversals->reverse($user, $expense, '2026-01-06', 'First reversal');

        $response = $this->actingAs($user)->post(route('transactions.reversal.store'), [
            'parent_transaction_id' => $reversal->id,
            'transaction_date' => '2026-01-07',
            'description' => 'Reversal of the reversal',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $reversalOfReversal = Transaction::where('description', 'Reversal of the reversal')->firstOrFail();
        $this->assertSame($reversal->id, $reversalOfReversal->parent_transaction_id);
        $this->assertSame('OUTFLOW', $reversalOfReversal->ledgerEntries->first()->direction);
    }

    // -----------------------------------------------------------------
    // Combined filters
    // -----------------------------------------------------------------

    public function test_combined_filters_apply_simultaneously(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create();
        $accountB = Account::factory()->for($user)->create();
        $categoryA = Category::factory()->for($user)->create();
        $categoryB = Category::factory()->for($user)->create();

        $match = $this->transactions->recordExpense($user, $accountA, '100.00', '2026-01-05', 'Match', $categoryA);
        $this->transactions->recordExpense($user, $accountA, '200.00', '2026-01-05', 'Different amount', $categoryA);
        $this->transactions->recordExpense($user, $accountA, '100.00', '2026-01-06', 'Different date', $categoryA);
        $this->transactions->recordExpense($user, $accountB, '100.00', '2026-01-05', 'Different account', $categoryA);
        $this->transactions->recordIncome($user, $accountA, '100.00', '2026-01-05', 'Different type', $categoryA);
        $this->transactions->recordExpense($user, $accountA, '100.00', '2026-01-05', 'Different category', $categoryB);

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'date_from' => '2026-01-05',
            'date_to' => '2026-01-05',
            'account_id' => $accountA->id,
            'category_id' => $categoryA->id,
            'transaction_type' => 'EXPENSE',
            'amount' => '100.00',
        ]));

        $response->assertOk();
        $response->assertSee('Match');
        $response->assertDontSee('Different amount');
        $response->assertDontSee('Different date');
        $response->assertDontSee('Different account');
        $response->assertDontSee('Different type');
        $response->assertDontSee('Different category');
    }

    public function test_filters_do_not_leak_between_separate_requests(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $this->transactions->recordExpense($user, $account, '100.00', '2026-01-05', 'Expense one');
        $this->transactions->recordIncome($user, $account, '100.00', '2026-01-05', 'Income one');

        $expenseOnly = $this->actingAs($user)->get(route('transactions.index', ['transaction_type' => 'EXPENSE']));
        $expenseOnly->assertSee('Expense one');
        $expenseOnly->assertDontSee('Income one');

        $incomeOnly = $this->actingAs($user)->get(route('transactions.index', ['transaction_type' => 'INCOME']));
        $incomeOnly->assertSee('Income one');
        $incomeOnly->assertDontSee('Expense one');

        $unfiltered = $this->actingAs($user)->get(route('transactions.index'));
        $unfiltered->assertSee('Expense one');
        $unfiltered->assertSee('Income one');
    }

    public function test_another_users_matching_transactions_never_appear_in_filtered_results(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $userAccount = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($other)->create();

        $this->transactions->recordExpense($user, $userAccount, '100.00', '2026-01-05', 'Mine');
        $this->transactions->recordExpense($other, $otherAccount, '100.00', '2026-01-05', 'Not mine');

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'date_from' => '2026-01-05',
            'date_to' => '2026-01-05',
            'transaction_type' => 'EXPENSE',
            'amount' => '100.00',
        ]));

        $response->assertSee('Mine');
        $response->assertDontSee('Not mine');
    }

    public function test_pagination_preserves_active_filters(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        for ($i = 1; $i <= 25; $i++) {
            $this->transactions->recordExpense($user, $account, '10.00', '2026-01-05', "Expense {$i}");
        }
        $this->transactions->recordIncome($user, $account, '10.00', '2026-01-05', 'Income filler');

        $pageTwo = $this->actingAs($user)->get(route('transactions.index', [
            'transaction_type' => 'EXPENSE',
            'page' => 2,
        ]));

        $pageTwo->assertOk();
        $pageTwo->assertDontSee('Income filler');
    }
}
