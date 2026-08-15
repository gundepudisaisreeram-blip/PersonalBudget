<?php

namespace App\Domain\Statements\Contracts;

use App\Domain\Statements\Dto\NormalizedStatement;
use App\Domain\Statements\Exceptions\StatementParseException;

/**
 * PDF Parser Framework -- abstraction only (PHASE_7_DECISION_PACKAGE.md
 * section 4, "PDF: Restricted to a Parser Framework / Abstraction only").
 *
 * No concrete implementation of this contract is provided in Phase 7 V1:
 * no OCR, no PDF table extraction. It exists solely as the extension
 * point a future phase would implement, so that PDF support can be added
 * later without changing StatementImportService, ParseStatementImportJob,
 * or the ProfileRegistry's calling convention for CSV/XLSX.
 */
interface PdfExtractorContract
{
    /**
     * @return array<int, array<int, string>>
     *
     * @throws StatementParseException
     */
    public function extractRows(string $absolutePath): array;

    public function normalize(array $rows): NormalizedStatement;
}
