<?php

namespace App\Domain\Statements\Dto;

/**
 * One bank-statement row after profile normalization, ready to be staged
 * into `statement_transactions`. Direction is always INFLOW/OUTFLOW,
 * amount is always a positive Money-compatible string (BR-003 -- no
 * floats), per the ledger_entries sign convention this staging domain
 * intentionally mirrors without touching (PHASE_7_DECISION_PACKAGE.md
 * section 5).
 */
final class NormalizedStatementRow
{
    public function __construct(
        public readonly string $transactionDate,
        public readonly ?string $valueDate,
        public readonly string $description,
        public readonly ?string $referenceNumber,
        public readonly string $normalizedAmount,
        public readonly string $direction,
        public readonly ?string $statementBalance,
        public readonly array $rawData,
    ) {}

    public function rowFingerprint(int $accountId): string
    {
        return hash('sha256', implode('|', [
            $accountId,
            $this->transactionDate,
            $this->normalizedAmount,
            $this->direction,
            mb_strtolower(trim($this->description)),
        ]));
    }
}
