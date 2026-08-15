<?php

namespace App\Domain\Statements\Profiles;

use App\Domain\Statements\Contracts\BankProfileContract;
use App\Domain\Statements\Dto\NormalizedStatement;
use App\Domain\Statements\Dto\NormalizedStatementRow;
use App\Domain\Statements\Exceptions\StatementParseException;
use Illuminate\Support\Carbon;

/**
 * ICICI-style XLSX statement export (PHASE_7_DECISION_PACKAGE.md section
 * 4C). Named for its verified internal structure, not for the sample
 * file's misleading "KVB_..." filename -- per the frozen architectural
 * rule, this profile is detected purely from the worksheet's own headers
 * (separate Debit/Credit columns, "Particulars" description column),
 * never from a filename.
 */
class IciciStyleXlsxProfile implements BankProfileContract
{
    public function key(): string
    {
        return 'icici_style_xlsx_v1';
    }

    public function label(): string
    {
        return 'ICICI-style Bank Statement (XLSX)';
    }

    public function fileType(): string
    {
        return 'xlsx';
    }

    public function matches(array $rows): bool
    {
        return $this->findHeaderRowIndex($rows) !== null;
    }

    public function normalize(array $rows): NormalizedStatement
    {
        $headerIndex = $this->findHeaderRowIndex($rows);
        if ($headerIndex === null) {
            throw new StatementParseException('Unable to locate the ICICI-style statement header row.');
        }

        $header = array_map(fn ($cell) => strtolower(trim((string) $cell)), $rows[$headerIndex]);
        $columns = [
            'transaction_date' => $this->findColumn($header, ['transaction date']),
            'value_date' => $this->findColumn($header, ['value date']),
            'description' => $this->findColumn($header, ['particulars', 'description']),
            'reference' => $this->findColumn($header, ['reference', 'cheque', 'chq']),
            'debit' => $this->findColumn($header, ['debit']),
            'credit' => $this->findColumn($header, ['credit']),
            'balance' => $this->findColumn($header, ['balance']),
        ];

        foreach (['transaction_date', 'debit', 'credit'] as $required) {
            if ($columns[$required] === null) {
                throw new StatementParseException("ICICI-style statement is missing the required '{$required}' column.");
            }
        }

        $normalizedRows = [];
        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $transactionDateRaw = trim((string) ($row[$columns['transaction_date']] ?? ''));
            if ($transactionDateRaw === '') {
                continue;
            }

            $debitRaw = trim((string) ($row[$columns['debit']] ?? ''));
            $creditRaw = trim((string) ($row[$columns['credit']] ?? ''));
            $debit = $this->parseAmount($debitRaw);
            $credit = $this->parseAmount($creditRaw);

            if ($debit === null && $credit === null) {
                continue;
            }

            $direction = ($debit !== null && $debit > 0) ? 'OUTFLOW' : 'INFLOW';
            $amount = $direction === 'OUTFLOW' ? $debit : $credit;

            if ($amount === null || $amount <= 0) {
                continue;
            }

            $normalizedRows[] = new NormalizedStatementRow(
                transactionDate: $this->normalizeDate($transactionDateRaw),
                valueDate: $columns['value_date'] !== null && trim((string) ($row[$columns['value_date']] ?? '')) !== ''
                    ? $this->normalizeDate((string) $row[$columns['value_date']])
                    : null,
                description: trim((string) ($row[$columns['description']] ?? '')),
                referenceNumber: $columns['reference'] !== null && trim((string) ($row[$columns['reference']] ?? '')) !== ''
                    ? trim((string) $row[$columns['reference']])
                    : null,
                normalizedAmount: number_format($amount, 2, '.', ''),
                direction: $direction,
                statementBalance: $columns['balance'] !== null && ($balance = $this->parseAmount((string) ($row[$columns['balance']] ?? ''))) !== null
                    ? number_format($balance, 2, '.', '')
                    : null,
                rawData: $row,
            );
        }

        if ($normalizedRows === []) {
            throw new StatementParseException('The ICICI-style statement contains no parseable transaction rows.');
        }

        return new NormalizedStatement(rows: $normalizedRows);
    }

    private function findHeaderRowIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $joined = strtolower(implode(' ', array_map('strval', $row)));
            if (str_contains($joined, 'transaction date')
                && str_contains($joined, 'particulars')
                && str_contains($joined, 'debit')
                && str_contains($joined, 'credit')
            ) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $needles
     */
    private function findColumn(array $header, array $needles): ?int
    {
        foreach ($header as $index => $cell) {
            foreach ($needles as $needle) {
                if (str_contains($cell, $needle)) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function parseAmount(string $raw): ?float
    {
        $raw = str_replace(',', '', trim($raw));
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private function normalizeDate(string $raw): string
    {
        $raw = trim($raw);

        foreach (['d-m-Y', 'd-m-y', 'd/m/Y', 'd/m/y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        throw new StatementParseException("Unrecognized ICICI-style statement date format: '{$raw}'.");
    }
}
