<?php

namespace App\Domain\Statements\Contracts;

use App\Domain\Statements\Dto\NormalizedStatement;
use App\Domain\Statements\Exceptions\StatementParseException;

/**
 * A bank-specific statement profile: recognizes its own structural
 * signature within already-read raw rows (never the filename, per
 * PHASE_7_DECISION_PACKAGE.md section 5's "Architectural Rule") and
 * normalizes those rows into staging-ready data. A profile never touches
 * ledger/domain financial logic -- it only recognizes format, locates
 * headers, parses fields, and normalizes values/dates/direction.
 */
interface BankProfileContract
{
    /** Stable identifier persisted to statement_imports.parser_profile. */
    public function key(): string;

    /** Human-readable label shown in the ambiguous-selection UI. */
    public function label(): string;

    /** 'csv' or 'xlsx' -- must match the file's detected format. */
    public function fileType(): string;

    /**
     * Content-based detection only. MUST NOT be told or infer anything
     * from the original filename.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    public function matches(array $rows): bool;

    /**
     * @param  array<int, array<int, string>>  $rows
     *
     * @throws StatementParseException if the rows fail to parse under this
     *                                 profile (used both for normal parsing and for validating an explicit
     *                                 user-selected profile against ambiguous content).
     */
    public function normalize(array $rows): NormalizedStatement;
}
