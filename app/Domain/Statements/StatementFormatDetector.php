<?php

namespace App\Domain\Statements;

/**
 * Sniffs a file's true format from its magic bytes -- never from its
 * extension or client-supplied MIME type (PHASE_7_DECISION_PACKAGE.md
 * section 9/10/11, "Never trust filename or extension alone").
 *
 * Legacy .xls and password-protected/encrypted .xlsx both use the same
 * OLE/CFBF container signature, so both are rejected identically as
 * unsupported here -- exactly the frozen scope boundary (OD10: legacy XLS
 * out of scope; XLSX security: password-protected workbooks rejected).
 */
class StatementFormatDetector
{
    private const ZIP_SIGNATURE = "PK\x03\x04";

    private const OLE_SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    /** @return 'csv'|'xlsx'|null null means unsupported/unrecognized */
    public function detect(string $absolutePath, string $originalExtension): ?string
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return null;
        }

        $header = fread($handle, 8);
        fclose($handle);

        if ($header === false) {
            return null;
        }

        if (str_starts_with($header, self::ZIP_SIGNATURE)) {
            return 'xlsx';
        }

        if (str_starts_with($header, self::OLE_SIGNATURE)) {
            // Legacy .xls or an encrypted/password-protected .xlsx --
            // both explicitly out of Phase 7 V1 scope.
            return null;
        }

        $extension = strtolower($originalExtension);
        if ($extension === 'csv' && $this->looksLikeText($absolutePath)) {
            return 'csv';
        }

        return null;
    }

    private function looksLikeText(string $absolutePath): bool
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $sample = fread($handle, 4096);
        fclose($handle);

        if ($sample === false || $sample === '') {
            return false;
        }

        // A NUL byte anywhere in the leading sample is a reliable binary
        // signal a genuine CSV export will never contain.
        return ! str_contains($sample, "\0");
    }
}
