<?php

namespace App\Domain\Statements\Contracts;

use App\Domain\Statements\Exceptions\StatementParseException;

/**
 * Reads a raw statement file (CSV or XLSX) into a plain array of rows,
 * each row itself an array of trimmed string cell values in column order.
 * Readers own file-format concerns only (encoding, delimiters, ZIP/XML
 * structure, security limits) -- never bank-specific meaning, which is a
 * BankProfileContract's responsibility (PHASE_7_DECISION_PACKAGE.md
 * section 8).
 */
interface StatementFileReaderContract
{
    /**
     * @return array<int, array<int, string>>
     *
     * @throws StatementParseException
     */
    public function read(string $absolutePath): array;
}
