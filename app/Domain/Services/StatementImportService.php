<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\StatementImportException;
use App\Domain\Statements\Exceptions\StatementParseException;
use App\Domain\Statements\Exceptions\UnsupportedStatementFormatException;
use App\Domain\Statements\ProfileRegistry;
use App\Domain\Statements\Readers\CsvFileReader;
use App\Domain\Statements\Readers\XlsxFileReader;
use App\Domain\Statements\StatementFormatDetector;
use App\Jobs\ParseStatementImportJob;
use App\Models\Account;
use App\Models\StatementImport;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Owns the upload half of the Phase 7 import lifecycle (PHASE_7_DECISION_
 * PACKAGE.md section 8): UPLOAD -> IMPORT BATCH CREATED -> QUEUED, plus
 * the Hybrid profile-selection state machine's synchronous steps (content
 * detection, zero/one/many-match handling, and the follow-up explicit
 * profile selection for an ambiguous file). PARSE + NORMALIZE onward is
 * owned by ParseStatementImportJob, on the queue.
 *
 * This service never creates, updates, or deletes Transaction, LedgerEntry,
 * or PaymentObligation rows -- it writes only to the statement staging
 * domain (statement_imports), per the frozen Phase Boundary.
 */
class StatementImportService
{
    public function __construct(
        private readonly OwnershipGuard $ownership,
        private readonly ProfileRegistry $profiles,
        private readonly StatementFormatDetector $formatDetector,
        private readonly CsvFileReader $csvReader,
        private readonly XlsxFileReader $xlsxReader,
    ) {}

    /**
     * @return array{import: StatementImport, candidates: array<string, string>}
     *
     * @throws StatementImportException|UnsupportedStatementFormatException|StatementParseException
     */
    public function initiateUpload(User $user, Account $account, UploadedFile $file): array
    {
        $this->ownership->assertAccountOwnership($account, $user->id);

        $hash = hash_file('sha256', $file->getRealPath());
        if ($hash === false) {
            throw new StatementImportException('The uploaded file could not be read.');
        }

        if (StatementImport::query()->where('account_id', $account->id)->where('file_hash', $hash)->exists()) {
            throw new StatementImportException('This exact file has already been imported for this account.');
        }

        $format = $this->formatDetector->detect($file->getRealPath(), (string) $file->getClientOriginalExtension());
        if ($format === null) {
            throw new UnsupportedStatementFormatException('Unsupported or unrecognized statement file format.');
        }

        $rows = $this->readRows($format, $file->getRealPath());
        $candidates = $this->profiles->detect($format, $rows);

        if ($candidates === []) {
            throw new UnsupportedStatementFormatException('The file content did not match any supported bank statement profile.');
        }

        $storagePath = $this->storeFile($user, $account, $file, $format);

        if (count($candidates) === 1) {
            $import = $this->createImportRow($user, $account, $file, $hash, $format, $storagePath, $candidates[0]->key());
            $this->queue($import);

            return ['import' => $import, 'candidates' => []];
        }

        $import = $this->createImportRow($user, $account, $file, $hash, $format, $storagePath, null);

        $candidateMap = [];
        foreach ($candidates as $candidate) {
            $candidateMap[$candidate->key()] = $candidate->label();
        }

        return ['import' => $import, 'candidates' => $candidateMap];
    }

    /**
     * Resolves an ambiguous import (section 5, "Ambiguous Match") once the
     * user has explicitly chosen one of the presented candidate profiles.
     *
     * @throws StatementImportException
     */
    public function selectProfile(User $user, StatementImport $import, string $profileKey): StatementImport
    {
        $this->ownership->assertStatementImportOwnership($import, $user->id);

        if ($import->status !== StatementImport::STATUS_UPLOADED || $import->parser_profile !== null) {
            throw new StatementImportException('This import is not awaiting profile selection.');
        }

        $profile = $this->profiles->find($profileKey);
        if ($profile === null || $profile->fileType() !== $import->file_type) {
            throw new StatementImportException('Invalid profile selection.');
        }

        $rows = $this->readRows($import->file_type, Storage::disk('local')->path($import->storage_path));

        if (! $profile->matches($rows)) {
            $import->update(['status' => StatementImport::STATUS_FAILED, 'error_payload' => ['message' => 'The selected profile does not match this file\'s content.']]);
            throw new StatementImportException('The selected profile does not match this file\'s content.');
        }

        try {
            $profile->normalize($rows);
        } catch (StatementParseException $e) {
            $import->update(['status' => StatementImport::STATUS_FAILED, 'error_payload' => ['message' => $e->getMessage()]]);
            throw new StatementImportException($e->getMessage());
        }

        $import->update(['parser_profile' => $profileKey]);
        $this->queue($import);

        return $import->fresh();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readRows(string $format, string $absolutePath): array
    {
        return $format === 'csv'
            ? $this->csvReader->read($absolutePath)
            : $this->xlsxReader->read($absolutePath);
    }

    private function storeFile(User $user, Account $account, UploadedFile $file, string $format): string
    {
        $directory = "statements/{$user->id}/{$account->id}";
        $filename = Str::uuid()->toString().'.'.$format;

        $storedPath = $file->storeAs($directory, $filename, 'local');
        if ($storedPath === false) {
            throw new StatementImportException('The uploaded file could not be stored.');
        }

        return $storedPath;
    }

    private function createImportRow(
        User $user,
        Account $account,
        UploadedFile $file,
        string $hash,
        string $format,
        string $storagePath,
        ?string $parserProfile,
    ): StatementImport {
        try {
            return StatementImport::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'parser_profile' => $parserProfile,
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $storagePath,
                'file_hash' => $hash,
                'file_type' => $format,
                'status' => StatementImport::STATUS_UPLOADED,
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new StatementImportException('This exact file has already been imported for this account.');
            }

            throw $e;
        }
    }

    private function queue(StatementImport $import): void
    {
        $import->update(['status' => StatementImport::STATUS_QUEUED]);
        ParseStatementImportJob::dispatch($import->id);
    }
}
