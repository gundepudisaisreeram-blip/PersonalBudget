<?php

namespace App\Domain\Statements\Readers;

use App\Domain\Statements\Contracts\StatementFileReaderContract;
use App\Domain\Statements\Exceptions\StatementParseException;
use XMLReader;
use ZipArchive;

/**
 * A from-scratch, streaming XLSX reader built only on PHP's bundled `zip`
 * and `xmlreader` extensions -- no third-party spreadsheet library. This
 * is a deliberate security choice: PHASE_7_DECISION_PACKAGE.md section 10
 * requires precise control over decompression-ratio limits, worksheet-
 * dimension bounds, hidden-sheet skipping, macro/embedded-object
 * avoidance, and never loading the full DOM into memory -- guarantees
 * this reader enforces directly rather than trusting a dependency's
 * defaults.
 *
 * Only the OOXML parts strictly required to extract tabular data are ever
 * opened: xl/workbook.xml, xl/_rels/workbook.xml.rels, xl/sharedStrings.xml,
 * and the first visible worksheet's xl/worksheets/sheetN.xml. Macro
 * (xl/vbaProject.bin) and embedded-object parts are never read, so they
 * are "ignored" by construction rather than by special-case detection.
 * Formula cells' cached <v> value is read; the <f> formula body itself is
 * never evaluated.
 */
class XlsxFileReader implements StatementFileReaderContract
{
    public const MAX_ROWS = 10000;

    private const MAX_TOTAL_UNCOMPRESSED_BYTES = 100 * 1024 * 1024;

    private const MAX_ENTRY_COMPRESSION_RATIO = 200;

    private const MAX_SHARED_STRINGS = 200000;

    private const MAX_DIMENSION_ROWS = 200000;

    public function read(string $absolutePath): array
    {
        $zip = new ZipArchive;
        $opened = @$zip->open($absolutePath, ZipArchive::RDONLY);

        if ($opened !== true) {
            throw new StatementParseException('The uploaded file is not a valid or readable XLSX archive.');
        }

        try {
            $this->guardAgainstDecompressionBomb($zip);

            $workbookXml = $this->readEntry($zip, 'xl/workbook.xml');
            if ($workbookXml === null) {
                throw new StatementParseException('The uploaded XLSX file is missing its workbook definition.');
            }

            $sheets = $this->parseWorkbookSheets($workbookXml);
            $relationships = $this->parseWorkbookRelationships(
                $this->readEntry($zip, 'xl/_rels/workbook.xml.rels')
            );

            $visibleSheet = $this->firstVisibleSheet($sheets, $relationships);
            if ($visibleSheet === null) {
                throw new StatementParseException('The XLSX file has no visible worksheet to import.');
            }

            $sharedStrings = $this->parseSharedStrings($zip);

            return $this->streamWorksheetRows($zip, $visibleSheet, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    /**
     * Decompression-bomb defense: inspect the ZIP central directory (via
     * statIndex, which never decompresses anything) for an implausible
     * total uncompressed size or per-entry compression ratio, before any
     * entry is actually extracted.
     */
    private function guardAgainstDecompressionBomb(ZipArchive $zip): void
    {
        $totalUncompressed = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $totalUncompressed += $stat['size'];

            if ($stat['comp_size'] > 0
                && ($stat['size'] / $stat['comp_size']) > self::MAX_ENTRY_COMPRESSION_RATIO
                && $stat['size'] > 10 * 1024 * 1024
            ) {
                throw new StatementParseException('The XLSX file was rejected: an archive entry exceeded the permitted decompression ratio.');
            }
        }

        if ($totalUncompressed > self::MAX_TOTAL_UNCOMPRESSED_BYTES) {
            throw new StatementParseException('The XLSX file was rejected: its uncompressed contents exceed the permitted size.');
        }
    }

    private function readEntry(ZipArchive $zip, string $name): ?string
    {
        $contents = $zip->getFromName($name);

        return $contents === false ? null : $contents;
    }

    /**
     * @return array<int, array{name: string, rId: ?string, hidden: bool}>
     */
    private function parseWorkbookSheets(string $workbookXml): array
    {
        $reader = $this->openXmlReader($workbookXml);
        $sheets = [];

        while (@$reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $state = $reader->getAttribute('state');
                $sheets[] = [
                    'name' => (string) $reader->getAttribute('name'),
                    'rId' => $reader->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'),
                    'hidden' => in_array($state, ['hidden', 'veryHidden'], true),
                ];
            }
        }
        $reader->close();

        if ($sheets === []) {
            throw new StatementParseException('The XLSX workbook declares no worksheets.');
        }

        return $sheets;
    }

    /**
     * @return array<string, string> rId => target path within xl/
     */
    private function parseWorkbookRelationships(?string $relsXml): array
    {
        if ($relsXml === null) {
            return [];
        }

        $reader = $this->openXmlReader($relsXml);
        $map = [];

        while (@$reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'Relationship') {
                $id = $reader->getAttribute('Id');
                $target = $reader->getAttribute('Target');
                if ($id !== null && $target !== null) {
                    $map[$id] = ltrim($target, '/');
                }
            }
        }
        $reader->close();

        return $map;
    }

    /**
     * @param  array<int, array{name: string, rId: ?string, hidden: bool}>  $sheets
     * @param  array<string, string>  $relationships
     */
    private function firstVisibleSheet(array $sheets, array $relationships): ?string
    {
        foreach ($sheets as $sheet) {
            if ($sheet['hidden'] || $sheet['rId'] === null) {
                continue;
            }

            $target = $relationships[$sheet['rId']] ?? null;
            if ($target === null) {
                continue;
            }

            return str_starts_with($target, 'worksheets/') ? 'xl/'.$target : $target;
        }

        return null;
    }

    /**
     * @return array<int, string> 0-indexed shared string table
     */
    private function parseSharedStrings(ZipArchive $zip): array
    {
        $xml = $this->readEntry($zip, 'xl/sharedStrings.xml');
        if ($xml === null) {
            return [];
        }

        $reader = $this->openXmlReader($xml);
        $strings = [];
        $buffer = '';
        $inItem = false;

        while (@$reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $inItem = true;
                $buffer = '';
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $inItem = false;
                $strings[] = $buffer;
                if (count($strings) > self::MAX_SHARED_STRINGS) {
                    throw new StatementParseException('The XLSX file was rejected: shared string table exceeds the permitted size.');
                }
            } elseif ($inItem && $reader->nodeType === XMLReader::TEXT) {
                $buffer .= $reader->value;
            }
        }
        $reader->close();

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function streamWorksheetRows(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $xml = $this->readEntry($zip, $sheetPath);
        if ($xml === null) {
            throw new StatementParseException('The XLSX file is missing its primary worksheet data.');
        }

        $reader = $this->openXmlReader($xml);
        $rows = [];
        $currentRow = [];
        $currentCellRef = null;
        $currentCellType = null;
        $currentValue = '';
        $inValue = false;
        $inRow = false;

        while (@$reader->read()) {
            $name = $reader->localName;

            if ($reader->nodeType === XMLReader::ELEMENT && $name === 'dimension') {
                $this->guardWorksheetDimension($reader->getAttribute('ref'));
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $name === 'row') {
                $inRow = true;
                $currentRow = [];
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $name === 'row') {
                $inRow = false;
                if (count($rows) >= self::MAX_ROWS) {
                    throw new StatementParseException('The XLSX file exceeds the maximum permitted row count of '.self::MAX_ROWS.'.');
                }
                $rows[] = $currentRow;
            } elseif ($inRow && $reader->nodeType === XMLReader::ELEMENT && $name === 'c') {
                $currentCellRef = $reader->getAttribute('r');
                $currentCellType = $reader->getAttribute('t');
                $currentValue = '';
                $inValue = false;
            } elseif ($inRow && $reader->nodeType === XMLReader::ELEMENT && in_array($name, ['v', 't'], true)) {
                $inValue = true;
                $currentValue = '';
            } elseif ($inRow && $reader->nodeType === XMLReader::END_ELEMENT && in_array($name, ['v', 't'], true)) {
                $inValue = false;
            } elseif ($inRow && $inValue && $reader->nodeType === XMLReader::TEXT) {
                $currentValue .= $reader->value;
            } elseif ($inRow && $reader->nodeType === XMLReader::END_ELEMENT && $name === 'c' && $currentCellRef !== null) {
                $columnIndex = $this->columnIndexFromRef($currentCellRef);
                $currentRow[$columnIndex] = $this->resolveCellValue($currentCellType, $currentValue, $sharedStrings);
                $currentCellRef = null;
            }
        }
        $reader->close();

        if ($rows === []) {
            throw new StatementParseException('The XLSX worksheet contains no readable rows.');
        }

        return array_map(fn (array $row) => $this->normalizeSparseRow($row), $rows);
    }

    private function guardWorksheetDimension(?string $ref): void
    {
        if ($ref === null || ! str_contains($ref, ':')) {
            return;
        }

        [, $end] = explode(':', $ref, 2);
        if (preg_match('/\d+/', $end, $matches) === 1 && (int) $matches[0] > self::MAX_DIMENSION_ROWS) {
            throw new StatementParseException('The XLSX file was rejected: declared worksheet dimensions are implausibly large.');
        }
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function resolveCellValue(?string $type, string $rawValue, array $sharedStrings): string
    {
        if ($type === 's') {
            $index = (int) $rawValue;

            return $sharedStrings[$index] ?? '';
        }

        return trim($rawValue);
    }

    private function columnIndexFromRef(string $cellRef): int
    {
        preg_match('/^([A-Z]+)/', $cellRef, $matches);
        $letters = $matches[1] ?? 'A';

        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * @param  array<int, string>  $row
     * @return array<int, string>
     */
    private function normalizeSparseRow(array $row): array
    {
        if ($row === []) {
            return [];
        }

        $max = max(array_keys($row));
        $normalized = [];
        for ($i = 0; $i <= $max; $i++) {
            $normalized[] = $row[$i] ?? '';
        }

        return $normalized;
    }

    private function openXmlReader(string $xml): XMLReader
    {
        // LIBXML_NONET blocks external-entity network fetches; NOENT is
        // deliberately omitted so custom/DTD-defined general entities are
        // never substituted (predefined entities like &amp; still resolve
        // regardless), closing off billion-laughs-style expansion attacks.
        $reader = XMLReader::XML($xml, 'UTF-8', LIBXML_NONET);

        if ($reader === false) {
            throw new StatementParseException('The XLSX file contains malformed XML and could not be parsed.');
        }

        return $reader;
    }
}
