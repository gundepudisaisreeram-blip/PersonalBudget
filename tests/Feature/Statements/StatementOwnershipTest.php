<?php

namespace Tests\Feature\Statements;

use App\Models\Account;
use App\Models\StatementImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Statements\Concerns\BuildsStatementFixtures;
use Tests\TestCase;

/**
 * Tenant Isolation (PHASE_7_DECISION_PACKAGE.md section 11).
 */
class StatementOwnershipTest extends TestCase
{
    use BuildsStatementFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_guest_is_redirected_to_login_from_every_import_route(): void
    {
        $this->get(route('imports.index'))->assertRedirect('/login');
        $this->get(route('imports.create'))->assertRedirect('/login');
    }

    public function test_uploading_to_a_foreign_account_id_is_rejected(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $foreignAccount = Account::factory()->for($owner)->create(['account_type' => 'ASSET']);

        $response = $this->actingAs($attacker)->post(route('imports.store'), [
            'account_id' => $foreignAccount->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, StatementImport::query()->count());
    }

    public function test_a_user_cannot_view_another_users_import_batch(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create(['account_type' => 'ASSET']);

        $this->actingAs($owner)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);
        $import = StatementImport::query()->first();

        $response = $this->actingAs($attacker)->get(route('imports.show', $import));

        $response->assertForbidden();
    }

    public function test_a_user_cannot_download_another_users_raw_statement_file(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create(['account_type' => 'ASSET']);

        $this->actingAs($owner)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);
        $import = StatementImport::query()->first();

        $response = $this->actingAs($attacker)->get(route('imports.download', $import));

        $response->assertForbidden();
    }

    public function test_a_user_cannot_select_a_profile_for_another_users_import(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create(['account_type' => 'ASSET']);
        $import = StatementImport::create([
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'parser_profile' => null,
            'original_filename' => 'x.csv',
            'storage_path' => 'x.csv',
            'file_hash' => 'hash',
            'file_type' => 'csv',
            'status' => StatementImport::STATUS_UPLOADED,
        ]);

        $response = $this->actingAs($attacker)->post(route('imports.select-profile', $import), [
            'parser_profile' => 'kotak_csv_v1',
        ]);

        $response->assertForbidden();
    }

    public function test_a_user_cannot_confirm_another_users_import(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create(['account_type' => 'ASSET']);
        $import = StatementImport::create([
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'parser_profile' => 'kotak_csv_v1',
            'original_filename' => 'x.csv',
            'storage_path' => 'x.csv',
            'file_hash' => 'hash',
            'file_type' => 'csv',
            'status' => StatementImport::STATUS_PREVIEW_READY,
        ]);

        $response = $this->actingAs($attacker)->post(route('imports.confirm', $import));

        $response->assertForbidden();
    }

    public function test_import_history_only_lists_the_authenticated_users_own_imports(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create(['account_type' => 'ASSET']);
        $otherAccount = Account::factory()->for($other)->create(['account_type' => 'ASSET']);

        $this->actingAs($owner)->post(route('imports.store'), [
            'account_id' => $ownerAccount->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent(), 'owner.csv'),
        ]);
        $this->actingAs($other)->post(route('imports.store'), [
            'account_id' => $otherAccount->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent(), 'other.csv'),
        ]);

        $response = $this->actingAs($owner)->get(route('imports.index'));

        $response->assertOk();
        $response->assertSee('owner.csv');
        $response->assertDontSee('other.csv');
    }
}
