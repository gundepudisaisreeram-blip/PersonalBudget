<?php

namespace Tests\Feature\Ledger;

use App\Domain\Exceptions\InvalidTransactionException;
use App\Domain\Services\AccountBalanceService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\RefundService;
use App\Domain\Services\ReversalService;
use App\Domain\Services\TransactionService;
use App\Domain\Services\TransferService;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LedgerMovementTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactions;

    private TransferService $transfers;

    private RefundService $refunds;

    private ReversalService $reversals;

    private AccountBalanceService $balances;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = new OwnershipGuard;
        $this->transactions = new TransactionService($guard);
        $this->transfers = new TransferService($guard);
        $this->refunds = new RefundService($guard);
        $this->reversals = new ReversalService($guard);
        $this->balances = new AccountBalanceService;
    }

    public function test_expense_creates_exactly_one_outflow_entry(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);

        $transaction = $this->transactions->recordExpense(
            $user, $account, '150.00', '2026-01-05', 'Groceries',
        );

        $this->assertSame('EXPENSE', $transaction->transaction_type);
        $this->assertSame('POSTED', $transaction->status);
        $this->assertCount(1, $transaction->ledgerEntries);
        $this->assertSame('OUTFLOW', $transaction->ledgerEntries->first()->direction);
        $this->assertSame('150.00', $transaction->ledgerEntries->first()->amount);
        $this->assertSame('850.00', $this->balances->calculate($account->fresh()));
    }

    public function test_income_creates_exactly_one_inflow_entry(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);

        $transaction = $this->transactions->recordIncome(
            $user, $account, '5000.00', '2026-01-01', 'Salary',
        );

        $this->assertSame('INCOME', $transaction->transaction_type);
        $this->assertCount(1, $transaction->ledgerEntries);
        $this->assertSame('INFLOW', $transaction->ledgerEntries->first()->direction);
        $this->assertSame('6000.00', $this->balances->calculate($account->fresh()));
    }

    public function test_expense_rejects_non_positive_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->expectException(InvalidTransactionException::class);

        $this->transactions->recordExpense($user, $account, '0.00', '2026-01-05', 'Invalid');
    }

    public function test_transfer_creates_balanced_outflow_and_inflow_of_equal_amount(): void
    {
        $user = User::factory()->create();
        $savings = Account::factory()->for($user)->create(['name' => 'Savings', 'opening_balance' => '1000.00']);
        $creditCard = Account::factory()->for($user)->liability()->create(['name' => 'Credit Card', 'opening_balance' => '500.00']);

        $transaction = $this->transfers->transfer(
            $user, $savings, $creditCard, '200.00', '2026-01-10', 'Credit card payment',
        );

        $this->assertSame('TRANSFER', $transaction->transaction_type);
        $this->assertCount(2, $transaction->ledgerEntries);

        $outflow = $transaction->ledgerEntries->firstWhere('account_id', $savings->id);
        $inflow = $transaction->ledgerEntries->firstWhere('account_id', $creditCard->id);

        $this->assertSame('OUTFLOW', $outflow->direction);
        $this->assertSame('INFLOW', $inflow->direction);
        $this->assertSame($outflow->amount, $inflow->amount);
        $this->assertNotSame($outflow->account_id, $inflow->account_id);

        // Personal wealth is unaffected by a transfer: asset decreases by 200,
        // liability (debt) decreases by 200 too.
        $this->assertSame('800.00', $this->balances->calculate($savings->fresh()));
        $this->assertSame('300.00', $this->balances->calculate($creditCard->fresh()));
    }

    public function test_transfer_rejects_same_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->expectException(InvalidTransactionException::class);

        $this->transfers->transfer($user, $account, $account, '100.00', '2026-01-10', 'Invalid');
    }

    public function test_credit_card_purchase_is_expense_and_payment_is_transfer_without_double_counting(): void
    {
        $user = User::factory()->create();
        $bank = Account::factory()->for($user)->create(['opening_balance' => '10000.00']);
        $creditCard = Account::factory()->for($user)->liability()->create(['opening_balance' => '0.00']);

        // Purchase: EXPENSE, liability increases.
        $this->transactions->recordExpense($user, $creditCard, '5000.00', '2026-01-02', 'Purchase');
        $this->assertSame('5000.00', $this->balances->calculate($creditCard->fresh()));

        // Payment: TRANSFER, asset decreases, liability decreases. Not a second expense.
        $this->transfers->transfer($user, $bank, $creditCard, '5000.00', '2026-01-15', 'CC payment');

        $this->assertSame('0.00', $this->balances->calculate($creditCard->fresh()));
        $this->assertSame('5000.00', $this->balances->calculate($bank->fresh()));

        $expenseCount = Transaction::where('transaction_type', 'EXPENSE')->count();
        $this->assertSame(1, $expenseCount);
    }

    public function test_refund_creates_single_inflow_linked_to_expense_parent(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);

        $expense = $this->transactions->recordExpense($user, $account, '300.00', '2026-01-05', 'Purchase');

        $refund = $this->refunds->refund($user, $expense, $account, '300.00', '2026-01-10', 'Refunded purchase');

        $this->assertSame('REFUND', $refund->transaction_type);
        $this->assertSame($expense->id, $refund->parent_transaction_id);
        $this->assertCount(1, $refund->ledgerEntries);
        $this->assertSame('INFLOW', $refund->ledgerEntries->first()->direction);
        $this->assertSame('1000.00', $this->balances->calculate($account->fresh()));
    }

    public function test_refund_rejects_non_expense_parent(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $income = $this->transactions->recordIncome($user, $account, '300.00', '2026-01-05', 'Salary');

        $this->expectException(InvalidTransactionException::class);

        $this->refunds->refund($user, $income, $account, '300.00', '2026-01-10', 'Invalid refund');
    }

    public function test_reversal_of_single_entry_transaction_inverts_direction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);

        $expense = $this->transactions->recordExpense($user, $account, '400.00', '2026-01-05', 'Failed payment');

        $reversal = $this->reversals->reverse($user, $expense, '2026-01-06', 'Bank reversed the transaction');

        $this->assertSame('REVERSAL', $reversal->transaction_type);
        $this->assertSame($expense->id, $reversal->parent_transaction_id);
        $this->assertCount(1, $reversal->ledgerEntries);

        $reversalEntry = $reversal->ledgerEntries->first();
        $parentEntry = $expense->ledgerEntries->first();

        $this->assertSame('INFLOW', $reversalEntry->direction);
        $this->assertSame($parentEntry->account_id, $reversalEntry->account_id);
        $this->assertSame($parentEntry->amount, $reversalEntry->amount);

        // Net effect of expense + reversal is zero.
        $this->assertSame('1000.00', $this->balances->calculate($account->fresh()));
    }

    public function test_reversal_of_transfer_mirrors_both_entries_with_inverted_directions(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);
        $accountB = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);

        $transfer = $this->transfers->transfer($user, $accountA, $accountB, '250.00', '2026-01-05', 'Move funds');

        $reversal = $this->reversals->reverse($user, $transfer, '2026-01-06', 'Undo transfer');

        $this->assertCount(2, $reversal->ledgerEntries);

        $reversedFromA = $reversal->ledgerEntries->firstWhere('account_id', $accountA->id);
        $reversedFromB = $reversal->ledgerEntries->firstWhere('account_id', $accountB->id);

        $this->assertSame('INFLOW', $reversedFromA->direction);
        $this->assertSame('OUTFLOW', $reversedFromB->direction);
        $this->assertSame('250.00', $reversedFromA->amount);
        $this->assertSame('250.00', $reversedFromB->amount);

        $this->assertSame('1000.00', $this->balances->calculate($accountA->fresh()));
        $this->assertSame('1000.00', $this->balances->calculate($accountB->fresh()));
    }

    public function test_adjustment_creates_single_entry_and_writes_an_audit_log(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '1000.00']);

        $adjustment = $this->transactions->recordAdjustment(
            $user, $account, 'OUTFLOW', '25.00', '2026-01-20',
            'Bank fee correction', 'Verified against February statement closing balance',
        );

        $this->assertSame('ADJUSTMENT', $adjustment->transaction_type);
        $this->assertSame('ADJUSTMENT', $adjustment->source);
        $this->assertCount(1, $adjustment->ledgerEntries);
        $this->assertSame('975.00', $this->balances->calculate($account->fresh()));

        $auditLog = AuditLog::where('entity_type', Transaction::class)
            ->where('entity_id', $adjustment->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('ADJUSTMENT_CREATED', $auditLog->action);
        $this->assertSame('Verified against February statement closing balance', $auditLog->metadata['reason']);
    }

    public function test_transaction_and_ledger_entries_are_created_atomically(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        try {
            DB::transaction(function () use ($user, $account) {
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'transaction_date' => '2026-01-05',
                    'transaction_type' => 'EXPENSE',
                    'description' => 'Should not persist',
                    'source' => 'MANUAL',
                    'status' => 'POSTED',
                ]);

                // Violates the ledger_entries.amount > 0 CHECK constraint,
                // forcing a mid-transaction database failure.
                LedgerEntry::create([
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'account_id' => $account->id,
                    'direction' => 'OUTFLOW',
                    'amount' => '0.00',
                ]);
            });

            $this->fail('Expected a database exception due to the CHECK constraint violation.');
        } catch (\Throwable) {
            // Expected: the whole operation must roll back.
        }

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }
}
