# PHASE 7 EXTERNAL AUDIT BUNDLE — Bank Statement Import & Parsing

Companion to `PHASE_7_IMPLEMENTATION_REPORT.md`. Written so an independent reviewer, with no access to the implementation conversation, can verify every claim below against the repository directly.

## 1. Decision Package Version

`docs/knowledge_base/phase_decisions/PHASE_7_DECISION_PACKAGE.md`, Version 1.5.3, Date 2026-08-12. Implementation was authorized against this exact version by explicit instruction, superseding the package's own recorded "NOT AUTHORIZED" final-status line.

## 2. Test Results (full suite, this repository, this run)

Command: `php artisan test`

```
{"tool":"phpunit","result":"passed","tests":422,"passed":422,"assertions":1084,"duration_ms":41515}
```

- **422 tests, 1084 assertions, 0 failed, 0 skipped, 0 errors, 41.5s.**
- Phase 1–6 baseline (confirmed by this run of the previously-certified suite prior to this phase's changes): **351 tests / 919 assertions.**
- Phase 7 addition: **422 − 351 = 71 tests, 1084 − 919 = 165 assertions** — purely additive; the Phase 1–6 baseline count is unchanged, confirming zero regressions and zero weakened/removed tests.

Phase 7 test breakdown (isolated run, `--filter=Statements`):

```
{"tool":"phpunit","result":"passed","tests":71,"passed":71,"assertions":165,"duration_ms":15302}
```

Files: `tests/Unit/Domain/Statements/{CsvFileReaderTest,XlsxFileReaderTest,BankProfileTest}.php`, `tests/Feature/Statements/{StatementUploadTest,StatementProfileSelectionTest,StatementDuplicateTest,StatementLifecycleTest,StatementOwnershipTest,StatementAdversarialTest,PhaseBoundaryTest,StatementSchemaTest}.php`.

## 3. Pint (code style)

Command: `vendor/bin/pint --test`

```
{"tool":"pint","result":"passed"}
```

Clean on the full codebase after applying Pint's own fixers to the five files it initially flagged (`BankProfileContract.php`, `ProfileRegistry.php` — phpdoc alignment; the statement_imports migration — class-definition/quote-style/brace formatting; `BuildsStatementFixtures.php` — phpdoc alignment; `PhaseBoundaryTest.php` — import ordering/strict-type imports). No logic changed by these fixes — the full suite was re-run afterward with an identical 422/1084 result.

## 4. Migration Diff

Two new files, zero modifications to any existing migration:

```
database/migrations/2026_08_12_000200_alter_statement_imports_table_for_phase7.php
database/migrations/2026_08_12_000210_alter_statement_transactions_table_for_phase7.php
```

Resulting schema, confirmed via `SHOW CREATE TABLE` against a fresh migration of the testing database:

```sql
-- statement_imports (relevant columns/keys)
`account_id` bigint unsigned NOT NULL,
`parser_profile` varchar(255) DEFAULT NULL,
`status` enum('UPLOADED','QUEUED','PARSING','VALIDATING','PREVIEW_READY','CONFIRMED','COMPLETED','FAILED') NOT NULL DEFAULT 'UPLOADED',
`error_payload` json DEFAULT NULL,
UNIQUE KEY `statement_imports_account_id_file_hash_unique` (`account_id`,`file_hash`)

-- statement_transactions (relevant columns/keys)
`account_id` bigint unsigned DEFAULT NULL,      -- nullable; see Implementation Report section 6
`row_fingerprint` varchar(255) DEFAULT NULL,    -- nullable; see Implementation Report section 6
`reference_number` varchar(255) DEFAULT NULL,
KEY `statement_transactions_account_id_row_fingerprint_index` (`account_id`,`row_fingerprint`)     -- non-unique
KEY `statement_transactions_account_id_reference_number_index` (`account_id`,`reference_number`)   -- non-unique
```

`StatementSchemaTest` proves this programmatically: `test_account_id_file_hash_is_a_unique_constraint` (inserting a duplicate raises `QueryException`), `test_the_same_file_hash_is_permitted_for_a_different_account`, `test_two_valid_rows_with_an_identical_fallback_fingerprint_can_coexist` (two rows with the same `row_fingerprint` are both inserted successfully), `test_two_rows_with_an_identical_reference_number_can_coexist_at_the_schema_level` (confirming section 12's "database indexes are NOT uniqueness constraints" — confirmed-duplicate rejection is application logic in `ParseStatementImportJob`, never a database constraint), and `test_row_fingerprint_and_reference_number_indexes_exist_and_are_not_unique` / `test_account_id_file_hash_index_is_unique` (asserting directly against `Schema::getIndexes()`).

## 5. XLSX Security — Invariant-by-Invariant Evidence

| Section 10 invariant | Implementation | Test |
|---|---|---|
| Malformed/corrupt XLSX rejected gracefully | `ZipArchive::open()` return value checked; non-`true` → `StatementParseException`, never an unhandled exception | `test_rejects_a_corrupt_non_zip_file` |
| ZIP/decompression expansion limits | `guardAgainstDecompressionBomb()` inspects `ZipArchive::statIndex()` (never decompresses) for total uncompressed size >100MB or any entry's ratio >200:1 while >10MB, before any entry is read | `test_rejects_a_decompression_bomb` |
| Oversized worksheet dimensions rejected before allocation | `guardWorksheetDimension()` parses the `<dimension>` tag's declared row bound and rejects >200,000 before streaming continues; the streamed row-cap (10,000) is enforced independently of what the tag claims | `test_rejects_implausibly_large_declared_worksheet_dimensions` |
| Hidden worksheets ignored | `firstVisibleSheet()` skips any `<sheet state="hidden"\|"veryHidden">`; a workbook with only a hidden sheet is rejected outright | `test_ignores_a_hidden_sheet_and_reads_the_visible_one`, `test_rejects_a_workbook_with_no_visible_worksheet` |
| Formula cells: cached value read, formula never executed | The reader only ever reads a cell's `<v>` child; `<f>` is never inspected or evaluated | `test_never_evaluates_a_formula_cell_only_reads_its_cached_value` |
| Macros/embedded objects ignored/stripped | The reader only ever opens `xl/workbook.xml`, `xl/_rels/workbook.xml.rels`, `xl/sharedStrings.xml`, and the one visible worksheet part — `xl/vbaProject.bin` and any OLE part are never opened, so they are inert by construction, not by special-case detection | `test_never_reads_macro_project_binary` |
| Password-protected/encrypted workbooks rejected gracefully | A genuinely encrypted OOXML package is an OLE/CFBF container (same magic bytes as legacy XLS), which fails `ZipArchive::open()` identically to a corrupt file — no separate password-detection code path exists or is needed | `test_rejects_a_password_protected_workbook`, plus `StatementFormatDetector`'s OLE-signature branch |
| No unbounded DOM/XML loaded into memory | `XMLReader`-based streaming throughout (`workbook.xml`, `sharedStrings.xml`, the worksheet) — no `DOMDocument`, `simplexml_load_string`, or `SpreadsheetReader`-style full-file parse anywhere in `XlsxFileReader` | Structural (source inspection); the 10,001-row test also proves the streamed row-cap engages before completion |
| MAX_FILE_SIZE / MAX_ROW_COUNT (5MB / 10,000) | `UploadStatementRequest`/`StatementImportController::store()` enforce 5MB via the `max:5120` (KB) validation rule; `CsvFileReader::MAX_ROWS`/`XlsxFileReader::MAX_ROWS` enforce 10,000 live during the read itself, for both formats | `StatementUploadTest::test_an_oversized_file_is_rejected`, `CsvFileReaderTest::test_enforces_the_maximum_row_count`, `XlsxFileReaderTest::test_enforces_the_maximum_row_count` |
| CSV formula injection (CWE-1236) sanitized when rendered | `App\Domain\Statements\Support\CsvFormulaGuard::sanitize()` prefixes a leading `=`/`+`/`-`/`@` with an apostrophe; wired into `resources/views/imports/preview.blade.php` for both `description` and `reference_number` | `StatementAdversarialTest::test_csv_formula_injection_prefixes_are_neutralized_in_the_rendered_preview` |
| Legacy XLS rejected | Shares the OLE/CFBF magic-byte signature with encrypted XLSX in `StatementFormatDetector` — both rejected identically, before any parser is invoked | `StatementUploadTest::test_legacy_xls_upload_is_gracefully_rejected` |
| Filename never determines bank profile | `BankProfileContract::matches()`/`normalize()` receive only already-read row data, never a filename; proven directly by detecting the ICICI-style profile against content taken from a file the Decision Package itself found misleadingly named "KVB_...xlsx" | `BankProfileTest::test_icici_style_profile_detects_via_content_never_filename` |
| Path traversal / non-user-controlled storage paths | `StatementImportService::storeFile()` always generates `statements/{user_id}/{account_id}/{uuid}.{ext}`, ignoring the client filename entirely | `StatementAdversarialTest::test_a_path_traversal_filename_never_influences_the_generated_storage_path` |

## 6. Duplicate/Identity Behavior — Evidence

`StatementDuplicateTest` (7 tests): same-file identical rows both preserved; cross-import identical primary reference → `FAILED`, zero rows persisted; cross-import primary reference with conflicting transaction data → `FAILED` with a distinct "conflict" message; fallback-fingerprint collision alone → `POTENTIAL_DUPLICATE`, batch reaches `PREVIEW_READY`, row persisted; different references with an identical fallback fingerprint → both preserved (never treated as a confirmed duplicate); no primary reference present at all → never auto-rejected; a `POTENTIAL_DUPLICATE` row remains staged, unchanged, after the batch is confirmed to `COMPLETED`.

## 7. Profile Selection — Evidence

`BankProfileTest` (13 tests) proves each real profile's `matches()`/`normalize()` behavior in isolation, including that the ICICI-style profile detects the exact content structure found inside the misleadingly-named sample, plus the `ProfileRegistry` mechanism itself (auto-select on one match, empty on zero matches, and — using two deliberately-colliding stub profiles bound only for this test, since the two real profiles never collide with each other — that multiple matches return every candidate without either being auto-parsed). `StatementProfileSelectionTest` (3 tests) proves the same mechanism end-to-end over HTTP: an ambiguous upload halts at `UPLOADED`/`parser_profile = null` and renders both candidate labels; an explicit selection that validates proceeds past `UPLOADED`; an explicit selection that fails structural validation is rejected and the import is marked `FAILED`.

## 8. Tenant Isolation — Evidence

`StatementOwnershipTest` (7 tests): guest redirected to `/login` from every import route; uploading against a foreign `account_id` → 403, zero rows created; viewing, downloading, selecting a profile for, and confirming another user's import batch each → 403; import history lists only the authenticated user's own imports. `StatementAdversarialTest::test_raw_files_are_never_publicly_downloadable` additionally proves the raw file is never written to the `public` disk and that the download route requires authentication.

## 9. Phase Boundary — Evidence

`PhaseBoundaryTest` (3 tests): `Transaction`/`LedgerEntry` row counts are asserted identical immediately after upload (`PREVIEW_READY`) and again after confirmation (`COMPLETED`); zero `ReconciliationMatch` rows exist after a full lifecycle; the imported account's own `AccountBalanceService::calculate()` result is byte-for-byte identical before and after the import — proving the certified balance-calculation service was neither modified nor its output altered by any Phase 7 write. A direct `grep` for `::create(`, `::update(`, `::delete(`, `::save(` against `Transaction`/`LedgerEntry`/`PaymentObligation` inside `app/Domain/Services/StatementImportService.php` and `app/Jobs/ParseStatementImportJob.php` returns zero matches.

## 10. Certified Phase 1–6 File Boundary

`git diff --stat` against every file the Decision Package §16 explicitly forbids (`Transaction.php`, `LedgerEntry.php`, `AccountBalanceService.php`, `TransactionService.php`, `ReportingService.php`, `BudgetService.php`) is empty. Additionally confirmed empty for `TransferService.php`, `RefundService.php`, `ReversalService.php`, `SafeToSpendService.php`, `PaymentObligationService.php`, `ObligationAllocationService.php`, `MonthlyGenerationService.php`, and `Money.php` — none of which the Decision Package names, but none of which was touched either. The two files modified outside the explicitly-named set (`OwnershipGuard.php`, `bootstrap/app.php`) are addressed in the Implementation Report section 4/6 as additive, precedented, non-forbidden changes.

## 11. Git State (this run)

```
Branch: phase1-audit

Modified:
 M app/Domain/Services/OwnershipGuard.php
 M app/Models/StatementImport.php
 M app/Models/StatementTransaction.php
 M bootstrap/app.php
 M resources/views/layouts/app.blade.php
 M routes/web.php

New migrations:
?? database/migrations/2026_08_12_000200_alter_statement_imports_table_for_phase7.php
?? database/migrations/2026_08_12_000210_alter_statement_transactions_table_for_phase7.php

Other untracked (new): app/Domain/Exceptions/StatementImportException.php, app/Domain/Services/StatementImportService.php,
  app/Domain/Statements/ (14 files), app/Http/Controllers/StatementImportController.php,
  app/Http/Requests/Statements/ (2 files), app/Jobs/ParseStatementImportJob.php,
  app/Policies/StatementImportPolicy.php, resources/views/imports/ (4 files),
  tests/Feature/Statements/ (9 files), tests/Unit/Domain/Statements/ (3 files),
  docs/knowledge_base/phase_decisions/PHASE_7_DECISION_PACKAGE.md (pre-existing, not created this session)
```

Nothing has been staged, committed, or pushed.

## 12. Certification

**PHASE 7 IMPLEMENTATION COMPLETE — AWAITING INDEPENDENT SOURCE-CODE ADVERSARIAL REVIEW.**
