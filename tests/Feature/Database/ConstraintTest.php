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
