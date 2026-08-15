<?php

namespace App\Domain\Statements\Readers;

use App\Domain\Statements\Contracts\StatementFileReaderContract;
use App\Domain\Statements\Exceptions\StatementParseException;

/**
 * Streams a CSV file line-by-line via fgetcsv() -- never loads the whole
 * file into memory -- handling quoted fields, embedded delimiters, and a
 * UTF-8 BOM. Enforces the frozen MAX_ROW_COUNT limit (PHASE_7_DECISION_
 * PACKAGE.md section 10, Open Decision 6) during the read itself, so an
 * oversized file is rejected before any downstream processing.
 */
class CsvFileReader implements StatementFileReaderContract
{
    public const MAX_ROWS = 10000;

    public function read(string $absolutePath): array
    {
        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new StatementParseException('The uploaded CSV file could not be opened.');
        }

        $rows = [];
        $first = true;

        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                if ($first) {
                    $row[0] = $this->stripBom($row[0] ?? '');
                    $first = false;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw new StatementParseException(
                        'The CSV file exceeds the maximum permitted row count of '.self::MAX_ROWS.'.'
                    );
                }

                $rows[] = array_map(
                    fn ($cell) => trim((string) $this->toUtf8($cell ?? '')),
                    $row
                );
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw new StatementParseException('The CSV file contains no readable rows.');
        }

        return $rows;
    }

    private function stripBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }

    private function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
