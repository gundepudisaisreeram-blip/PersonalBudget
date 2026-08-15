<?php

namespace Tests\Feature\Statements;

use App\Domain\Statements\Contracts\BankProfileContract;
use App\Domain\Statements\Dto\NormalizedStatement;
use App\Domain\Statements\Dto\NormalizedStatementRow;
use App\Domain\Statements\Exceptions\StatementParseException;
use App\Domain\Statements\ProfileRegistry;
use App\Models\Account;
use App\Models\StatementImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Statements\Concerns\BuildsStatementFixtures;
use Tests\TestCase;

/**
 * Ambiguous Match branch of the Hybrid profile-selection state machine
 * (PHASE_7_DECISION_PACKAGE.md section 5, Open Decision 11). The two
 * real, currently-supported profiles never collide with each other (one
 * is CSV, the other XLSX), so this scenario is exercised with a bound
 * ProfileRegistry carrying two deliberately-colliding CSV stub profiles
 * -- proving the *mechanism* the real profiles will share the moment a
 * second CSV bank profile is added, per the frozen architecture.
 */
class StatementProfileSelectionTest extends TestCase
{
    use BuildsStatementFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function bindAmbiguousRegistry(bool $secondProfileValidates = true): void
    {
        $this->app->instance(ProfileRegistry::class, new ProfileRegistry([
            $this->makeStubProfile('stub_alpha', 'Stub Bank Alpha', normalizes: true),
            $this->makeStubProfile('stub_beta', 'Stub Bank Beta', normalizes: $secondProfileValidates),
        ]));
    }

    public function test_multiple_matching_profiles_halt_automatic_parsing_and_prompt_selection(): void
    {
        $this->bindAmbiguousRegistry();
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);

        $import = StatementImport::query()->first();
        $response->assertRedirect(route('imports.show', $import));
        $this->assertSame(StatementImport::STATUS_UPLOADED, $import->status);
        $this->assertNull($import->parser_profile);

        $show = $this->actingAs($user)->get(route('imports.show', $import));
        $show->assertOk();
        $show->assertSee('Stub Bank Alpha');
        $show->assertSee('Stub Bank Beta');
    }

    public function test_explicit_profile_selection_validates_and_proceeds_to_queued(): void
    {
        $this->bindAmbiguousRegistry();
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);
        $import = StatementImport::query()->first();

        $response = $this->actingAs($user)->post(route('imports.select-profile', $import), [
            'parser_profile' => 'stub_alpha',
        ]);

        $response->assertRedirect(route('imports.show', $import));
        $import->refresh();
        $this->assertSame('stub_alpha', $import->parser_profile);
        $this->assertNotSame(StatementImport::STATUS_UPLOADED, $import->status);
    }

    public function test_a_selected_profile_that_fails_structural_validation_is_rejected(): void
    {
        $this->bindAmbiguousRegistry(secondProfileValidates: false);
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET']);

        $this->actingAs($user)->post(route('imports.store'), [
            'account_id' => $account->id,
            'file' => $this->uploadedCsv($this->kotakCsvContent()),
        ]);
        $import = StatementImport::query()->first();

        $response = $this->actingAs($user)->post(route('imports.select-profile', $import), [
            'parser_profile' => 'stub_beta',
        ]);

        $response->assertSessionHasErrors('parser_profile');
        $import->refresh();
        $this->assertSame(StatementImport::STATUS_FAILED, $import->status);
    }

    private function makeStubProfile(string $key, string $label, bool $normalizes): BankProfileContract
    {
        return new class($key, $label, $normalizes) implements BankProfileContract
        {
            public function __construct(
                private string $key,
                private string $label,
                private bool $normalizes,
            ) {}

            public function key(): string
            {
                return $this->key;
            }

            public function label(): string
            {
                return $this->label;
            }

            public function fileType(): string
            {
                return 'csv';
            }

            public function matches(array $rows): bool
            {
                return true;
            }

            public function normalize(array $rows): NormalizedStatement
            {
                if (! $this->normalizes) {
                    throw new StatementParseException('Stub profile intentionally fails structural validation.');
                }

                return new NormalizedStatement(rows: [
                    new NormalizedStatementRow(
                        transactionDate: '2026-08-01',
                        valueDate: null,
                        description: 'Stub row',
                        referenceNumber: null,
                        normalizedAmount: '10.00',
                        direction: 'OUTFLOW',
                        statementBalance: null,
                        rawData: [],
                    ),
                ]);
            }
        };
    }
}
