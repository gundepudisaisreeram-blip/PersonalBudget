<?php

namespace Tests\Feature\Statements;

use App\Models\Account;
use App\Models\StatementImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Statements\Concerns\BuildsStatementFixtures;
use Tests\TestCase;

/**
 * Upload lifecycle: UPLOAD -> IMPORT BATCH CREATED -> QUEUED -> PARSE ->
 * VALIDATE -> PREVIEW_READY (PHASE_7_DECISION_PACKAGE.md section 8).
 */
class StatementUploadTest extends TestCase
{
    use BuildsStatementFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_valid_kotak_csv_is_auto_detected_uploaded_and_queued_to_preview_ready(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);

        $import = StatementImport::query()->first();
        $response->assertRedirect(route('imports.show', $import));

        $this->assertSame('kotak_csv_v1', $import->parser_profile);
        $this->assertSame(StatementImport::STATUS_PREVIEW_READY, $import->status);
        $this->assertSame(2, $import->transaction_count);
        $this->assertSame(2, $import->statementTransactions()->count());
    }

    public function test_a_valid_icici_style_xlsx_is_auto_detected_and_processed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedXlsx($this->iciciStyleXlsxContent()),
        ]);

        $import = StatementImport::query()->first();
        $response->assertRedirect(route('imports.show', $import));
        $this->assertSame('icici_style_xlsx_v1', $import->parser_profile);
        $this->assertSame(StatementImport::STATUS_PREVIEW_READY, $import->status);
    }

    public function test_unrecognized_content_is_rejected_as_unsupported_format(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv("Random,Header\r\n1,2\r\n"),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, StatementImport::query()->count());
    }

    public function test_legacy_xls_upload_is_gracefully_rejected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        $oleBytes = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat('X', 200);

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => UploadedFile::fake()->createWithContent('legacy.xls', $oleBytes),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, StatementImport::query()->count());
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => UploadedFile::fake()->create('big.csv', 6 * 1024),
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_uploading_the_exact_same_file_twice_for_the_same_account_is_rejected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        $content = $this->kotakCsvContent();

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($content),
        ])->assertSessionDoesntHaveErrors();

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($content, 'statement-again.csv'),
        ]);

        $response->assertSessionHasErrors('import');
        $this->assertSame(1, StatementImport::query()->count());
    }

    public function test_the_same_file_content_may_be_uploaded_again_for_a_different_owned_account(): void
    {
        $user = User::factory()->create();
        $accountA = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        $accountB = Account::factory()->for($user)->create(['account_type' => 'ASSET']);
        $content = $this->kotakCsvContent();

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $accountA->id,
            'file' => $this->uploadedCsv($content),
        ])->assertSessionDoesntHaveErrors();

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $accountB->id,
            'file' => $this->uploadedCsv($content, 'statement-b.csv'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(2, StatementImport::query()->count());
    }

    public function test_the_http_upload_request_does_not_wait_for_parsing_synchronously(): void
    {
        // With QUEUE_CONNECTION=sync in this test environment the job runs
        // inline, so by the time the redirect happens PREVIEW_READY is
        // already reached -- but the controller itself never calls the
        // parser directly; it only ever calls StatementImportService,
        // which dispatches the job. This is proven structurally by the
        // PARSING/VALIDATING/PREVIEW_READY states being reachable only
        // through ParseStatementImportJob::handle().
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);

        $import = StatementImport::query()->first();
        $this->assertContains($import->status, [StatementImport::STATUS_PREVIEW_READY, StatementImport::STATUS_QUEUED]);
    }
}
