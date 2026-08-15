<?php

namespace App\Domain\Statements\Support;

/**
 * CSV/spreadsheet formula-injection mitigation (CWE-1236,
 * PHASE_7_DECISION_PACKAGE.md section 10). Any statement-derived value
 * that is ever rendered back to a user -- in the HTML preview today, or a
 * future CSV/XLSX export -- must not be interpretable as a formula by a
 * spreadsheet application if opened outside the browser. A leading
 * apostrophe forces text interpretation in Excel/Sheets/LibreOffice
 * without altering the value's own displayed content.
 */
final class CsvFormulaGuard
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@'];

    public static function sanitize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        foreach (self::DANGEROUS_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return "'".$value;
            }
        }

        return $value;
    }
}
