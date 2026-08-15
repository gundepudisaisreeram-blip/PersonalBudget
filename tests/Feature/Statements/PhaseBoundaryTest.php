<?php

namespace Tests\Feature\Statements;

use App\Domain\Services\AccountBalanceService;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\ReconciliationMatch;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Statements\Concerns\BuildsStatementFixtures;
use Tests\TestCase;

/**
 * The absolute Phase Boundary (PHASE_7_DECISION_PACKAGE.md section 2/8):
 * Phase 7 writes only to the statement staging domain. Transaction and
 * LedgerEntry counts must be identical before and after every import
 * lifecycle stage, and zero reconciliation matches may ever be created.
 */
class PhaseBoundaryTest extends TestCase
{
    use BuildsStatementFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_transaction_and_ledger_entry_counts_are_unchanged_by_a_full_import_lifecycle(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);

        $transactionsBefore = Transaction::query()->count();
        $ledgerEntriesBefore = LedgerEntry::query()->count();

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);
        $import = StatementImport::query()->first();
        $this->assertSame(StatementImport::STATUS_PREVIEW_READY, $import->status);

        $this->assertSame($transactionsBefore, Transaction::query()->count());
        $this->assertSame($ledgerEntriesBefore, LedgerEntry::query()->count());

        $this->actingAs($user)->post(route('imports.confirm', $import));

        $this->assertSame($transactionsBefore, Transaction::query()->count());
        $this->assertSame($ledgerEntriesBefore, LedgerEntry::query()->count());
    }

    public function test_zero_reconciliation_matches_are_ever_created_by_an_import(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);
        $import = StatementImport::query()->first();
        $this->actingAs($user)->post(route('imports.confirm', $import));

        $this->assertSame(0, ReconciliationMatch::query()->count());
    }

    public function test_the_account_current_balance_is_unaffected_by_an_import(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);
        $balanceService = app(AccountBalanceService::class);
        $before = $balanceService->calculate($account);

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);
        $import = StatementImport::query()->first();
        $this->actingAs($user)->post(route('imports.confirm', $import));

        $this->assertSame($before, $balanceService->calculate($account->fresh()));
    }
}
