<?php

namespace Tests\Feature\Ledger;

use App\Domain\Exceptions\ImmutableRecordException;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\RefundService;
use App\Domain\Services\ReversalService;
use App\Domain\Services\TransactionService;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves posted financial records actively reject mutation/deletion at the
 * model level, and that legitimate creation, Refund, and Reversal flows
 * (which only ever create new rows) remain unaffected.
 */
class ImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactions;

    private RefundService $refunds;

    private ReversalService $reversals;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = new OwnershipGuard;
        $this->transactions = new TransactionService($guard);
        $this->refunds = new RefundService($guard);
        $this->reversals = new ReversalService($guard);
    }

    public function test_a_posted_transaction_cannot_be_updated(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = $this->transactions->recordExpense($user, $account, '100.00', '2026-01-05', 'Groceries');

        try {
            $transaction->update(['description' => 'Tampered']);
            $this->fail('Expected updating a posted transaction to throw ImmutableRecordException.');
        } catch (ImmutableRecordException) {
            // Expected.
        }

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'description' => 'Groceries']);
    }

    public function test_a_posted_transaction_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = $this->transactions->recordExpense($user, $account, '100.00', '2026-01-05', 'Groceries');

        try {
            $transaction->delete();
            $this->fail('Expected deleting a posted transaction to throw ImmutableRecordException.');
        } catch (ImmutableRecordException) {
            // Expected.
        }

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    public function test_a_ledger_entry_cannot_be_updated(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = $this->transactions->recordExpense($user, $account, '100.00', '2026-01-05', 'Groceries');
        $entry = $transaction->ledgerEntries->first();

        try {
            $entry->update(['amount' => '999.00']);
            $this->fail('Expected updating a posted ledger entry to throw ImmutableRecordException.');
        } catch (ImmutableRecordException) {
            // Expected.
        }

        $this->assertDatabaseHas('ledger_entries', ['id' => $entry->id, 'amount' => '100.00']);
    }

    public function test_a_ledger_entry_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = $this->transactions->recordExpense($user, $account, '100.00', '2026-01-05', 'Groceries');
        $entry = $transaction->ledgerEntries->first();

        try {
            $entry->delete();
            $this->fail('Expected deleting a posted ledger entry to throw ImmutableRecordException.');
        } catch (ImmutableRecordException) {
            // Expected.
        }

        $this->assertDatabaseHas('ledger_entries', ['id' => $entry->id]);
    }

    public function test_transaction_creation_still_succeeds_with_the_immutability_guard_active(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);

        $transaction = $this->transactions->recordExpense($user, $account, '150.00', '2026-01-05', 'Groceries');

        $this->assertNotNull($transaction->id);
        $this->assertSame('POSTED', $transaction->status);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_refund_still_succeeds_with_the_immutability_guard_active(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);
        $expense = $this->transactions->recordExpense($user, $account, '300.00', '2026-01-05', 'Purchase');

        $refund = $this->refunds->refund($user, $expense, $account, '300.00', '2026-01-10', 'Refunded purchase');

        $this->assertSame('REFUND', $refund->transaction_type);
        $this->assertSame($expense->id, $refund->parent_transaction_id);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_reversal_still_succeeds_with_the_immutability_guard_active(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);
        $expense = $this->transactions->recordExpense($user, $account, '400.00', '2026-01-05', 'Failed payment');

        $reversal = $this->reversals->reverse($user, $expense, '2026-01-06', 'Bank reversed the transaction');

        $this->assertSame('REVERSAL', $reversal->transaction_type);
        $this->assertSame($expense->id, $reversal->parent_transaction_id);
        $this->assertDatabaseCount('transactions', 2);
    }
}
