<?php

namespace Tests\Feature\Statements;

use App\Models\Account;
use App\Models\StatementImport;
use App\Models\StatementTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PHASE_7_DECISION_PACKAGE.md section 12 / 22 -- database gap analysis
 * and migration constraints. Indexes on row_fingerprint/reference_number
 * must be non-unique; only (account_id, file_hash) is unique.
 */
class StatementSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_id_file_hash_is_a_unique_constraint(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        StatementImport::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'parser_profile' => 'kotak_csv_v1',
            'original_filename' => 'a.csv',
            'storage_path' => 'a.csv',
            'file_hash' => 'same-hash',
            'file_type' => 'csv',
            'status' => StatementImport::STATUS_UPLOADED,
        ]);

        $this->expectException(QueryException::class);

        StatementImport::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'parser_profile' => 'kotak_csv_v1',
            'original_filename' => 'b.csv',
            'storage_path' => 'b.csv',
            'file_hash' => 'same-hash',
            'file_type' => 'csv',
            'status' => StatementImport::STATUS_UPLOADED,
        ]);
    }

    public function test_the_same_file_hash_is_permitted_for_a_different_account(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        $accountB = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        StatementImport::create([
            'user_id' => $user->id, 'account_id' => $accountA->id, 'parser_profile' => 'kotak_csv_v1',
            'original_filename' => 'a.csv', 'storage_path' => 'a.csv', 'file_hash' => 'shared-hash',
            'file_type' => 'csv', 'status' => StatementImport::STATUS_UPLOADED,
        ]);

        $second = StatementImport::create([
            'user_id' => $user->id, 'account_id' => $accountB->id, 'parser_profile' => 'kotak_csv_v1',
            'original_filename' => 'b.csv', 'storage_path' => 'b.csv', 'file_hash' => 'shared-hash',
            'file_type' => 'csv', 'status' => StatementImport::STATUS_UPLOADED,
        ]);

        $this->assertNotNull($second->id);
    }

    public function test_two_valid_rows_with_an_identical_fallback_fingerprint_can_coexist(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        $import = StatementImport::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'parser_profile' => 'kotak_csv_v1',
            'original_filename' => 'a.csv', 'storage_path' => 'a.csv', 'file_hash' => 'h1',
            'file_type' => 'csv', 'status' => StatementImport::STATUS_UPLOADED,
        ]);

        $row = fn () => [
            'user_id' => $user->id,
            'statement_import_id' => $import->id,
            'account_id' => $account->id,
            'transaction_date' => '2026-08-01',
            'description' => 'Coffee',
            'normalized_amount' => '5.00',
            'direction' => 'OUTFLOW',
            'row_fingerprint' => 'identical-fingerprint',
            'processing_status' => 'STAGED',
            'categorization_status' => 'UNCATEGORIZED',
            'match_status' => 'UNMATCHED',
            'duplicate_status' => StatementTransaction::DUPLICATE_STATUS_VALID,
        ];

        StatementTransaction::create($row());
        $second = StatementTransaction::create($row());

        $this->assertNotNull($second->id);
        $this->assertSame(2, StatementTransaction::query()->where('row_fingerprint', 'identical-fingerprint')->count());
    }

    public function test_two_rows_with_an_identical_reference_number_can_coexist_at_the_schema_level(): void
    {
        // The database itself does not reject this (section 12: "database
        // indexes are NOT uniqueness constraints") -- confirmed-duplicate
        // rejection is enforced by ParseStatementImportJob's application
        // logic, not a database constraint, so legitimate historical data
        // is never at risk of a schema-level rejection.
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        $import = StatementImport::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'parser_profile' => 'kotak_csv_v1',
            'original_filename' => 'a.csv', 'storage_path' => 'a.csv', 'file_hash' => 'h2',
            'file_type' => 'csv', 'status' => StatementImport::STATUS_UPLOADED,
        ]);

        $row = fn (string $fingerprint) => [
            'user_id' => $user->id,
            'statement_import_id' => $import->id,
            'account_id' => $account->id,
            'transaction_date' => '2026-08-01',
            'description' => 'Coffee',
            'reference_number' => 'DUPLICATE-REF',
            'normalized_amount' => '5.00',
            'direction' => 'OUTFLOW',
            'row_fingerprint' => $fingerprint,
            'processing_status' => 'STAGED',
            'categorization_status' => 'UNCATEGORIZED',
            'match_status' => 'UNMATCHED',
            'duplicate_status' => StatementTransaction::DUPLICATE_STATUS_VALID,
        ];

        StatementTransaction::create($row('fp1'));
        $second = StatementTransaction::create($row('fp2'));

        $this->assertNotNull($second->id);
    }

    public function test_row_fingerprint_and_reference_number_indexes_exist_and_are_not_unique(): void
    {
        $indexes = Schema::getIndexes('statement_transactions');
        $byName = collect($indexes)->keyBy('name');

        $this->assertTrue($byName->has('statement_transactions_account_id_row_fingerprint_index'));
        $this->assertFalse($byName->get('statement_transactions_account_id_row_fingerprint_index')['unique']);

        $this->assertTrue($byName->has('statement_transactions_account_id_reference_number_index'));
        $this->assertFalse($byName->get('statement_transactions_account_id_reference_number_index')['unique']);
    }

    public function test_account_id_file_hash_index_is_unique(): void
    {
        $indexes = Schema::getIndexes('statement_imports');
        $byName = collect($indexes)->keyBy('name');

        $this->assertTrue($byName->has('statement_imports_account_id_file_hash_unique'));
        $this->assertTrue($byName->get('statement_imports_account_id_file_hash_unique')['unique']);
    }
}
