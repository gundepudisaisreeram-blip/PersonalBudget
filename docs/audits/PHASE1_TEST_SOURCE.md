# PHASE 1 TEST SOURCE — SOURCE BUNDLE

Concatenation of all Feature and Unit test files, verbatim.


---

## FILE: tests/Feature/Database/CategorySeederTest.php

```php
<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_system_categories_with_null_user_id(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertTrue(Category::whereNull('user_id')->where('name', 'Food')->exists());
        $this->assertTrue(Category::whereNull('user_id')->where('name', 'Income')->exists());
        $this->assertSame(8, Category::whereNull('user_id')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(8, Category::whereNull('user_id')->count());
    }

    public function test_system_category_is_usable_and_owned_by_every_user(): void
    {
        $this->seed(CategorySeeder::class);

        $food = Category::whereNull('user_id')->where('name', 'Food')->firstOrFail();

        $this->assertTrue($food->isSystem());
        $this->assertTrue($food->ownedBy(1));
        $this->assertTrue($food->ownedBy(9999));
    }
}
```


---

## FILE: tests/Feature/Database/ConstraintTest.php

```php
<?php

namespace Tests\Feature\Database;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\ObligationAllocation;
use App\Models\PaymentObligation;
use App\Models\ReconciliationMatch;
use App\Models\Setting;
use App\Models\StatementImport;
use App\Models\StatementTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_reject_negative_opening_balance(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        Account::create([
            'user_id' => $user->id,
            'name' => 'Bad Account',
            'institution' => 'Test Bank',
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'INR',
            'opening_balance' => '-1.00',
            'opening_balance_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_accounts_accept_zero_opening_balance(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Zero Account',
            'institution' => 'Test Bank',
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'INR',
            'opening_balance' => '0.00',
            'opening_balance_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $this->assertSame('0.00', $account->opening_balance);
    }

    public function test_ledger_entries_reject_zero_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_date' => '2026-01-01',
            'transaction_type' => 'EXPENSE',
            'description' => 'Test',
            'source' => 'MANUAL',
            'status' => 'POSTED',
        ]);

        $this->expectException(QueryException::class);

        LedgerEntry::create([
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
            'account_id' => $account->id,
            'direction' => 'OUTFLOW',
            'amount' => '0.00',
        ]);
    }

    public function test_ledger_entries_reject_negative_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_date' => '2026-01-01',
            'transaction_type' => 'EXPENSE',
            'description' => 'Test',
            'source' => 'MANUAL',
            'status' => 'POSTED',
        ]);

        $this->expectException(QueryException::class);

        LedgerEntry::create([
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
            'account_id' => $account->id,
            'direction' => 'OUTFLOW',
            'amount' => '-50.00',
        ]);
    }

    public function test_obligation_allocations_reject_non_positive_amount(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $account = Account::factory()->for($user)->create();

        $obligation = PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-check',
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_date' => '2026-01-01',
            'transaction_type' => 'EXPENSE',
            'description' => 'Test',
            'source' => 'MANUAL',
            'status' => 'POSTED',
        ]);

        $this->expectException(QueryException::class);

        ObligationAllocation::create([
            'user_id' => $user->id,
            'payment_obligation_id' => $obligation->id,
            'transaction_id' => $transaction->id,
            'allocated_amount' => '0.00',
        ]);
    }

    public function test_budgets_reject_negative_amount(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        Budget::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '-100.00',
        ]);
    }

    public function test_users_email_is_unique(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'duplicate@example.com']);
    }

    public function test_statement_imports_unique_file_hash_per_user_not_globally(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountA = Account::factory()->for($userA)->create();
        $accountB = Account::factory()->for($userB)->create();
        $hash = hash('sha256', 'same-file-contents');

        StatementImport::create([
            'user_id' => $userA->id,
            'account_id' => $accountA->id,
            'bank' => 'ICICI',
            'original_filename' => 'jan.csv',
            'storage_path' => 'a.csv',
            'file_hash' => $hash,
            'file_type' => 'CSV',
            'status' => 'COMPLETED',
        ]);

        // Different user, same file hash: allowed (unique is per-user).
        $second = StatementImport::create([
            'user_id' => $userB->id,
            'account_id' => $accountB->id,
            'bank' => 'ICICI',
            'original_filename' => 'jan.csv',
            'storage_path' => 'b.csv',
            'file_hash' => $hash,
            'file_type' => 'CSV',
            'status' => 'COMPLETED',
        ]);
        $this->assertNotNull($second->id);

        // Same user, same file hash again: rejected (re-upload protection).
        $this->expectException(QueryException::class);

        StatementImport::create([
            'user_id' => $userA->id,
            'account_id' => $accountA->id,
            'bank' => 'ICICI',
            'original_filename' => 'jan-again.csv',
            'storage_path' => 'a2.csv',
            'file_hash' => $hash,
            'file_type' => 'CSV',
            'status' => 'COMPLETED',
        ]);
    }

    public function test_reconciliation_matches_statement_transaction_id_is_unique(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $statementImport = StatementImport::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'bank' => 'ICICI',
            'original_filename' => 'jan.csv',
            'storage_path' => 'a.csv',
            'file_hash' => hash('sha256', 'unique-match-test'),
            'file_type' => 'CSV',
            'status' => 'COMPLETED',
        ]);

        $statementTransaction = StatementTransaction::create([
            'user_id' => $user->id,
            'statement_import_id' => $statementImport->id,
            'transaction_date' => '2026-01-05',
            'description' => 'POS purchase',
            'normalized_amount' => '250.00',
            'direction' => 'OUTFLOW',
            'normalized_hash' => hash('sha256', 'stmt-txn'),
            'processing_status' => 'READY_FOR_REVIEW',
            'categorization_status' => 'UNCATEGORIZED',
            'match_status' => 'UNMATCHED',
            'duplicate_status' => 'UNIQUE',
        ]);

        $transactionA = Transaction::create([
            'user_id' => $user->id, 'transaction_date' => '2026-01-05', 'transaction_type' => 'EXPENSE',
            'description' => 'A', 'source' => 'MANUAL', 'status' => 'POSTED',
        ]);
        $transactionB = Transaction::create([
            'user_id' => $user->id, 'transaction_date' => '2026-01-05', 'transaction_type' => 'EXPENSE',
            'description' => 'B', 'source' => 'MANUAL', 'status' => 'POSTED',
        ]);

        ReconciliationMatch::create([
            'user_id' => $user->id,
            'statement_transaction_id' => $statementTransaction->id,
            'transaction_id' => $transactionA->id,
            'match_type' => 'EXACT',
            'status' => 'CONFIRMED',
        ]);

        $this->expectException(QueryException::class);

        ReconciliationMatch::create([
            'user_id' => $user->id,
            'statement_transaction_id' => $statementTransaction->id,
            'transaction_id' => $transactionB->id,
            'match_type' => 'EXACT',
            'status' => 'SUGGESTED',
        ]);
    }

    public function test_settings_are_unique_per_user_and_key(): void
    {
        $user = User::factory()->create();

        Setting::create(['user_id' => $user->id, 'key' => 'timezone', 'value' => 'Asia/Kolkata']);

        $this->expectException(QueryException::class);

        Setting::create(['user_id' => $user->id, 'key' => 'timezone', 'value' => 'UTC']);
    }
}
```


---

## FILE: tests/Feature/Database/SchemaReconciliationTest.php

```php
<?php

namespace Tests\Feature\Database;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Proves the corrections made during the 04-vs-09 specification reconciliation
 * (2026-08-09): direct user_id -> users.id FKs on the three composite-tenant-FK
 * tables, and accounts.institution / accounts.subtype / accounts.currency.
 */
class SchemaReconciliationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{0: string}>
     */
    public static function tablesRequiringDirectUserForeignKey(): array
    {
        return [
            ['ledger_entries'],
            ['obligation_allocations'],
            ['reconciliation_matches'],
        ];
    }

    #[DataProvider('tablesRequiringDirectUserForeignKey')]
    public function test_table_has_a_direct_foreign_key_from_user_id_to_users(string $table): void
    {
        $constraints = DB::select(
            <<<'SQL'
                SELECT COUNT(*) as total
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = 'user_id'
                  AND REFERENCED_TABLE_NAME = 'users'
                  AND REFERENCED_COLUMN_NAME = 'id'
            SQL,
            [$table],
        );

        $this->assertSame(1, (int) $constraints[0]->total, "Expected a direct single-column FK from {$table}.user_id to users.id.");
    }

    public function test_accounts_institution_is_required(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        Account::create([
            'user_id' => $user->id,
            'name' => 'No Institution',
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'INR',
            'opening_balance' => '0.00',
            'opening_balance_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_accounts_subtype_is_required(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        Account::create([
            'user_id' => $user->id,
            'name' => 'No Subtype',
            'institution' => 'Test Bank',
            'account_type' => 'ASSET',
            'currency' => 'INR',
            'opening_balance' => '0.00',
            'opening_balance_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_accounts_currency_is_not_artificially_length_restricted(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Long Currency Code',
            'institution' => 'Test Bank',
            'account_type' => 'ASSET',
            'subtype' => 'SAVINGS',
            'currency' => 'XTEST',
            'opening_balance' => '0.00',
            'opening_balance_date' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $this->assertSame('XTEST', $account->currency);
    }
}
```


---

## FILE: tests/Feature/ExampleTest.php

```php
<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
```


---

## FILE: tests/Feature/Ledger/LedgerMovementTest.php

```php
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
```


---

## FILE: tests/Feature/Obligations/ObligationAllocationTest.php

```php
<?php

namespace Tests\Feature\Obligations;

use App\Domain\Exceptions\AllocationException;
use App\Domain\Services\ObligationAllocationService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\RefundService;
use App\Domain\Services\ReversalService;
use App\Domain\Services\TransactionService;
use App\Domain\Services\TransferService;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ObligationAllocationTest extends TestCase
{
    use RefreshDatabase;

    private ObligationAllocationService $allocations;

    private TransactionService $transactions;

    private TransferService $transfers;

    private RefundService $refunds;

    private ReversalService $reversals;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = new OwnershipGuard;
        $this->allocations = new ObligationAllocationService($guard);
        $this->transactions = new TransactionService($guard);
        $this->transfers = new TransferService($guard);
        $this->refunds = new RefundService($guard);
        $this->reversals = new ReversalService($guard);
    }

    private function makeObligation(User $user, string $plannedAmount = '19159.00'): PaymentObligation
    {
        $category = Category::factory()->for($user)->create();

        return PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-'.uniqid(),
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => $plannedAmount,
            'status' => 'PENDING',
        ]);
    }

    public function test_allocation_amount_must_be_positive(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $obligation = $this->makeObligation($user);
        $expense = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-10', 'EMI');

        $this->expectException(AllocationException::class);

        $this->allocations->allocate($user, $obligation, $expense, '0.00');
    }

    public function test_only_expense_and_transfer_transactions_may_fulfill_an_obligation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '10000.00']);
        $obligation = $this->makeObligation($user);

        $income = $this->transactions->recordIncome($user, $account, '5000.00', '2026-01-02', 'Salary');

        $this->expectException(AllocationException::class);

        $this->allocations->allocate($user, $obligation, $income, '5000.00');
    }

    public function test_refund_reversal_and_adjustment_transactions_cannot_fulfill_an_obligation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '10000.00']);

        $expense = $this->transactions->recordExpense($user, $account, '1000.00', '2026-01-02', 'Purchase');
        $refund = $this->refunds->refund($user, $expense, $account, '1000.00', '2026-01-03', 'Refund');
        $reversal = $this->reversals->reverse($user, $expense, '2026-01-04', 'Reversal');
        $adjustment = $this->transactions->recordAdjustment(
            $user, $account, 'OUTFLOW', '10.00', '2026-01-05', 'Fee', 'Bank fee',
        );

        $ineligible = [$refund, $reversal, $adjustment];
        $rejectedCount = 0;

        foreach ($ineligible as $transaction) {
            $obligation = $this->makeObligation($user);

            try {
                $this->allocations->allocate($user, $obligation, $transaction, '10.00');
            } catch (AllocationException) {
                $rejectedCount++;
            }
        }

        $this->assertSame(count($ineligible), $rejectedCount);
    }

    public function test_partial_allocation_sets_status_to_partially_paid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '19159.00');

        $expense = $this->transactions->recordExpense($user, $account, '10000.00', '2026-01-10', 'EMI partial');
        $this->allocations->allocate($user, $obligation, $expense, '10000.00');

        $obligation->refresh();
        $this->assertSame('PARTIALLY_PAID', $obligation->status);
    }

    public function test_full_allocation_sets_status_to_paid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '19159.00');

        $expense = $this->transactions->recordExpense($user, $account, '19159.00', '2026-01-10', 'EMI full');
        $this->allocations->allocate($user, $obligation, $expense, '19159.00');

        $obligation->refresh();
        $this->assertSame('PAID', $obligation->status);
    }

    public function test_overpayment_cannot_be_allocated_beyond_planned_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '19159.00');

        $expense = $this->transactions->recordExpense($user, $account, '19200.00', '2026-01-10', 'EMI overpaid');

        // Allocate exactly the planned amount; the remaining 41 stays unallocated.
        $this->allocations->allocate($user, $obligation, $expense, '19159.00');

        $obligation->refresh();
        $this->assertSame('PAID', $obligation->status);

        $this->expectException(AllocationException::class);
        $this->allocations->allocate($user, $obligation, $expense, '41.00');
    }

    public function test_multiple_transactions_can_fulfill_one_obligation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $expenseA = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-05', 'Part A');
        $expenseB = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-06', 'Part B');

        $this->allocations->allocate($user, $obligation, $expenseA, '5000.00');
        $this->allocations->allocate($user, $obligation, $expenseB, '5000.00');

        $obligation->refresh();
        $this->assertSame('PAID', $obligation->status);
        $this->assertSame(2, $obligation->obligationAllocations()->count());
    }

    public function test_one_transaction_can_fulfill_multiple_obligations(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligationA = $this->makeObligation($user, '3000.00');
        $obligationB = $this->makeObligation($user, '3000.00');

        $expense = $this->transactions->recordExpense($user, $account, '6000.00', '2026-01-05', 'Combined payment');

        $this->allocations->allocate($user, $obligationA, $expense, '3000.00');
        $this->allocations->allocate($user, $obligationB, $expense, '3000.00');

        $this->assertSame('PAID', $obligationA->fresh()->status);
        $this->assertSame('PAID', $obligationB->fresh()->status);
    }

    public function test_removing_an_allocation_recalculates_status_back_to_partially_paid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $expenseA = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-05', 'Part A');
        $expenseB = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-06', 'Part B');

        $this->allocations->allocate($user, $obligation, $expenseA, '5000.00');
        $allocationB = $this->allocations->allocate($user, $obligation, $expenseB, '5000.00');

        $this->assertSame('PAID', $obligation->fresh()->status);

        $this->allocations->removeAllocation($user, $allocationB);

        $this->assertSame('PARTIALLY_PAID', $obligation->fresh()->status);
    }

    public function test_removing_all_allocations_recalculates_status_back_to_pending(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $expense = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-05', 'Part A');
        $allocation = $this->allocations->allocate($user, $obligation, $expense, '5000.00');

        $this->assertSame('PARTIALLY_PAID', $obligation->fresh()->status);

        $this->allocations->removeAllocation($user, $allocation);

        $this->assertSame('PENDING', $obligation->fresh()->status);
    }

    public function test_transfer_can_fulfill_an_investment_obligation(): void
    {
        $user = User::factory()->create();
        $savings = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $mutualFund = Account::factory()->for($user)->create(['opening_balance' => '0.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $transfer = $this->transfers->transfer($user, $savings, $mutualFund, '10000.00', '2026-01-05', 'SIP');

        $this->allocations->allocate($user, $obligation, $transfer, '10000.00');

        $this->assertSame('PAID', $obligation->fresh()->status);
    }

    public function test_obligation_cannot_be_skipped_while_it_has_active_allocations(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $expense = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-05', 'Part A');
        $this->allocations->allocate($user, $obligation, $expense, '5000.00');

        $this->expectException(AllocationException::class);
        $this->allocations->skip($user, $obligation);
    }

    public function test_obligation_can_be_skipped_when_it_has_no_allocations(): void
    {
        $user = User::factory()->create();
        $obligation = $this->makeObligation($user, '10000.00');

        $this->allocations->skip($user, $obligation);

        $this->assertSame('SKIPPED', $obligation->fresh()->status);
    }

    public function test_obligation_cannot_be_cancelled_while_it_has_active_allocations(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $expense = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-05', 'Part A');
        $this->allocations->allocate($user, $obligation, $expense, '5000.00');

        $this->expectException(AllocationException::class);
        $this->allocations->cancel($user, $obligation);
    }

    /**
     * Proves the obligation row is genuinely locked with SELECT ... FOR UPDATE:
     * a second connection attempting to lock the same row must block until
     * the first connection's transaction ends, rather than reading stale data.
     */
    public function test_allocation_locking_blocks_a_concurrent_writer(): void
    {
        $user = User::factory()->create();
        $obligation = $this->makeObligation($user, '10000.00');

        Config::set('database.connections.locktest', Config::get('database.connections.mysql'));

        DB::connection()->beginTransaction();
        DB::connection()->table('payment_obligations')->where('id', $obligation->id)->lockForUpdate()->first();

        DB::connection('locktest')->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;
        $start = microtime(true);

        try {
            DB::connection('locktest')->transaction(function () use ($obligation) {
                DB::connection('locktest')->table('payment_obligations')
                    ->where('id', $obligation->id)
                    ->lockForUpdate()
                    ->first();
            });
        } catch (\Throwable $e) {
            $blocked = true;
        }

        $elapsed = microtime(true) - $start;

        DB::connection()->rollBack();
        DB::purge('locktest');

        $this->assertTrue($blocked, 'Expected the second connection to be blocked by the row lock.');
        $this->assertGreaterThanOrEqual(1.0, $elapsed, 'Expected the second connection to wait for the lock timeout.');
    }
}
```


---

## FILE: tests/Feature/Obligations/PaymentObligationIdentityTest.php

```php
<?php

namespace Tests\Feature\Obligations;

use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\PaymentObligationService;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentObligationIdentityTest extends TestCase
{
    use RefreshDatabase;

    private PaymentObligationService $obligations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obligations = new PaymentObligationService(new OwnershipGuard);
    }

    public function test_recurring_identity_is_accepted(): void
    {
        $user = User::factory()->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create();

        $obligation = PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => $template->id,
            'occurrence_key' => '2026-01',
            'idempotency_key' => null,
            'category_id' => $template->category_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
            'is_mandatory' => true,
        ]);

        $this->assertTrue($obligation->isRecurring());
        $this->assertFalse($obligation->isOneTime());
    }

    public function test_one_time_identity_is_accepted(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $obligation = PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => null,
            'occurrence_key' => null,
            'idempotency_key' => 'one-time-google-2026-08-11',
            'category_id' => $category->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'due_date' => '2026-08-11',
            'planned_amount' => '1950.00',
            'status' => 'PENDING',
            'is_mandatory' => false,
        ]);

        $this->assertFalse($obligation->isRecurring());
        $this->assertTrue($obligation->isOneTime());
    }

    public function test_identity_check_rejects_both_recurring_and_one_time_identifiers(): void
    {
        $user = User::factory()->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => $template->id,
            'occurrence_key' => '2026-01',
            'idempotency_key' => 'conflicting-key',
            'category_id' => $template->category_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
            'is_mandatory' => true,
        ]);
    }

    public function test_identity_check_rejects_neither_recurring_nor_one_time_identifiers(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => null,
            'occurrence_key' => null,
            'idempotency_key' => null,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
            'is_mandatory' => true,
        ]);
    }

    public function test_duplicate_recurring_occurrence_violates_unique_constraint(): void
    {
        $user = User::factory()->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create();

        PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => $template->id,
            'occurrence_key' => '2026-01',
            'category_id' => $template->category_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);

        $this->expectException(QueryException::class);

        PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => $template->id,
            'occurrence_key' => '2026-01',
            'category_id' => $template->category_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);
    }

    public function test_duplicate_idempotency_key_violates_unique_constraint(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'duplicate-key',
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);

        $this->expectException(QueryException::class);

        PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'duplicate-key',
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '500.00',
            'status' => 'PENDING',
        ]);
    }

    public function test_recurring_generation_is_idempotent_via_service(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create([
            'amount' => '65000.00',
            'category_id' => $category->id,
        ]);

        $first = $this->obligations->createRecurringOccurrence(
            $user, $template, '2026-08', '2026-08-01', '2026-08-31', '2026-08-05', '65000.00',
        );

        $second = $this->obligations->createRecurringOccurrence(
            $user, $template, '2026-08', '2026-08-01', '2026-08-31', '2026-08-05', '65000.00',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentObligation::where('recurring_payment_template_id', $template->id)->count());
    }

    public function test_one_time_generation_is_idempotent_via_service(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $first = $this->obligations->createOneTime(
            $user, 'google-subscription-2026-08-11', $category, '2026-08-01', '2026-08-31', '2026-08-11', '1950.00',
        );

        $second = $this->obligations->createOneTime(
            $user, 'google-subscription-2026-08-11', $category, '2026-08-01', '2026-08-31', '2026-08-11', '1950.00',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentObligation::where('idempotency_key', 'google-subscription-2026-08-11')->count());
    }
}
```


---

## FILE: tests/Feature/Ownership/TenantIsolationTest.php

```php
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
```


---

## FILE: tests/Unit/Domain/MoneyTest.php

```php
<?php

namespace Tests\Unit\Domain;

use App\Domain\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_add_uses_exact_decimal_arithmetic(): void
    {
        $this->assertSame('19159.00', Money::add('10000.00', '9159.00'));
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
    }

    public function test_sub_uses_exact_decimal_arithmetic(): void
    {
        $this->assertSame('9159.00', Money::sub('19159.00', '10000.00'));
    }

    public function test_compare(): void
    {
        $this->assertSame(0, Money::compare('100.00', '100.00'));
        $this->assertSame(1, Money::compare('100.01', '100.00'));
        $this->assertSame(-1, Money::compare('99.99', '100.00'));
    }

    public function test_is_positive(): void
    {
        $this->assertTrue(Money::isPositive('0.01'));
        $this->assertFalse(Money::isPositive('0.00'));
        $this->assertFalse(Money::isPositive('-0.01'));
    }

    public function test_is_zero(): void
    {
        $this->assertTrue(Money::isZero('0.00'));
        $this->assertFalse(Money::isZero('0.01'));
    }

    public function test_is_greater_than(): void
    {
        $this->assertTrue(Money::isGreaterThan('19200.00', '19159.00'));
        $this->assertFalse(Money::isGreaterThan('19159.00', '19159.00'));
    }

    public function test_is_greater_than_or_equal(): void
    {
        $this->assertTrue(Money::isGreaterThanOrEqual('19159.00', '19159.00'));
        $this->assertTrue(Money::isGreaterThanOrEqual('19200.00', '19159.00'));
        $this->assertFalse(Money::isGreaterThanOrEqual('19158.99', '19159.00'));
    }
}
```


---

## FILE: tests/Unit/ExampleTest.php

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
}
```

