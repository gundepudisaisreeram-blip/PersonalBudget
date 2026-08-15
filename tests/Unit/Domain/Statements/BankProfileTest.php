<?php

namespace Tests\Unit\Domain\Statements;

use App\Domain\Statements\Contracts\BankProfileContract;
use App\Domain\Statements\Dto\NormalizedStatement;
use App\Domain\Statements\Exceptions\StatementParseException;
use App\Domain\Statements\ProfileRegistry;
use App\Domain\Statements\Profiles\IciciStyleXlsxProfile;
use App\Domain\Statements\Profiles\KotakCsvProfile;
use App\Domain\Statements\Readers\CsvFileReader;
use App\Domain\Statements\Readers\XlsxFileReader;
use Tests\Feature\Statements\Concerns\BuildsStatementFixtures;
use Tests\TestCase;

class BankProfileTest extends TestCase
{
    use BuildsStatementFixtures;

    private function writeTemp(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stmt');
        file_put_contents($path, $content);

        return $path;
    }

    // -----------------------------------------------------------------
    // Kotak CSV
    // -----------------------------------------------------------------

    public function test_kotak_profile_detects_via_ifsc_and_header_content(): void
    {
        $rows = (new CsvFileReader)->read($this->writeTemp($this->kotakCsvContent()));

        $this->assertTrue((new KotakCsvProfile)->matches($rows));
    }

    public function test_kotak_profile_ignores_a_misleading_filename(): void
    {
        // matches() never receives a filename at all -- content is the
        // only signal, per the frozen architectural rule.
        $rows = (new CsvFileReader)->read($this->writeTemp($this->kotakCsvContent()));

        $this->assertTrue((new KotakCsvProfile)->matches($rows));
    }

    public function test_kotak_profile_normalizes_dr_cr_and_two_digit_year_dates(): void
    {
        $rows = (new CsvFileReader)->read($this->writeTemp($this->kotakCsvContent()));

        $normalized = (new KotakCsvProfile)->normalize($rows);

        $this->assertInstanceOf(NormalizedStatement::class, $normalized);
        $this->assertCount(2, $normalized->rows);
        $this->assertSame('2026-07-30', $normalized->rows[0]->transactionDate);
        $this->assertSame('OUTFLOW', $normalized->rows[0]->direction);
        $this->assertSame('150.00', $normalized->rows[0]->normalizedAmount);
        $this->assertSame('REF100', $normalized->rows[0]->referenceNumber);
        $this->assertSame('INFLOW', $normalized->rows[1]->direction);
    }

    public function test_kotak_profile_does_not_match_unrelated_content(): void
    {
        $rows = [['Random', 'CSV', 'Content'], ['1', '2', '3']];

        $this->assertFalse((new KotakCsvProfile)->matches($rows));
    }

    // -----------------------------------------------------------------
    // ICICI-style XLSX (the structurally-verified "KVB"-named sample)
    // -----------------------------------------------------------------

    public function test_icici_style_profile_detects_via_content_never_filename(): void
    {
        $rows = (new XlsxFileReader)->read($this->writeTemp($this->iciciStyleXlsxContent()));

        // This exact content was found inside a file misleadingly named
        // "KVB_....xlsx" (section 4C) -- detection must still succeed
        // because matches() is never given the filename at all.
        $this->assertTrue((new IciciStyleXlsxProfile)->matches($rows));
    }

    public function test_icici_style_profile_normalizes_debit_credit_columns(): void
    {
        $rows = (new XlsxFileReader)->read($this->writeTemp($this->iciciStyleXlsxContent()));

        $normalized = (new IciciStyleXlsxProfile)->normalize($rows);

        $this->assertSame('OUTFLOW', $normalized->rows[0]->direction);
        $this->assertSame('150.00', $normalized->rows[0]->normalizedAmount);
        $this->assertSame('INFLOW', $normalized->rows[1]->direction);
        $this->assertSame('20000.00', $normalized->rows[1]->normalizedAmount);
    }

    public function test_icici_style_profile_rejects_rows_missing_required_columns(): void
    {
        $this->expectException(StatementParseException::class);

        (new IciciStyleXlsxProfile)->normalize([['Some', 'Other', 'Header']]);
    }

    // -----------------------------------------------------------------
    // ProfileRegistry -- Hybrid Selection State Machine (Open Decision 11)
    // -----------------------------------------------------------------

    public function test_registry_auto_selects_the_single_matching_profile(): void
    {
        $rows = (new CsvFileReader)->read($this->writeTemp($this->kotakCsvContent()));

        $matches = (new ProfileRegistry)->detect('csv', $rows);

        $this->assertCount(1, $matches);
        $this->assertSame('kotak_csv_v1', $matches[0]->key());
    }

    public function test_registry_returns_zero_matches_for_unrecognized_content(): void
    {
        $matches = (new ProfileRegistry)->detect('csv', [['nothing', 'recognizable']]);

        $this->assertSame([], $matches);
    }

    public function test_registry_returns_every_candidate_when_multiple_profiles_match(): void
    {
        $stubA = $this->makeStubProfile('stub_a', 'csv', matches: true, label: 'Stub Bank A');
        $stubB = $this->makeStubProfile('stub_b', 'csv', matches: true, label: 'Stub Bank B');

        $registry = new ProfileRegistry([$stubA, $stubB]);

        $matches = $registry->detect('csv', [['ambiguous', 'content']]);

        $this->assertCount(2, $matches);
        $this->assertSame(['stub_a', 'stub_b'], array_map(fn ($p) => $p->key(), $matches));
    }

    public function test_registry_find_resolves_a_profile_by_key(): void
    {
        $profile = (new ProfileRegistry)->find('kotak_csv_v1');

        $this->assertInstanceOf(KotakCsvProfile::class, $profile);
        $this->assertNull((new ProfileRegistry)->find('does_not_exist'));
    }

    private function makeStubProfile(string $key, string $fileType, bool $matches, string $label): BankProfileContract
    {
        return new class($key, $fileType, $matches, $label) implements BankProfileContract
        {
            public function __construct(
                private string $key,
                private string $fileType,
                private bool $doesMatch,
                private string $label,
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
                return $this->fileType;
            }

            public function matches(array $rows): bool
            {
                return $this->doesMatch;
            }

            public function normalize(array $rows): NormalizedStatement
            {
                return new NormalizedStatement(rows: []);
            }
        };
    }
}
