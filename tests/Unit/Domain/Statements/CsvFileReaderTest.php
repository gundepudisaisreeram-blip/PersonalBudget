<?php

namespace Tests\Unit\Domain\Statements;

use App\Domain\Statements\Exceptions\StatementParseException;
use App\Domain\Statements\Readers\CsvFileReader;
use Tests\TestCase;

class CsvFileReaderTest extends TestCase
{
    private function writeTemp(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);

        return $path;
    }

    public function test_reads_quoted_fields_with_embedded_commas(): void
    {
        $path = $this->writeTemp("Header1,Header2\r\n\"Smith, John\",100\r\n");

        $rows = (new CsvFileReader)->read($path);

        $this->assertSame(['Header1', 'Header2'], $rows[0]);
        $this->assertSame(['Smith, John', '100'], $rows[1]);
    }

    public function test_strips_a_leading_utf8_bom(): void
    {
        $path = $this->writeTemp("\xEF\xBB\xBFHeader1,Header2\r\nA,B\r\n");

        $rows = (new CsvFileReader)->read($path);

        $this->assertSame('Header1', $rows[0][0]);
    }

    public function test_rejects_a_file_with_no_readable_rows(): void
    {
        $path = $this->writeTemp('');

        $this->expectException(StatementParseException::class);

        (new CsvFileReader)->read($path);
    }

    public function test_enforces_the_maximum_row_count(): void
    {
        $lines = ['Header'];
        for ($i = 0; $i <= CsvFileReader::MAX_ROWS; $i++) {
            $lines[] = "row{$i}";
        }
        $path = $this->writeTemp(implode("\r\n", $lines));

        $this->expectException(StatementParseException::class);

        (new CsvFileReader)->read($path);
    }

    public function test_a_row_at_exactly_the_maximum_is_accepted(): void
    {
        $lines = [];
        for ($i = 0; $i < CsvFileReader::MAX_ROWS; $i++) {
            $lines[] = "row{$i}";
        }
        $path = $this->writeTemp(implode("\r\n", $lines));

        $rows = (new CsvFileReader)->read($path);

        $this->assertCount(CsvFileReader::MAX_ROWS, $rows);
    }
}
