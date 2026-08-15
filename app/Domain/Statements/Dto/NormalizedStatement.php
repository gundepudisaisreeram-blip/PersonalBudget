<?php

namespace App\Domain\Statements\Dto;

/**
 * The full normalized output of parsing one statement file under one bank
 * profile: header-derived metadata plus every normalized row.
 */
final class NormalizedStatement
{
    /**
     * @param  array<int, NormalizedStatementRow>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly ?string $periodFrom = null,
        public readonly ?string $periodTo = null,
        public readonly ?string $openingBalance = null,
        public readonly ?string $closingBalance = null,
    ) {}
}
