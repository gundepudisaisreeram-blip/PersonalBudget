<?php

namespace Tests\Feature\Statements;

use App\Domain\Statements\Support\CsvFormulaGuard;
use App\Models\Account;
use App\Models\StatementImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Statements\Concerns\BuildsStatementFixtures;
use Tests\TestCase;

/**
 * PHASE_7_DECISION_PACKAGE.md section 15 -- adversarial scenarios not
 * already covered by StatementUploadTest / StatementOwnershipTest.
 */
class StatementAdversarialTest extends TestCase
{
    use BuildsStatementFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_php_script_renamed_to_csv_is_rejected_as_unrecognized_content(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv("<?php system(\$_GET['cmd']); ?>\n", 'spoofed.csv'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, StatementImport::query()->count());
    }

    public function test_a_path_traversal_filename_never_influences_the_generated_storage_path(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent(), '../../../../etc/passwd.csv'),
        ]);

        $import = StatementImport::query()->first();

        $this->assertStringNotContainsString('..', $import->storage_path);
        $this->assertStringNotContainsString('etc/passwd', $import->storage_path);
        $this->assertStringStartsWith("statements/{$user->id}/{$account->id}/", $import->storage_path);
        // The storage path is always application-generated (UUID-based),
        // regardless of what the client claims as the original filename --
        // Symfony's UploadedFile additionally strips any path component
        // from getClientOriginalName() itself, so the traversal segments
        // never even reach original_filename.
        $this->assertSame('passwd.csv', $import->original_filename);
    }

    public function test_raw_files_are_never_publicly_downloadable(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);
        $import = StatementImport::query()->first();

        // Guest access to the download route is rejected -- authentication
        // is always required, and the file was never written to the
        // public disk at all.
        $this->post(route('logout'));
        $this->assertGuest();
        $this->get(route('imports.download', $import))->assertRedirect('/login');
        Storage::disk('public')->assertMissing($import->storage_path);
    }

    public function test_csv_formula_injection_prefixes_are_neutralized_in_the_rendered_preview(): void
    {
        $this->assertSame("'=CMD|' /C calc'!A0", CsvFormulaGuard::sanitize("=CMD|' /C calc'!A0"));
        $this->assertSame("'+1+1", CsvFormulaGuard::sanitize('+1+1'));
        $this->assertSame("'-1-1", CsvFormulaGuard::sanitize('-1-1'));
        $this->assertSame("'@SUM(1)", CsvFormulaGuard::sanitize('@SUM(1)'));
        $this->assertSame('Ordinary text', CsvFormulaGuard::sanitize('Ordinary text'));
        $this->assertNull(CsvFormulaGuard::sanitize(null));
    }

    public function test_a_malformed_csv_row_count_mismatch_does_not_crash_with_a_500(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $malformed = "IFSC,KKBK0007462\r\nSl. No.,Transaction Date,Value Date,Description,Chq /Ref No.,Amount,Dr / Cr,Balance\r\n1,\"unterminated quote,01-08-26\r\n";

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($malformed),
        ]);

        $response->assertStatus(302);
    }
}
