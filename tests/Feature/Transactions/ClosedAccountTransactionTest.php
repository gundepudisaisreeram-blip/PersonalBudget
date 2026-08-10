<?php

namespace Tests\Feature\Transactions;

use App\Domain\Exceptions\InvalidTransactionException;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\RefundService;
use App\Domain\Services\ReversalService;
use App\Domain\Services\TransactionService;
use App\Domain\Services\TransferService;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves BR-008 ("closed accounts cannot accept current/future-dated
 * transactions") is enforced uniformly across all six transaction types,
 * with no exception for Refund/Reversal, at both the domain-service layer
 * and the full HTTP layer -- per the finalized Phase 3 Decision Package.
 */
class ClosedAccountTransactionTest extends TestCase
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
    // Domain-level (direct service call)
    // -----------------------------------------------------------------

    public function test_expense_against_a_closed_account_is_rejected_at_the_service_layer(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->closed()->create();

        $this->expectException(InvalidTransactionException::class);

        $this->transactions->recordExpense($user, $account, '100.00', '2026-01-05', 'Groceries');
    }

    public function test_income_against_a_closed_account_is_rejected_at_the_service_layer(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->closed()->create();

        $this->expectException(InvalidTransactionException::class);

        $this->transactions->recordIncome($user, $account, '100.00', '2026-01-05', 'Salary');
    }

    public function test_adjustment_against_a_closed_account_is_rejected_at_the_service_layer(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->closed()->create();

        $this->expectException(InvalidTransactionException::class);

        $this->transactions->recordAdjustment($user, $account, 'OUTFLOW', '25.00', '2026-01-05', 'Correction', 'Bank fee correction');
    }

    public function test_transfer_from_a_closed_account_is_rejected_at_the_service_layer(): void
    {
        $user = User::factory()->create();
        $closed = Account::factory()->for($user)->closed()->create();
        $active = Account::factory()->for($user)->create();

        $this->expectException(InvalidTransactionException::class);

        $this->transfers->transfer($user, $closed, $active, '100.00', '2026-01-05', 'Move funds');
    }

    public function test_transfer_to_a_closed_account_is_rejected_at_the_service_layer(): void
    {
        $user = User::factory()->create();
        $active = Account::factory()->for($user)->create();
        $closed = Account::factory()->for($user)->closed()->create();

        $this->expectException(InvalidTransactionException::class);

        $this->transfers->transfer($user, $active, $closed, '100.00', '2026-01-05', 'Move funds');
    }

    public function test_transfer_between_two_closed_accounts_is_rejected_at_the_service_layer(): void
    {
        $user = User::factory()->create();
        $closedA = Account::factory()->for($user)->closed()->create();
        $closedB = Account::factory()->for($user)->closed()->create();

        $this->expectException(InvalidTransactionException::class);

        $this->transfers->transfer($user, $closedA, $closedB, '100.00', '2026-01-05', 'Move funds');
    }

    public function test_refund_against_a_closed_destination_account_is_rejected_at_the_service_layer(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '300.00', '2026-01-05', 'Purchase');
        $closed = Account::factory()->for($user)->closed()->create();

        $this->expectException(InvalidTransactionException::class);

        $this->refunds->refund($user, $expense, $closed, '300.00', '2026-01-10', 'Refunded purchase');
    }

    public function test_reversal_whose_parent_account_is_now_closed_is_rejected_at_the_service_layer(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '400.00', '2026-01-05', 'Failed payment');

        $account->update(['status' => 'CLOSED']);

        $this->expectException(InvalidTransactionException::class);

        $this->reversals->reverse($user, $expense, '2026-01-06', 'Bank reversed the transaction');
    }

    public function test_reversal_of_a_transfer_where_only_one_inherited_account_is_closed_is_rejected(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create();
        $accountB = Account::factory()->for($user)->create();
        $transfer = $this->transfers->transfer($user, $accountA, $accountB, '250.00', '2026-01-05', 'Move funds');

        $accountB->update(['status' => 'CLOSED']);

        $this->expectException(InvalidTransactionException::class);

        $this->reversals->reverse($user, $transfer, '2026-01-06', 'Undo transfer');
    }

    // -----------------------------------------------------------------
    // HTTP-level: redirect back, withInput(), visible error, no DB write
    // -----------------------------------------------------------------

    private function assertNoFinancialStatePersisted(int $transactionCountBefore, int $ledgerCountBefore): void
    {
        $this->assertSame($transactionCountBefore, Transaction::count());
        $this->assertSame($ledgerCountBefore, LedgerEntry::count());
    }

    public function test_http_expense_against_a_closed_account_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->closed()->create();
        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.expense.store'), [
            'account_id' => $account->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Groceries',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $response->assertSessionHasInput('description', 'Groceries');
        $this->assertNoFinancialStatePersisted($transactionCount, $ledgerCount);
    }

    public function test_http_income_against_a_closed_account_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->closed()->create();
        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.income.store'), [
            'account_id' => $account->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Salary',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $response->assertSessionHasInput('description', 'Salary');
        $this->assertNoFinancialStatePersisted($transactionCount, $ledgerCount);
    }

    public function test_http_transfer_from_a_closed_account_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $closed = Account::factory()->for($user)->closed()->create();
        $active = Account::factory()->for($user)->create();
        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.transfer.store'), [
            'from_account_id' => $closed->id,
            'to_account_id' => $active->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Move funds',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $response->assertSessionHasInput('description', 'Move funds');
        $this->assertNoFinancialStatePersisted($transactionCount, $ledgerCount);
    }

    public function test_http_transfer_to_a_closed_account_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $active = Account::factory()->for($user)->create();
        $closed = Account::factory()->for($user)->closed()->create();
        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.transfer.store'), [
            'from_account_id' => $active->id,
            'to_account_id' => $closed->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Move funds',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $this->assertNoFinancialStatePersisted($transactionCount, $ledgerCount);
    }

    public function test_http_transfer_between_two_closed_accounts_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $closedA = Account::factory()->for($user)->closed()->create();
        $closedB = Account::factory()->for($user)->closed()->create();
        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.transfer.store'), [
            'from_account_id' => $closedA->id,
            'to_account_id' => $closedB->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Move funds',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $this->assertNoFinancialStatePersisted($transactionCount, $ledgerCount);
    }

    public function test_http_refund_against_a_closed_destination_account_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '300.00', '2026-01-05', 'Purchase');
        $closed = Account::factory()->for($user)->closed()->create();
        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.refund.store'), [
            'parent_transaction_id' => $expense->id,
            'account_id' => $closed->id,
            'amount' => '300.00',
            'transaction_date' => '2026-01-10',
            'description' => 'Refunded purchase',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $response->assertSessionHasInput('description', 'Refunded purchase');
        $this->assertNoFinancialStatePersisted($transactionCount, $ledgerCount);
    }

    public function test_http_reversal_whose_parent_account_is_now_closed_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '400.00', '2026-01-05', 'Failed payment');
        $account->update(['status' => 'CLOSED']);
        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.reversal.store'), [
            'parent_transaction_id' => $expense->id,
            'transaction_date' => '2026-01-06',
            'description' => 'Bank reversed the transaction',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $response->assertSessionHasInput('description', 'Bank reversed the transaction');
        $this->assertNoFinancialStatePersisted($transactionCount, $ledgerCount);
    }

    public function test_http_adjustment_against_a_closed_account_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->closed()->create();
        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.adjustment.store'), [
            'account_id' => $account->id,
            'direction' => 'OUTFLOW',
            'amount' => '25.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Correction',
            'reason' => 'Bank fee correction',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $response->assertSessionHasInput('description', 'Correction');
        $this->assertNoFinancialStatePersisted($transactionCount, $ledgerCount);
    }
}
