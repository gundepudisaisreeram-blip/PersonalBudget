# PHASE 7 IMPLEMENTATION REPORT — Bank Statement Import & Parsing

## 1. Document Control

- **Phase:** 7 — Bank Statement Import & Parsing
- **Authority:** `PHASE_7_DECISION_PACKAGE.md` v1.5.3 (all business decisions — OD1–OD6, OD10, OD11 — resolved; implementation authorized by explicit instruction "STATUS: IMPLEMENTATION AUTHORIZED AFTER FINAL v1.5.3 ADVERSARIAL GO", superseding the package's own prior "NOT AUTHORIZED" final-status line).
- **Status:** Implementation complete. Full regression suite green. Pint clean. Nothing committed, nothing pushed.
- **Companion document:** `PHASE_7_EXTERNAL_AUDIT_BUNDLE.md`.

## 2. Scope Implemented

- Secure CSV and XLSX upload, with content-based (never filename-based) format/profile detection.
- The frozen Hybrid profile-selection state machine (zero/one/many matches).
- Two real, verified bank profiles: Kotak Mahindra Bank CSV (`kotak_csv_v1`) and the ICICI-style XLSX structure found inside the sample misleadingly named "KVB_...xlsx" (`icici_style_xlsx_v1`).
- Legacy XLS: rejected outright (shares the OLE/CFBF magic-byte signature with encrypted XLSX, so both are rejected identically).
- PDF: `PdfExtractorContract` exists as an abstraction only — no concrete implementation, no OCR, no table extraction.
- Queued lifecycle: UPLOAD → IMPORT BATCH CREATED → QUEUED → PARSE + NORMALIZE → VALIDATE → PREVIEW_READY → USER CONFIRMS → COMPLETED, with FAILED at any parse/validation failure.
- Duplicate/idempotency handling: exact-file replay rejection, primary-reference confirmed-duplicate detection (strict all-or-nothing), fallback-fingerprint duplicate-candidate flagging (never a failure, never silently discarded).
- Private, tenant-scoped, indefinitely-retained raw file storage with no V1 delete path.
- Import history, upload, ambiguous-profile-selection, preview, and confirmation UI.

**Not implemented, per the frozen scope boundary:** legacy XLS, OFX, QIF, PDF table extraction/OCR, reconciliation matching/execution, automatic matching, confidence scoring, linking `StatementTransaction` to `Transaction`, any `Transaction`/`LedgerEntry`/`PaymentObligation` mutation, forecasting, goals, Safe-to-Spend changes, Phase 8/9 functionality.

## 3. Files Created

```
app/Domain/Exceptions/StatementImportException.php
app/Domain/Services/StatementImportService.php
app/Domain/Statements/Contracts/BankProfileContract.php
app/Domain/Statements/Contracts/PdfExtractorContract.php
app/Domain/Statements/Contracts/StatementFileReaderContract.php
app/Domain/Statements/Dto/NormalizedStatement.php
app/Domain/Statements/Dto/NormalizedStatementRow.php
app/Domain/Statements/Exceptions/StatementParseException.php
app/Domain/Statements/Exceptions/UnsupportedStatementFormatException.php
app/Domain/Statements/ProfileRegistry.php
app/Domain/Statements/Profiles/IciciStyleXlsxProfile.php
app/Domain/Statements/Profiles/KotakCsvProfile.php
app/Domain/Statements/Readers/CsvFileReader.php
app/Domain/Statements/Readers/XlsxFileReader.php
app/Domain/Statements/StatementFormatDetector.php
app/Domain/Statements/Support/CsvFormulaGuard.php
app/Http/Controllers/StatementImportController.php
app/Http/Requests/Statements/SelectStatementProfileRequest.php
app/Http/Requests/Statements/UploadStatementRequest.php
app/Jobs/ParseStatementImportJob.php
app/Policies/StatementImportPolicy.php
database/migrations/2026_08_12_000200_alter_statement_imports_table_for_phase7.php
database/migrations/2026_08_12_000210_alter_statement_transactions_table_for_phase7.php
resources/views/imports/create.blade.php
resources/views/imports/index.blade.php
resources/views/imports/preview.blade.php
resources/views/imports/show.blade.php
tests/Feature/Statements/Concerns/BuildsStatementFixtures.php
tests/Feature/Statements/PhaseBoundaryTest.php
tests/Feature/Statements/StatementAdversarialTest.php
tests/Feature/Statements/StatementDuplicateTest.php
tests/Feature/Statements/StatementLifecycleTest.php
tests/Feature/Statements/StatementOwnershipTest.php
tests/Feature/Statements/StatementProfileSelectionTest.php
tests/Feature/Statements/StatementSchemaTest.php
tests/Feature/Statements/StatementUploadTest.php
tests/Unit/Domain/Statements/BankProfileTest.php
tests/Unit/Domain/Statements/CsvFileReaderTest.php
tests/Unit/Domain/Statements/XlsxFileReaderTest.php
```

This is a superset of the Decision Package §16 "Expected new files" list (which named representative files, e.g. "Parsers (ParserFactory, CsvParser, XlsxParser, BankProfiles/*)" generically) — every named category is present; `ParserFactory` is realized as `ProfileRegistry`, and the parser abstraction is split into `Contracts` (interfaces), `Readers` (format-level), `Profiles` (bank-level), and `Dto` (normalized output), per §8's required RAW FILE READING → PROFILE DETECTION → BANK PROFILE → NORMALIZATION → VALIDATION → STAGING separation.

## 4. Files Modified

```
app/Domain/Services/OwnershipGuard.php   — +1 method, assertStatementImportOwnership()
app/Models/StatementImport.php           — Phase 7 field/status constants (see section 6)
app/Models/StatementTransaction.php      — Phase 7 field/status constants (see section 6)
bootstrap/app.php                        — +1 exception render() for StatementImportException, identical pattern to every prior phase's domain exception
resources/views/layouts/app.blade.php    — +1 "Import" nav link
routes/web.php                           — +7 import routes, +1 controller import
```

**On modifying `OwnershipGuard.php` and `bootstrap/app.php`:** neither is in the Decision Package §16 forbidden-files list. Both changes are strictly additive, in the exact shape every prior phase has used to introduce its own new owned entity (`assertAccountOwnership`, `assertBudgetOwnership`, etc.) and its own new domain exception (`BudgetException`, `AllocationException`, etc.) — no existing method, route, or exception handler was touched. This is not a change to certified Phase 1–6 financial architecture; it is the same incremental-growth pattern that architecture has followed in every phase.

## 5. Migrations

Two new, additive migrations (§12, "MIGRATION AUTHORIZED FOR IMPLEMENTATION"):

- `2026_08_12_000200_alter_statement_imports_table_for_phase7.php` — renames `bank` → `parser_profile` (nullable, to represent an ambiguous-content import awaiting user selection), adds `error_payload` (json, nullable), redefines `status` to the frozen eight-value lifecycle enum, and replaces the `(user_id, file_hash)` unique constraint with `(account_id, file_hash)` per §12.
- `2026_08_12_000210_alter_statement_transactions_table_for_phase7.php` — adds `account_id` (nullable — see the deviation note below), renames `normalized_hash` → `row_fingerprint` (nullable) and `reference` → `reference_number`, and adds the two required non-unique composite indexes `(account_id, row_fingerprint)` and `(account_id, reference_number)`.

**No other table was touched.** No new table was created. `database/migrations/` shows exactly these two new files and zero modifications to any existing migration file, per this repository's established precedent of never editing an already-run migration.

## 6. Documented Deviation: `account_id` / `row_fingerprint` Are Nullable, Not `NOT NULL`

This is the one genuine implementation-time constraint this phase encountered, and it is fully disclosed here rather than silently worked around.

**Finding:** `tests/Feature/Database/ConstraintTest.php::test_reconciliation_matches_statement_transaction_id_is_unique` and `tests/Feature/Ownership/TenantIsolationTest.php::test_composite_foreign_key_rejects_a_cross_tenant_reconciliation_match_at_the_database_level` — both certified Phase 1–6 tests, both explicitly forbidden to modify — construct a bare `StatementTransaction` row using the table's original Phase 1 shape (no `account_id`, and using the pre-rename field name `normalized_hash`) purely as an unrelated foreign-key fixture for testing `reconciliation_matches` constraints. They predate Phase 7 and cannot know about its new columns.

**Resolution:** `account_id` and `row_fingerprint` are added/renamed as nullable at the schema level, rather than `NOT NULL`. This is a deliberate, minimal concession forced by the "do not modify Phase 1–6 test files" constraint, not a weakening of Phase 7's own guarantees — `ParseStatementImportJob` (the only Phase 7 code path that writes `StatementTransaction` rows) always populates both fields unconditionally; the relaxation exists solely so an untouchable legacy fixture from a different phase does not fail an `INSERT` on a column it has no knowledge of. `reference_number` required no such change — it inherited nullability from the original `reference` column, which was already nullable.

This was not a STOP-condition-triggering contradiction in the Decision Package itself (§12 never states these two columns must be `NOT NULL`, only that they must exist and be indexed) — it was a genuine, narrow schema-compatibility fact discovered only by running the full regression suite, resolved without touching either the Decision Package or any Phase 1–6 test file.

## 7. Parser Architecture

No third-party spreadsheet or CSV library was added to `composer.json`. `CsvFileReader` streams via PHP's built-in `fgetcsv()`; `XlsxFileReader` is a from-scratch, streaming reader built only on the bundled `ext-zip` (`ZipArchive`) and `ext-xmlreader` (`XMLReader`) extensions — a deliberate choice so every XLSX security invariant (§10) is enforced directly by this codebase rather than trusted to a dependency's defaults. See `PHASE_7_EXTERNAL_AUDIT_BUNDLE.md` section 5 for the itemized security-invariant-to-implementation mapping.

`ProfileRegistry` implements the frozen Hybrid Selection state machine (§5, OD11): `detect()` runs content-only `matches()` across every registered profile for the file's detected format; zero matches is a hard rejection; exactly one auto-selects; more than one returns every candidate without ever calling `normalize()` on any of them, so an ambiguous file is never auto-parsed. `ProfileRegistry`'s constructor accepts an optional profile-list override — used only by `tests/Unit/Domain/Statements/BankProfileTest.php` and `tests/Feature/Statements/StatementProfileSelectionTest.php` to exercise the Ambiguous-Match branch, which the two real, currently-supported profiles (one CSV, one XLSX) cannot trigger against each other since they are never candidates for the same file format. Production code always uses the default two-profile registry.

## 8. Duplicate / Identity Model

Implemented exactly per §6/§9: primary identity (bank reference/cheque number) match against the same account → `CONFIRMED_DUPLICATE`, entire batch `FAILED` before any row is persisted (all-or-nothing, verified by zero `statement_transactions` rows existing for a failed batch). A primary reference that matches an existing row's reference but whose date/amount/direction differs is also `FAILED`, with a distinct "conflicting data" message. A fallback-fingerprint (`sha256(account_id|date|amount|direction|description)`) collision alone — no reliable primary reference — is classified `POTENTIAL_DUPLICATE`, persisted, surfaced in Preview, and never fails the batch or gets silently dropped; it remains staged even after confirmation. Same-file identical rows are never treated as duplicates of each other at all (no within-file dedup logic exists). The existing `duplicate_status` enum (`UNIQUE`/`POTENTIAL_DUPLICATE`/`CONFIRMED_DUPLICATE`) — already present in the dormant Phase 1 schema — is reused directly for this classification, per the Decision Package's own instruction to prefer the existing staging architecture's technical representation over inventing a new column.

## 9. Tenant Isolation

Every upload is scoped via `$user->accounts()->findOrFail` semantics (`OwnershipGuard::assertAccountOwnership`, throwing the certified `OwnershipViolationException` → 403); every import/preview/download/select-profile/confirm action is gated by the new `StatementImportPolicy` (`view`/`create`), following the exact certified Policy pattern already used by every other model; raw files live on the `local` (private) disk under `statements/{user_id}/{account_id}/{uuid}.{ext}` — an application-generated path that never incorporates the client-supplied filename, so a path-traversal filename can influence neither the storage path nor (per Symfony's own `UploadedFile::getClientOriginalName()` behavior) even the stored `original_filename` metadata.

## 10. Testing / Pint / Performance

See `PHASE_7_EXTERNAL_AUDIT_BUNDLE.md` for exact counts and evidence. Summary: full suite green (422 tests / 1084 assertions, 0 failures), Pint clean. Duplicate lookups are bulk (`whereIn`), never per-row; `StatementTransaction` rows are bulk-inserted in chunks of 500, never via a loop of individual `::create()` calls; `MAX_ROW_COUNT` (10,000) is enforced live during streaming for both CSV and XLSX, so an oversized file is rejected before the bulk of its rows are ever buffered in memory.

## 11. Phase Boundary Verification

No code path in this changeset calls `::create(`, `::update(`, `::delete(`, or `::save(` against `Transaction`, `LedgerEntry`, or `PaymentObligation` — confirmed both by direct inspection and by `PhaseBoundaryTest`, which asserts `Transaction`/`LedgerEntry` counts and the target account's own `AccountBalanceService::calculate()` result are byte-for-byte identical before and after a full upload-through-confirmation lifecycle, and that zero `ReconciliationMatch` rows are ever created.

## 12. Unresolved Findings

None outstanding. The one deviation encountered (section 6) was resolved within this phase's own scope, without a new Project Owner decision and without touching any forbidden file.

## 13. Final Status

**PHASE 7 IMPLEMENTATION COMPLETE — AWAITING INDEPENDENT SOURCE-CODE ADVERSARIAL REVIEW.**
