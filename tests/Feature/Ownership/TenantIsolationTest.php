<?php

namespace Tests\Feature\Ownership;

use App\Domain\Exceptions\OwnershipViolationException;
use App\Domain\Services\ObligationAllocationService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\RefundService;
use App\Domain\Services\ReversalService;
use App\Domain\Services\TransactionService;
use App\Domain\Services\TransferService;
use App\Models\Account;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\ObligationAllocation;
use App\Models\PaymentObligation;
use App\Models\ReconciliationMatch;
use App\Models\StatementImport;
use App\Models\StatementTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private OwnershipGuard $guard;

    private TransactionService $transactions;

    private TransferService $transfers;

    private RefundService $refunds;

    private ReversalService $reversals;

    private ObligationAllocationService $allocations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new OwnershipGuard;
        $this->transactions = new TransactionService($this->guard);
        $this->transfers = new TransferService($this->guard);
        $this->refunds = new RefundService($this->guard);
        $this->reversals = new ReversalService($this->guard);
        $this->allocations = new ObligationAllocationService($this->guard);
    }

    public function test_user_cannot_record_an_expense_against_another_users_account(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();

        $this->expectException(OwnershipViolationException::class);

        $this->transactions->recordExpense($attacker, $ownerAccount, '100.00', '2026-01-01', 'Attack');
    }

    public function test_user_cannot_transfer_using_another_users_account(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $attackerAccount = Account::factory()->for($attacker)->create();

        $this->expectException(OwnershipViolationException::class);

        $this->transfers->transfer($attacker, $ownerAccount, $attackerAccount, '100.00', '2026-01-01', 'Attack');
    }

    public function test_user_cannot_use_another_users_transaction_as_a_refund_parent(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $attackerAccount = Account::factory()->for($attacker)->create();

        $ownerExpense = $this->transactions->recordExpense($owner, $ownerAccount, '100.00', '2026-01-01', 'Owner expense');

        $this->expectException(OwnershipViolationException::class);

        $this->refunds->refund($attacker, $ownerExpense, $attackerAccount, '100.00', '2026-01-02', 'Attack');
    }

    public function test_user_cannot_use_another_users_transaction_as_a_reversal_parent(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();

        $ownerExpense = $this->transactions->recordExpense($owner, $ownerAccount, '100.00', '2026-01-01', 'Owner expense');

        $this->expectException(OwnershipViolationException::class);

        $this->reversals->reverse($attacker, $ownerExpense, '2026-01-02', 'Attack');
    }

    public function test_user_cannot_allocate_against_another_users_obligation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $attackerAccount = Account::factory()->for($attacker)->create(['opening_balance' => '5000.00']);
        $ownerCategory = Category::factory()->for($owner)->create();

        $ownerObligation = PaymentObligation::create([
            'user_id' => $owner->id,
            'idempotency_key' => 'owner-obligation',
            'category_id' => $ownerCategory->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);

        $attackerExpense = $this->transactions->recordExpense($attacker, $attackerAccount, '1000.00', '2026-01-05', 'Attacker expense');

        $this->expectException(OwnershipViolationException::class);

        $this->allocations->allocate($attacker, $ownerObligation, $attackerExpense, '1000.00');
    }

    public function test_user_cannot_allocate_another_users_transaction_to_their_own_obligation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create(['opening_balance' => '5000.00']);
        $attackerCategory = Category::factory()->for($attacker)->create();

        $attackerObligation = PaymentObligation::create([
            'user_id' => $attacker->id,
            'idempotency_key' => 'attacker-obligation',
            'category_id' => $attackerCategory->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);

        $ownerExpense = $this->transactions->recordExpense($owner, $ownerAccount, '1000.00', '2026-01-05', 'Owner expense');

        $this->expectException(OwnershipViolationException::class);

        $this->allocations->allocate($attacker, $attackerObligation, $ownerExpense, '1000.00');
    }

    public function test_user_cannot_use_another_users_private_category(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $attackerAccount = Account::factory()->for($attacker)->create();
        $ownerCategory = Category::factory()->for($owner)->create();

        $this->expectException(OwnershipViolationException::class);

        $this->transactions->recordExpense(
            $attacker, $attackerAccount, '100.00', '2026-01-01', 'Attack', $ownerCategory,
        );
    }

    public function test_user_can_use_a_system_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $systemCategory = Category::factory()->system()->create(['name' => 'Food']);

        $transaction = $this->transactions->recordExpense(
            $user, $account, '100.00', '2026-01-01', 'Lunch', $systemCategory,
        );

        $this->assertSame($systemCategory->id, $transaction->category_id);
    }

    public function test_composite_foreign_key_rejects_a_cross_tenant_ledger_entry_at_the_database_level(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $ownerTransaction = Transaction::create([
            'user_id' => $owner->id,
            'transaction_date' => '2026-01-01',
            'transaction_type' => 'EXPENSE',
            'description' => 'Owner expense',
            'source' => 'MANUAL',
            'status' => 'POSTED',
        ]);

        $this->expectException(QueryException::class);

        LedgerEntry::create([
            'user_id' => $attacker->id,
            'transaction_id' => $ownerTransaction->id,
            'account_id' => $ownerAccount->id,
            'direction' => 'OUTFLOW',
            'amount' => '100.00',
        ]);
    }

    public function test_composite_foreign_key_rejects_a_cross_tenant_obligation_allocation_at_the_database_level(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ownerCategory = Category::factory()->for($owner)->create();
        $attackerAccount = Account::factory()->for($attacker)->create();

        $ownerObligation = PaymentObligation::create([
            'user_id' => $owner->id,
            'idempotency_key' => 'owner-obligation-db',
            'category_id' => $ownerCategory->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);

        $attackerTransaction = Transaction::create([
            'user_id' => $attacker->id,
            'transaction_date' => '2026-01-01',
            'transaction_type' => 'EXPENSE',
            'description' => 'Attacker expense',
            'source' => 'MANUAL',
            'status' => 'POSTED',
        ]);

        $this->expectException(QueryException::class);

        ObligationAllocation::create([
            'user_id' => $attacker->id,
            'payment_obligation_id' => $ownerObligation->id,
            'transaction_id' => $attackerTransaction->id,
            'allocated_amount' => '500.00',
        ]);
    }

    public function test_composite_foreign_key_rejects_a_cross_tenant_reconciliation_match_at_the_database_level(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();

        $statementImport = StatementImport::create([
            'user_id' => $owner->id,
            'account_id' => $ownerAccount->id,
            'bank' => 'ICICI',
            'original_filename' => 'jan.csv',
            'storage_path' => 'statements/jan.csv',
            'file_hash' => hash('sha256', 'jan.csv-owner'),
            'file_type' => 'CSV',
            'status' => 'COMPLETED',
        ]);

        $statementTransaction = StatementTransaction::create([
            'user_id' => $owner->id,
            'statement_import_id' => $statementImport->id,
            'transaction_date' => '2026-01-05',
            'description' => 'POS purchase',
            'normalized_amount' => '250.00',
            'direction' => 'OUTFLOW',
            'normalized_hash' => hash('sha256', 'txn-owner'),
            'processing_status' => 'READY_FOR_REVIEW',
            'categorization_status' => 'UNCATEGORIZED',
            'match_status' => 'UNMATCHED',
            'duplicate_status' => 'UNIQUE',
        ]);

        $attackerTransaction = Transaction::create([
            'user_id' => $attacker->id,
            'transaction_date' => '2026-01-05',
            'transaction_type' => 'EXPENSE',
            'description' => 'Attacker expense',
            'source' => 'MANUAL',
            'status' => 'POSTED',
        ]);

        $this->expectException(QueryException::class);

        ReconciliationMatch::create([
            'user_id' => $attacker->id,
            'statement_transaction_id' => $statementTransaction->id,
            'transaction_id' => $attackerTransaction->id,
            'match_type' => 'EXACT',
            'status' => 'SUGGESTED',
        ]);
    }

    public function test_statement_import_account_ownership_is_enforced_by_the_shared_guard(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();

        $this->expectException(OwnershipViolationException::class);

        $this->guard->assertAccountOwnership($ownerAccount, $attacker->id);
    }
}
