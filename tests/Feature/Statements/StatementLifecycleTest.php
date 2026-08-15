<?php

namespace Tests\Feature\Statements;

use App\Models\Account;
use App\Models\StatementImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Statements\Concerns\BuildsStatementFixtures;
use Tests\TestCase;

/**
 * Full import lifecycle (PHASE_7_DECISION_PACKAGE.md section 8) and the
 * immutability / no-V1-deletion contract (section 7).
 */
class StatementLifecycleTest extends TestCase
{
    use BuildsStatementFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_full_lifecycle_from_upload_through_confirmation_to_completed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);
        $import = StatementImport::query()->first();
        $this->assertSame(StatementImport::STATUS_PREVIEW_READY, $import->status);

        $response = $this->actingAs($user)->post(route('imports.confirm', $import));
        $response->assertRedirect(route('imports.show', $import));

        $import->refresh();
        $this->assertSame(StatementImport::STATUS_COMPLETED, $import->status);
    }

    public function test_confirmation_is_rejected_when_the_import_is_not_preview_ready(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        $import = StatementImport::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'parser_profile' => 'kotak_csv_v1',
            'original_filename' => 'x.csv',
            'storage_path' => 'x.csv',
            'file_hash' => 'hash',
            'file_type' => 'csv',
            'status' => StatementImport::STATUS_QUEUED,
        ]);

        $response = $this->actingAs($user)->post(route('imports.confirm', $import));

        $response->assertSessionHasErrors('import');
        $this->assertSame(StatementImport::STATUS_QUEUED, $import->fresh()->status);
    }

    public function test_a_failed_import_directs_the_user_to_upload_a_corrected_file_as_a_new_import(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent([
                ['1', '01-08-26', '01-08-26', 'Rent', 'REFX', '100.00', 'DR', '900.00'],
            ])),
        ]);

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent([
                ['1', '01-08-26', '01-08-26', 'Rent', 'REFX', '999.00', 'DR', '1.00'],
            ]), 'corrected.csv'),
        ]);

        $failed = StatementImport::query()->where('status', StatementImport::STATUS_FAILED)->first();
        $response = $this->actingAs($user)->get(route('imports.show', $failed));

        $response->assertOk();
        $response->assertSee('Upload a corrected file');
        $this->assertSame(2, StatementImport::query()->count());
    }

    public function test_no_edit_route_exists_for_a_statement_import_or_its_rows(): void
    {
        $this->assertFalse(Route::has('imports.update'));
        $this->assertFalse(Route::has('imports.edit'));
        $this->assertFalse(Route::has('imports.transactions.update'));
    }

    public function test_no_delete_route_exists_for_a_statement_import_or_its_raw_file(): void
    {
        $this->assertFalse(Route::has('imports.destroy'));
        $this->assertFalse(Route::has('imports.delete'));
    }

    public function test_no_delete_ui_is_rendered_on_the_import_show_page(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);
        $import = StatementImport::query()->first();

        $response = $this->actingAs($user)->get(route('imports.show', $import));

        $response->assertOk();
        $response->assertDontSee('Delete');
    }
}
