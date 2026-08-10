<?php

namespace Tests\Feature\Transactions;

use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\TransactionService;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the corrected Refund/Reversal parent_transaction_id contract: a
 * tenant-scoped Form Request closure rule is authoritative on the normal
 * HTTP path (row 2 of the Phase 3 Decision Package's exception table), and
 * domain-level InvalidTransactionException cases (row 3: non-EXPENSE
 * parent, entry-less parent, same-account transfer) redirect back with the
 * full five-part contract rather than a 403.
 */
class RefundReversalParentValidationTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactions = new TransactionService(new OwnershipGuard);
    }

    // -----------------------------------------------------------------
    // Row 2: Form Request tenant-scoped validation, not 403
    // -----------------------------------------------------------------

    public function test_refund_rejects_another_users_parent_transaction_as_a_validation_error_not_a_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $attackerAccount = Account::factory()->for($attacker)->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $ownerExpense = $this->transactions->recordExpense($owner, $ownerAccount, '100.00', '2026-01-05', 'Owner expense');
        $transactionCount = Transaction::count();

        $response = $this->actingAs($attacker)->post(route('transactions.refund.store'), [
            'parent_transaction_id' => $ownerExpense->id,
            'account_id' => $attackerAccount->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-10',
            'description' => 'Attack',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('parent_transaction_id');
        $response->assertStatus(302);
        $this->assertSame($transactionCount, Transaction::count());
    }

    public function test_refund_rejects_a_nonexistent_parent_transaction_id_identically_to_another_users(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $attackerAccount = Account::factory()->for($attacker)->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $ownerExpense = $this->transactions->recordExpense($owner, $ownerAccount, '100.00', '2026-01-05', 'Owner expense');

        $expectedMessage = 'The selected parent transaction is invalid.';

        $crossTenant = $this->actingAs($attacker)->post(route('transactions.refund.store'), [
            'parent_transaction_id' => $ownerExpense->id,
            'account_id' => $attackerAccount->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-10',
            'description' => 'Attack A',
        ]);
        $crossTenant->assertStatus(302);
        $crossTenant->assertSessionHasErrors(['parent_transaction_id' => $expectedMessage]);

        $nonexistent = $this->actingAs($attacker)->post(route('transactions.refund.store'), [
            'parent_transaction_id' => 999999,
            'account_id' => $attackerAccount->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-10',
            'description' => 'Attack B',
        ]);
        $nonexistent->assertStatus(302);
        $nonexistent->assertSessionHasErrors(['parent_transaction_id' => $expectedMessage]);
    }

    public function test_reversal_rejects_another_users_parent_transaction_as_a_validation_error_not_a_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $ownerExpense = $this->transactions->recordExpense($owner, $ownerAccount, '100.00', '2026-01-05', 'Owner expense');
        $transactionCount = Transaction::count();

        $response = $this->actingAs($attacker)->post(route('transactions.reversal.store'), [
            'parent_transaction_id' => $ownerExpense->id,
            'transaction_date' => '2026-01-06',
            'description' => 'Attack',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('parent_transaction_id');
        $response->assertStatus(302);
        $this->assertSame($transactionCount, Transaction::count());
    }

    public function test_reversal_rejects_a_nonexistent_parent_transaction_id_as_a_validation_error(): void
    {
        $user = User::factory()->create();
        $transactionCount = Transaction::count();

        $response = $this->actingAs($user)->post(route('transactions.reversal.store'), [
            'parent_transaction_id' => 999999,
            'transaction_date' => '2026-01-06',
            'description' => 'Attack',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('parent_transaction_id');
        $this->assertSame($transactionCount, Transaction::count());
    }

    // -----------------------------------------------------------------
    // Row 3: domain-level InvalidTransactionException, full 5-part contract
    // -----------------------------------------------------------------

    public function test_refund_against_a_non_expense_parent_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $income = $this->transactions->recordIncome($user, $account, '300.00', '2026-01-05', 'Salary');
        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.refund.store'), [
            'parent_transaction_id' => $income->id,
            'account_id' => $account->id,
            'amount' => '300.00',
            'transaction_date' => '2026-01-10',
            'description' => 'Invalid refund',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $response->assertSessionHasInput('description', 'Invalid refund');
        $this->assertSame($transactionCount, Transaction::count());
        $this->assertSame($ledgerCount, LedgerEntry::count());
    }

    public function test_reversal_against_a_parent_with_no_ledger_entries_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();

        // Bypasses the domain service deliberately to construct the one
        // scenario the services themselves can never produce: a Transaction
        // with zero Ledger Entries.
        $orphan = Transaction::create([
            'user_id' => $user->id,
            'transaction_date' => '2026-01-05',
            'transaction_type' => 'EXPENSE',
            'description' => 'Orphan',
            'source' => 'MANUAL',
            'status' => 'POSTED',
        ]);

        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.reversal.store'), [
            'parent_transaction_id' => $orphan->id,
            'transaction_date' => '2026-01-06',
            'description' => 'Invalid reversal',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $response->assertSessionHasInput('description', 'Invalid reversal');
        $this->assertSame($transactionCount, Transaction::count());
        $this->assertSame($ledgerCount, LedgerEntry::count());
    }

    public function test_transfer_with_the_same_source_and_destination_account_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transactionCount = Transaction::count();
        $ledgerCount = LedgerEntry::count();

        $response = $this->actingAs($user)->post(route('transactions.transfer.store'), [
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-05',
            'description' => 'Invalid transfer',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('transaction');
        $response->assertSessionHasInput('description', 'Invalid transfer');
        $this->assertSame($transactionCount, Transaction::count());
        $this->assertSame($ledgerCount, LedgerEntry::count());
    }

    // -----------------------------------------------------------------
    // Valid cases
    // -----------------------------------------------------------------

    public function test_a_refund_can_reference_the_authenticated_users_own_expense(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '100.00', '2026-01-05', 'Purchase');

        $response = $this->actingAs($user)->post(route('transactions.refund.store'), [
            'parent_transaction_id' => $expense->id,
            'account_id' => $account->id,
            'amount' => '100.00',
            'transaction_date' => '2026-01-10',
            'description' => 'Valid refund',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('transactions', ['description' => 'Valid refund', 'parent_transaction_id' => $expense->id]);
    }

    public function test_a_reversal_can_reference_the_authenticated_users_own_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $expense = $this->transactions->recordExpense($user, $account, '100.00', '2026-01-05', 'Purchase');

        $response = $this->actingAs($user)->post(route('transactions.reversal.store'), [
            'parent_transaction_id' => $expense->id,
            'transaction_date' => '2026-01-06',
            'description' => 'Valid reversal',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('transactions', ['description' => 'Valid reversal', 'parent_transaction_id' => $expense->id]);
    }
}
