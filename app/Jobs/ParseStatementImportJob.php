<?php

namespace App\Jobs;

use App\Domain\Statements\Dto\NormalizedStatement;
use App\Domain\Statements\Exceptions\StatementParseException;
use App\Domain\Statements\ProfileRegistry;
use App\Domain\Statements\Readers\CsvFileReader;
use App\Domain\Statements\Readers\XlsxFileReader;
use App\Models\StatementImport;
use App\Models\StatementTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * PARSE + NORMALIZE -> VALIDATE -> PREVIEW_READY (PHASE_7_DECISION_
 * PACKAGE.md section 8). Strict all-or-nothing (section 9, Open Decision
 * 3): a confirmed cross-import duplicate reference fails the whole batch
 * before a single row is persisted; a fallback-fingerprint collision alone
 * never fails the batch, it only flags the affected row as a duplicate
 * candidate.
 *
 * CRITICAL BOUNDARY: this job never creates, updates, or deletes a
 * Transaction, LedgerEntry, or PaymentObligation row -- it writes only to
 * statement_transactions.
 */
class ParseStatementImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    private const INSERT_CHUNK_SIZE = 500;

    public function __construct(public readonly int $statementImportId) {}

    public function handle(ProfileRegistry $profiles, CsvFileReader $csvReader, XlsxFileReader $xlsxReader): void
    {
        $import = StatementImport::find($this->statementImportId);
        if ($import === null || $import->status !== StatementImport::STATUS_QUEUED) {
            return;
        }

        $import->update(['status' => StatementImport::STATUS_PARSING]);

        try {
            $profile = $import->parser_profile !== null ? $profiles->find($import->parser_profile) : null;
            if ($profile === null) {
                throw new StatementParseException('The import has no valid parser profile assigned.');
            }

            $absolutePath = Storage::disk('local')->path($import->storage_path);
            $rows = $import->file_type === 'csv' ? $csvReader->read($absolutePath) : $xlsxReader->read($absolutePath);
            $normalized = $profile->normalize($rows);

            $import->update(['status' => StatementImport::STATUS_VALIDATING]);

            $this->validateAndPersist($import, $normalized);
        } catch (StatementParseException $e) {
            $import->update([
                'status' => StatementImport::STATUS_FAILED,
                'error_payload' => ['message' => $e->getMessage()],
            ]);
        }
    }

    private function validateAndPersist(StatementImport $import, NormalizedStatement $normalized): void
    {
        $accountId = $import->account_id;
        $rows = $normalized->rows;

        $referenceNumbers = array_values(array_filter(array_map(fn ($row) => $row->referenceNumber, $rows)));
        $withinBatchCounts = array_count_values($referenceNumbers);

        $existingByReference = $referenceNumbers === [] ? collect() : StatementTransaction::query()
            ->where('account_id', $accountId)
            ->whereIn('reference_number', array_unique($referenceNumbers))
            ->get()
            ->keyBy('reference_number');

        $failureReasons = [];
        foreach ($rows as $index => $row) {
            if ($row->referenceNumber === null) {
                continue;
            }

            if (($withinBatchCounts[$row->referenceNumber] ?? 0) > 1) {
                $failureReasons[] = 'Row '.($index + 1).": reference '{$row->referenceNumber}' appears more than once in this file.";

                continue;
            }

            $existing = $existingByReference->get($row->referenceNumber);
            if ($existing === null) {
                continue;
            }

            $isExactMatch = $existing->transaction_date->toDateString() === $row->transactionDate
                && (string) $existing->normalized_amount === $row->normalizedAmount
                && $existing->direction === $row->direction;

            $failureReasons[] = $isExactMatch
                ? 'Row '.($index + 1).": reference '{$row->referenceNumber}' is a confirmed duplicate of a previously imported transaction."
                : 'Row '.($index + 1).": reference '{$row->referenceNumber}' matches a previous import but the transaction data conflicts.";
        }

        if ($failureReasons !== []) {
            $import->update([
                'status' => StatementImport::STATUS_FAILED,
                'error_payload' => ['message' => 'Confirmed cross-import duplicate reference(s) detected.', 'details' => $failureReasons],
            ]);

            return;
        }

        $fingerprints = array_map(fn ($row) => $row->rowFingerprint($accountId), $rows);
        $existingFingerprints = array_flip(
            StatementTransaction::query()
                ->where('account_id', $accountId)
                ->whereIn('row_fingerprint', array_unique($fingerprints))
                ->pluck('row_fingerprint')
                ->all()
        );

        $now = Carbon::now();
        $insertRows = [];
        $dates = [];

        foreach ($rows as $row) {
            $fingerprint = $row->rowFingerprint($accountId);
            $dates[] = $row->transactionDate;

            $insertRows[] = [
                'user_id' => $import->user_id,
                'statement_import_id' => $import->id,
                'account_id' => $accountId,
                'transaction_date' => $row->transactionDate,
                'value_date' => $row->valueDate,
                'description' => $row->description,
                'reference_number' => $row->referenceNumber,
                'normalized_amount' => $row->normalizedAmount,
                'direction' => $row->direction,
                'statement_balance' => $row->statementBalance,
                'row_fingerprint' => $fingerprint,
                'raw_data' => json_encode($row->rawData),
                'processing_status' => 'STAGED',
                'categorization_status' => 'UNCATEGORIZED',
                'match_status' => 'UNMATCHED',
                'duplicate_status' => isset($existingFingerprints[$fingerprint])
                    ? StatementTransaction::DUPLICATE_STATUS_CANDIDATE
                    : StatementTransaction::DUPLICATE_STATUS_VALID,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($import, $insertRows, $dates) {
            foreach (array_chunk($insertRows, self::INSERT_CHUNK_SIZE) as $chunk) {
                StatementTransaction::query()->insert($chunk);
            }

            $import->update([
                'status' => StatementImport::STATUS_PREVIEW_READY,
                'transaction_count' => count($insertRows),
                'period_from' => min($dates),
                'period_to' => max($dates),
            ]);
        });
    }
}
