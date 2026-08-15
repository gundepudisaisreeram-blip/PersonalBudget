<?php

namespace Tests\Feature\Statements;

use App\Models\Account;
use App\Models\StatementImport;
use App\Models\StatementTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Statements\Concerns\BuildsStatementFixtures;
use Tests\TestCase;

/**
 * Duplicate / Identity Contract (PHASE_7_DECISION_PACKAGE.md section 6,
 * Open Decision 5) and the Strict/All-or-Nothing error model (section 9,
 * Open Decision 3).
 */
class StatementDuplicateTest extends TestCase
{
    use BuildsStatementFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function upload(User $user, Account $account, array $rows, string $filename = 'statement.csv'): StatementImport
    {
        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent($rows), $filename),
        ]);

        return StatementImport::query()->latest('id')->first();
    }

    public function test_two_legitimate_identical_transactions_in_the_same_file_are_both_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $rows = [
            ['1', '01-08-26', '01-08-26', 'Coffee', '', '5.00', 'DR', '995.00'],
            ['2', '01-08-26', '01-08-26', 'Coffee', '', '5.00', 'DR', '990.00'],
        ];
        $import = $this->upload($user, $account, $rows);

        $this->assertSame(StatementImport::STATUS_PREVIEW_READY, $import->status);
        $this->assertSame(2, $import->statementTransactions()->count());
        $this->assertSame(2, $import->statementTransactions()->where('duplicate_status', StatementTransaction::DUPLICATE_STATUS_VALID)->count());
    }

    public function test_same_primary_reference_across_imports_is_a_confirmed_duplicate_and_fails_the_batch(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Rent', 'REF500', '1000.00', 'DR', '9000.00'],
        ], 'first.csv');

        // The Balance column differs between the two files (irrelevant to
        // both the primary-reference match and the fallback fingerprint)
        // purely so the two uploads are not byte-for-byte identical files
        // -- an identical file would instead be rejected earlier by the
        // (account_id, file_hash) exact-replay check (section 16), which
        // is a distinct scenario covered by StatementUploadTest.
        $second = $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Rent', 'REF500', '1000.00', 'DR', '8000.00'],
        ], 'second.csv');

        $this->assertSame(StatementImport::STATUS_FAILED, $second->status);
        $this->assertNotEmpty($second->error_payload['details'] ?? null);
        $this->assertSame(0, $second->statementTransactions()->count());
        $this->assertSame(1, StatementTransaction::query()->count());
    }

    public function test_same_primary_reference_with_conflicting_data_fails_the_batch_with_conflict_detail(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Rent', 'REF700', '100.00', 'DR', '9900.00'],
        ], 'first.csv');

        $second = $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Rent', 'REF700', '200.00', 'DR', '9800.00'],
        ], 'second.csv');

        $this->assertSame(StatementImport::STATUS_FAILED, $second->status);
        $this->assertStringContainsString('conflict', strtolower($second->error_payload['details'][0]));
    }

    public function test_fallback_fingerprint_collision_alone_is_a_candidate_not_a_failure(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Groceries', '', '50.00', 'DR', '950.00'],
        ], 'first.csv');

        // Balance differs so the two files are not byte-identical (which
        // would instead hit the exact-file-replay rejection).
        $second = $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Groceries', '', '50.00', 'DR', '900.00'],
        ], 'second.csv');

        $this->assertSame(StatementImport::STATUS_PREVIEW_READY, $second->status);
        $this->assertSame(1, $second->statementTransactions()->count());
        $this->assertSame(
            StatementTransaction::DUPLICATE_STATUS_CANDIDATE,
            $second->statementTransactions()->first()->duplicate_status
        );
    }

    public function test_different_references_with_identical_fallback_fingerprint_are_both_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Groceries', 'REF-A', '50.00', 'DR', '950.00'],
        ], 'first.csv');

        $second = $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Groceries', 'REF-B', '50.00', 'DR', '950.00'],
        ], 'second.csv');

        $this->assertSame(StatementImport::STATUS_PREVIEW_READY, $second->status);
        $this->assertSame(2, StatementTransaction::query()->count());
    }

    public function test_no_primary_reference_present_is_never_automatically_rejected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $import = $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Cash withdrawal', '', '300.00', 'DR', '700.00'],
        ]);

        $this->assertSame(StatementImport::STATUS_PREVIEW_READY, $import->status);
    }

    public function test_duplicate_candidate_row_remains_staged_after_confirmation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Groceries', '', '50.00', 'DR', '950.00'],
        ], 'first.csv');
        $second = $this->upload($user, $account, [
            ['1', '01-08-26', '01-08-26', 'Groceries', '', '50.00', 'DR', '900.00'],
        ], 'second.csv');

        $this->actingAs($user)->post(route('imports.confirm', $second));

        $second->refresh();
        $this->assertSame(StatementImport::STATUS_COMPLETED, $second->status);
        $this->assertSame(1, $second->statementTransactions()->count());
        $this->assertSame(
            StatementTransaction::DUPLICATE_STATUS_CANDIDATE,
            $second->statementTransactions()->first()->duplicate_status
        );
    }
}
