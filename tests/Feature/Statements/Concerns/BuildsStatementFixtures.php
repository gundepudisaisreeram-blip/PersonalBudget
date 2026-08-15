<?php

namespace Tests\Feature\Statements\Concerns;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Hand-builds minimal, valid CSV/XLSX fixtures matching the two real,
 * verified bank formats in PHASE_7_DECISION_PACKAGE.md section 4 (Kotak
 * CSV, ICICI-style XLSX), plus raw ZIP/XML builders for the adversarial
 * XLSX-security test scenarios. No third-party spreadsheet library is
 * used anywhere in this repository, so fixtures are built with the same
 * primitives (ZipArchive, hand-written OOXML) the production reader uses.
 */
trait BuildsStatementFixtures
{
    protected function kotakCsvContent(array $rows = []): string
    {
        if ($rows === []) {
            $rows = [
                ['1', '30-07-26 21:44', '30-07-26', 'Coffee Shop', 'REF100', '150.00', 'DR', '9850.00'],
                ['2', '31-07-26 10:00', '31-07-26', 'Salary Credit', 'REF101', '20000.00', 'CR', '29850.00'],
            ];
        }

        $lines = [
            'Statement for Account,,,,,,,',
            'Account Holder,John Doe,,,,,,',
            'IFSC,KKBK0007462,,,,,,',
            'Sl. No.,Transaction Date,Value Date,Description,Chq /Ref No.,Amount,Dr / Cr,Balance',
        ];

        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        return implode("\r\n", $lines)."\r\n";
    }

    protected function uploadedCsv(string $content, string $filename = 'statement.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>  $rows
     *                                                                                                                [transactionDate, valueDate, particulars, reference, debit, credit, balance]
     */
    protected function iciciStyleXlsxContent(array $rows = []): string
    {
        if ($rows === []) {
            $rows = [
                ['01-08-2026', '01-08-2026', 'Coffee shop', 'REF001', '150.00', '0', '9850.00'],
                ['02-08-2026', '02-08-2026', 'Salary credit', 'REF002', '0', '20000.00', '29850.00'],
            ];
        }

        $header = ['Transaction Date', 'Value Date', 'Particulars', 'Reference', 'Debit', 'Credit', 'Balance'];

        return $this->buildXlsx(array_merge([$header], $rows));
    }

    protected function uploadedXlsx(string $bytes, string $filename = 'statement.xlsx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $bytes);
    }

    /**
     * Builds a minimal, valid XLSX byte string from a plain array-of-rows
     * grid of string cell values (all written as shared strings, for
     * simplicity -- the reader resolves numeric-looking shared strings
     * identically to inline values).
     *
     * @param  array<int, array<int, string>>  $rows
     */
    protected function buildXlsx(array $rows, bool $hiddenExtraSheet = false, bool $withVba = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $sheetsXml = '<sheet name="Data" sheetId="1" r:id="rId1"/>';
        if ($hiddenExtraSheet) {
            $sheetsXml .= '<sheet name="Hidden" sheetId="2" r:id="rId2" state="hidden"/>';
        }

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>'.$sheetsXml.'</sheets>
</workbook>');

        $relsXml = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
        $relsXml .= '<Relationship Id="rIdSS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        if ($hiddenExtraSheet) {
            $relsXml .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>';
        }

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relsXml.'</Relationships>');

        $flatStrings = [];
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $flatStrings[] = $cell;
            }
        }
        $unique = array_values(array_unique($flatStrings));
        $index = array_flip($unique);

        $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($unique).'" uniqueCount="'.count($unique).'">';
        foreach ($unique as $s) {
            $sstXml .= '<si><t>'.htmlspecialchars((string) $s, ENT_XML1).'</t></si>';
        }
        $sstXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $sstXml);

        $colLetter = fn (int $i) => chr(65 + $i);
        $maxCol = max(array_map('count', $rows));
        $dimEnd = $colLetter($maxCol - 1).count($rows);

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$dimEnd.'"/><sheetData>';
        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $sheetXml .= '<row r="'.$rowNum.'">';
            foreach ($row as $cIdx => $cell) {
                $ref = $colLetter($cIdx).$rowNum;
                $sheetXml .= '<c r="'.$ref.'" t="s"><v>'.$index[$cell].'</v></c>';
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        if ($hiddenExtraSheet) {
            $zip->addFromString('xl/worksheets/sheet2.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c></row></sheetData></worksheet>');
        }

        if ($withVba) {
            $zip->addFromString('xl/vbaProject.bin', "\x00\x01\x02fake-macro-binary");
        }

        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }
}
