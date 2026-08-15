<?php

namespace App\Domain\Statements\Profiles;

use App\Domain\Statements\Contracts\BankProfileContract;
use App\Domain\Statements\Dto\NormalizedStatement;
use App\Domain\Statements\Dto\NormalizedStatementRow;
use App\Domain\Statements\Exceptions\StatementParseException;
use Illuminate\Support\Carbon;

/**
 * Kotak Mahindra Bank CSV export (PHASE_7_DECISION_PACKAGE.md section 4B).
 * Detected entirely by content -- a rows-13 preamble containing an IFSC
 * code, followed by a header row naming "Transaction Date"/"Value Date"/
 * "Dr / Cr" -- never by filename.
 */
class KotakCsvProfile implements BankProfileContract
{
    public function key(): string
    {
        return 'kotak_csv_v1';
    }

    public function label(): string
    {
        return 'Kotak Mahindra Bank (CSV)';
    }

    public function fileType(): string
    {
        return 'csv';
    }

    public function matches(array $rows): bool
    {
        return $this->findHeaderRowIndex($rows) !== null && $this->hasIfscSignature($rows);
    }

    public function normalize(array $rows): NormalizedStatement
    {
        $headerIndex = $this->findHeaderRowIndex($rows);
        if ($headerIndex === null) {
            throw new StatementParseException('Unable to locate the Kotak statement header row.');
        }

        $header = array_map(fn ($cell) => strtolower(trim($cell)), $rows[$headerIndex]);
        $columns = [
            'transaction_date' => $this->findColumn($header, ['transaction date']),
            'value_date' => $this->findColumn($header, ['value date']),
            'description' => $this->findColumn($header, ['description']),
            'reference' => $this->findColumn($header, ['chq', 'ref']),
            'amount' => $this->findColumn($header, ['amount']),
            'direction' => $this->findColumn($header, ['dr / cr', 'dr/cr']),
            'balance' => $this->findColumn($header, ['balance']),
        ];

        foreach (['transaction_date', 'amount', 'direction'] as $required) {
            if ($columns[$required] === null) {
                throw new StatementParseException("Kotak statement is missing the required '{$required}' column.");
            }
        }

        $normalizedRows = [];
        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $transactionDateRaw = $row[$columns['transaction_date']] ?? '';
            if (trim($transactionDateRaw) === '') {
                continue;
            }

            $amountRaw = $row[$columns['amount']] ?? '';
            if (trim($amountRaw) === '' || ! is_numeric(str_replace(',', '', $amountRaw))) {
                continue;
            }

            $directionRaw = strtoupper(trim($row[$columns['direction']] ?? ''));
            $direction = str_starts_with($directionRaw, 'CR') ? 'INFLOW' : 'OUTFLOW';

            $normalizedRows[] = new NormalizedStatementRow(
                transactionDate: $this->normalizeDate($transactionDateRaw),
                valueDate: $columns['value_date'] !== null && trim($row[$columns['value_date']] ?? '') !== ''
                    ? $this->normalizeDate($row[$columns['value_date']])
                    : null,
                description: trim($row[$columns['description']] ?? ''),
                referenceNumber: $columns['reference'] !== null && trim($row[$columns['reference']] ?? '') !== ''
                    ? trim($row[$columns['reference']])
                    : null,
                normalizedAmount: number_format((float) str_replace(',', '', $amountRaw), 2, '.', ''),
                direction: $direction,
                statementBalance: $columns['balance'] !== null && trim($row[$columns['balance']] ?? '') !== ''
                    ? number_format((float) str_replace(',', '', $row[$columns['balance']]), 2, '.', '')
                    : null,
                rawData: $row,
            );
        }

        if ($normalizedRows === []) {
            throw new StatementParseException('The Kotak statement contains no parseable transaction rows.');
        }

        return new NormalizedStatement(rows: $normalizedRows);
    }

    private function hasIfscSignature(array $rows): bool
    {
        foreach (array_slice($rows, 0, 20) as $row) {
            foreach ($row as $cell) {
                if (str_contains(strtoupper((string) $cell), 'IFSC')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function findHeaderRowIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $joined = strtolower(implode(' ', $row));
            if (str_contains($joined, 'transaction date')
                && str_contains($joined, 'value date')
                && (str_contains($joined, 'dr / cr') || str_contains($joined, 'dr/cr'))
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

    private function normalizeDate(string $raw): string
    {
        $raw = trim($raw);

        foreach (['d-m-y H:i', 'd-m-Y H:i', 'd-m-y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        throw new StatementParseException("Unrecognized Kotak statement date format: '{$raw}'.");
    }
}
