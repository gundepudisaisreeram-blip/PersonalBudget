<?php

namespace Tests\Unit\Domain\Statements;

use App\Domain\Statements\Exceptions\StatementParseException;
use App\Domain\Statements\Readers\XlsxFileReader;
use Tests\Feature\Statements\Concerns\BuildsStatementFixtures;
use Tests\TestCase;
use ZipArchive;

/**
 * PHASE_7_DECISION_PACKAGE.md section 10 -- XLSX Security Implementation
 * Invariants, and section 15's Adversarial Test Matrix XLSX rows.
 */
class XlsxFileReaderTest extends TestCase
{
    use BuildsStatementFixtures;

    private function writeTemp(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $bytes);

        return $path;
    }

    public function test_reads_a_valid_minimal_workbook(): void
    {
        $bytes = $this->buildXlsx([
            ['Transaction Date', 'Particulars', 'Debit', 'Credit'],
            ['01-08-2026', 'Coffee', '150.00', '0'],
        ]);

        $rows = (new XlsxFileReader)->read($this->writeTemp($bytes));

        $this->assertSame('Transaction Date', $rows[0][0]);
        $this->assertSame('Coffee', $rows[1][1]);
    }

    public function test_rejects_a_corrupt_non_zip_file(): void
    {
        $this->expectException(StatementParseException::class);

        (new XlsxFileReader)->read($this->writeTemp('not a zip archive'));
    }

    public function test_rejects_a_workbook_with_no_visible_worksheet(): void
    {
        $bytes = $this->buildXlsx([['A']], hiddenExtraSheet: false);

        // Rebuild with the primary sheet itself marked hidden and no other sheet.
        $path = $this->writeTemp($bytes);
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data" sheetId="1" r:id="rId1" state="hidden"/></sheets></workbook>');
        $zip->close();

        $this->expectException(StatementParseException::class);

        (new XlsxFileReader)->read($path);
    }

    public function test_ignores_a_hidden_sheet_and_reads_the_visible_one(): void
    {
        $bytes = $this->buildXlsx([
            ['Transaction Date', 'Particulars', 'Debit', 'Credit'],
            ['01-08-2026', 'Coffee', '150.00', '0'],
        ], hiddenExtraSheet: true);

        $rows = (new XlsxFileReader)->read($this->writeTemp($bytes));

        $this->assertCount(2, $rows);
        $this->assertSame('Transaction Date', $rows[0][0]);
    }

    public function test_never_reads_macro_project_binary(): void
    {
        $bytes = $this->buildXlsx([
            ['Transaction Date', 'Particulars', 'Debit', 'Credit'],
            ['01-08-2026', 'Coffee', '150.00', '0'],
        ], withVba: true);

        // The reader must succeed and return normal rows -- it never
        // touches xl/vbaProject.bin at all, so macro presence is inert.
        $rows = (new XlsxFileReader)->read($this->writeTemp($bytes));

        $this->assertCount(2, $rows);
    }

    public function test_rejects_a_password_protected_workbook(): void
    {
        // A genuinely encrypted OOXML package is an OLE/CFBF container,
        // not a ZIP -- ZipArchive::open() fails identically to a corrupt file.
        $bytes = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat('X', 200);

        $this->expectException(StatementParseException::class);

        (new XlsxFileReader)->read($this->writeTemp($bytes));
    }

    public function test_rejects_a_decompression_bomb(): void
    {
        $path = $this->writeTemp('');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', str_repeat('A', 30 * 1024 * 1024));
        $zip->setCompressionName('xl/workbook.xml', ZipArchive::CM_DEFLATE, 9);
        $zip->close();

        $this->expectException(StatementParseException::class);

        (new XlsxFileReader)->read($path);
    }

    public function test_rejects_implausibly_large_declared_worksheet_dimensions(): void
    {
        $path = $this->writeTemp('');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:XFD1048576"/><sheetData><row r="1"><c r="A1"><v>1</v></c></row></sheetData></worksheet>');
        $zip->close();

        $this->expectException(StatementParseException::class);

        (new XlsxFileReader)->read($path);
    }

    public function test_never_evaluates_a_formula_cell_only_reads_its_cached_value(): void
    {
        $path = $this->writeTemp('');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        // Cell A1 has a formula but a cached value of 42 -- the reader must
        // return "42", never attempt to execute SUM(1:1000000).
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1"><f>SUM(1:1000000)</f><v>42</v></c></row></sheetData></worksheet>');
        $zip->close();

        $rows = (new XlsxFileReader)->read($path);

        $this->assertSame('42', $rows[0][0]);
    }

    public function test_enforces_the_maximum_row_count(): void
    {
        $rows = [['Transaction Date', 'Particulars', 'Debit', 'Credit']];
        for ($i = 0; $i <= XlsxFileReader::MAX_ROWS; $i++) {
            $rows[] = ["0{$i}-08-2026", 'Row', '1.00', '0'];
        }

        $this->expectException(StatementParseException::class);

        (new XlsxFileReader)->read($this->writeTemp($this->buildXlsx($rows)));
    }
}
