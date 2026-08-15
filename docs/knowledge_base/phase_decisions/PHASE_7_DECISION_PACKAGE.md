PHASE 7 DECISION PACKAGE — Bank Statement Import & Parsing

1. Document Control / Executive Summary

Phase: 7 — Bank Statement Import / Parsing

Status: PLANNING ONLY — no implementation authorized. Corrected per independent adversarial review up through v1.5.3.

Version: 1.5.3

Date: 2026-08-12

Relationship to frozen Knowledge Base: Subordinate to 00_DOMAIN_MODEL.md through 10_IMPLEMENTATION_CONTRACT.md. Fully respects the certified Phase 1–6 baseline.

Governance basis: Requires external adversarial review before implementation. All Project Owner business decisions are now resolved (v1.5.0/v1.5.1).

Executive summary: Phase 7 introduces the ingestion of external bank statement data into the system. This phase is strictly limited to extraction, validation, normalization, and staging. It serves as the prerequisite to Phase 9 (Reconciliation). v1.5.3 finalizes the duplicate-candidate lifecycle contract, aligns import-row immutability with indefinite raw-file retention and the V1 no-delete contract, and preserves the v1.5.2 profile-selection and XLSX-security invariants.

2. Phase Scope & Boundary

IN SCOPE (Phase 7):

Secure file upload and content-based format validation.

Parsing of authorized statement formats (CSV, XLSX).

Normalization of statement rows into a standard internal staging format.

Content-based bank profile detection (Hybrid UI/Auto).

Duplicate file/row detection and idempotency handling.

Queued persistence into statement_imports and statement_transactions staging tables.

User preview, confirmation, and import history UI.

PDF Parser Framework (Abstraction only).

EXPLICITLY OUT OF SCOPE (Phase Boundary):

Legacy XLS: Excluded from V1 scope.

Reconciliation Matching & Execution: Matching imported rows against existing Transaction records is exclusively Phase 9. Phase 7 MUST NOT attempt automatic matching, linking, or confidence scoring.

Ledger Mutation: Phase 7 MUST NOT create, update, or delete Transaction, LedgerEntry, or PaymentObligation records.

Forecasting & Goals: Excluded (Phase 11).

Safe-to-Spend: Excluded (Certified Phase 5 logic remains untouched).

Advanced Bank Analytics: Excluded.

Phase 1-6 Certified Logic: No modification to TransactionService, AccountBalanceService, BudgetService, or ReportingService.

3. Existing Architecture Inspection

Before drafting this package, the existing repository and schema were inspected:

Dormant Schema: 2025_01_02_000100_create_statement_imports_table.php and 2025_01_02_000110_create_statement_transactions_table.php exist. These are currently dormant and un-policed by application code.

Queue/Job Infrastructure: 0001_01_01_000002_create_jobs_table.php exists, indicating Laravel queue capability is available.

Financial Immutability: Transaction and LedgerEntry are protected by ImmutableRecordException. Phase 7 respects this by explicitly isolating its writes to staging tables.

4. Real Bank File Analysis & Supported Formats

Direct inspection of representative files yielded the following architectural requirements:

A. ICICI Profile (Legacy XLS) - OUT OF SCOPE

Finding: The ICICI sample is a legacy .xls (HTML-based Excel export).

Scope Status — FROZEN (v1.5.0, OD10 Option B): OUT OF SCOPE. V1 is aligned exactly with the roadmap's CSV/XLSX scope. The .xls file remains valid evidence for a future adapter, but doesn't silently expand V1. Uploading a legacy .xls will trigger an unsupported format error. Do not add legacy XLS to V1.

B. Kotak Profile (CSV)

Observed Format: Standard .csv.

Preamble: Rows 0–12 contain account holder name, Account No., Period, and IFSC (KKBK0007462).

Headers (Row 13): Sl. No., Transaction Date, Value Date, Description, Chq /Ref No., Amount, Dr / Cr, Balance, Dr / Cr.

Date Format: DD-MM-YY HH:MM (e.g., 30-07-26 21:44).

Amount & Direction: Single absolute Amount column with a Dr / Cr indicator column (DR = OUTFLOW, CR = INFLOW).

C. KVB / Masked Profile (XLSX)

Observed Format: .xlsx.

Finding: The filename KVB_1456...xlsx implied Karur Vysya Bank, but the internal structural metadata perfectly matched an ICICI statement format.

Architectural Rule: Filename-based bank identification is FORBIDDEN.

Final V1 Format Scope

CSV & XLSX: Fully supported for V1.

Legacy XLS: Excluded.

PDF: Restricted to a Parser Framework / Abstraction only (StatementParserContract, PdfExtractorContract). No concrete PDF table extraction or OCR is implemented in V1.

OFX / QIF: Explicitly excluded.

5. Parser Architecture & Profile Selection Contract

The parser abstraction must separate file reading from bank-specific normalization.

Profile Selection Mechanism — FROZEN (v1.5.2 Correction, OD11 Option C)

Hybrid Selection. The system must protect against ambiguous files and misleading filenames using the following deterministic state machine:

Content Detection: The system parses headers and metadata to auto-detect the bank profile. Filenames are NEVER authoritative.

Unique Profile Match: Exactly one valid profile match → automatically select and continue.

Zero Matches: Zero valid profile matches → reject the file outright as an unsupported/unrecognized format.

Ambiguous Match (Multiple): Multiple valid profile matches → automatic parsing MUST NOT proceed. The UI MUST prompt the user to explicitly select one of the matched candidates from a dropdown.

Validation: The user's explicitly selected profile is then validated against the file content. If it structurally fails to parse under the chosen profile, it is rejected.

6. Duplicate / Identity Contract (FROZEN, v1.5.1)

The system must deterministically distinguish between file identity, row fingerprint, and primary transaction reference to properly handle cross-import replays versus legitimate duplicates.

Primary Identity

Use a bank-provided transaction identifier/reference where one is reliably present.

Examples: ICICI Ref.No., ICICI Cheque Number, Kotak Chq /Ref No.

A primary reference is the preferred cross-import identity.

Secondary Identity / Fallback Fingerprint

If no reliable bank transaction identifier exists, use a normalized fallback composite. The fallback MUST be exactly:Hash(account_id, normalized_date, normalized_amount, direction, normalized_description)

The fallback fingerprint is a duplicate candidate identifier, not definitive proof of transaction identity.

Same-File Duplicates

ALLOW (FROZEN, OD5 Option A). Identical fallback fingerprints within one import must never be silently discarded. Legitimate identical transactions (e.g., buying two identical coffees on the same day) happen and must be preserved.

Cross-Import Duplicate Handling

A cross-import replay is automatically confirmed only when a reliable primary bank transaction identifier/reference matches an existing imported row for the same account and normalized transaction context.

A fallback-fingerprint match without a reliable primary reference is treated as a duplicate candidate, not an automatically confirmed duplicate.

A fallback-only collision MUST NOT by itself cause the entire import to fail, because legitimate transactions can share identical date, amount, direction, and description.

Phase 7 V1 must preserve the row and flag the collision according to the approved staging/validation behavior. It must never silently discard the financial row.

7. Import Row Immutability & Batch Correction

Imported StatementTransaction rows represent an immutable snapshot of external bank reality.

Immutability: Once persisted, a StatementTransaction is strictly read-only.

No Editing: Users MUST NOT be allowed to edit the amount, date, or description of a StatementTransaction.

No V1 Deletion: Phase 7 V1 provides no user-facing deletion operation for retained StatementImport batches or their raw files. This is consistent with OD4 indefinite retention.

Correction: If an import fails validation, the batch remains FAILED and the user uploads a corrected file as a new import. Individual rows cannot be cherry-picked for mutation.

8. Import Lifecycle & Processing Architecture

FROZEN (v1.5.0, OD1 & OD2 Option B) — Queued & Persisted.To protect against large-file timeouts and memory exhaustion, the import process is a queued state machine. Previews acquire a durable import-batch identity.

The Exact Lifecycle:

UPLOAD: File received synchronously.

IMPORT BATCH CREATED: StatementImport row created.

QUEUED: Background job dispatched. HTTP request returns immediately. UI polls for progress.

PARSE + NORMALIZE: Job executes parser based on Hybrid Profile Selection.

VALIDATE (v1.5.3 Correction): During VALIDATE, rows may be classified as VALID, CONFIRMED_DUPLICATE, or DUPLICATE_CANDIDATE. Only CONFIRMED_DUPLICATE is a strict failure under OD3. DUPLICATE_CANDIDATE is a derived Preview-validation state, not a new persisted financial state; it MUST be surfaced in Preview, MUST NOT cause row deletion, and MUST NOT cause batch failure.

PREVIEW_READY: Job completes. Rows are persisted to statement_transactions. Status updated to PREVIEW_READY. Derived duplicate-candidate findings remain available to the Preview layer for the batch.

USER CONFIRMS: User reviews staged data in UI and confirms.

COMPLETED: Status flipped to COMPLETED. (If errors occurred during validation, see Error Model).

CRITICAL BOUNDARY: No Transaction or LedgerEntry is touched at any point in this lifecycle.

9. Error / Rejection Model

FROZEN (v1.5.1 Correction, OD3 Option A) — Strict / All-or-Nothing.For a financial staging pipeline, partial imports create dangerous ambiguity around retry and duplicate handling.

If a row is confirmed invalid or confirmed to be a cross-import replay based on a reliable primary transaction identity, the entire batch transitions to FAILED.

A fallback fingerprint collision alone is not a confirmed replay and MUST NOT cause failure.

A DUPLICATE_CANDIDATE remains staged and visible in Preview; it is not silently discarded, deleted, or converted into CONFIRMED_DUPLICATE without reliable primary identity evidence.

The UI displays the error line/reason for confirmed failures. The user must provide a corrected file and initiate a new upload. No partial imports are authorized in V1.

10. File Security, Tabular Parsing Safety, & Retention

Path Traversal: Securely generated, non-user-controlled filenames.

MIME & Extension: Strict validation against allowed types (text/csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet).

CSV Formula Injection (CWE-1236): If values are rendered to the user, cells starting with =, +, -, or @ must be safely sanitized.

Malformed Input: Parsers must gracefully handle missing headers, blank rows, and quoting anomalies without 500 errors.

XLSX Security — Implementation Invariants (v1.5.2)

The XLSX parser implementation MUST explicitly defend against the following structural risks (these are mandatory security invariants, not new business decisions):

Malformed/Corrupt XLSX: Gracefully reject invalid XML or corrupted ZIP structures without crashing or throwing unhandled 500 exceptions.

ZIP/Decompression Expansion Limits: Prevent Zip Bomb (Decompression Bomb) attacks by enforcing strict expansion ratio limits during extraction.

Oversized Worksheet Dimensions: Reject files declaring artificially massive row/column dimensions that could cause memory exhaustion during array allocation.

Hidden Worksheets: The parser MUST ignore hidden worksheets; only visible, primary data sheets are evaluated for financial imports.

Formula Cells: The parser MUST extract the raw/calculated value, NEVER execute the formula itself. (Any extracted values starting with =, +, -, or @ must still follow CSV Formula Injection sanitization if rendered/exported).

Macros/Embedded Objects: The parser MUST completely ignore and strip macros (.xlsm components, VBA) and embedded objects (OLE).

Unsupported Workbook Structures: Gracefully reject password-protected or heavily encrypted workbooks that cannot be parsed.

Parser Memory Exhaustion: The parser MUST NOT load the entire DOM/XML structure into memory at once (e.g., must use a streaming XML reader for large files).

System Resource Limits — FROZEN (v1.5.0, OD6)

MAX_FILE_SIZE: 5 MB

MAX_ROW_COUNT: 10,000 rows

Original File Retention — FROZEN (v1.5.1 Correction, OD4 Option A)

RETAIN. The raw uploaded file must be retained indefinitely. Implementation constraints:

The original uploaded file is retained indefinitely in private, tenant-scoped storage.

The original filename is metadata only and MUST NOT determine the storage path.

Storage paths MUST be generated by the application and MUST NOT be user-controlled.

Direct public URLs are forbidden.

Every download/access operation MUST revalidate authenticated ownership.

Phase 7 V1 provides no user-facing deletion operation for retained statement files.

Retention MUST NOT create access to another user's file through manipulated import IDs or paths.

11. Tenant Isolation

Every import is strictly isolated to the authenticated user.

Upload Ownership: A StatementImport batch MUST belong to $user->id.

Account Ownership: The target account_id for the import MUST be verified via $user->accounts()->findOrFail($id).

File Access: Stored statement files must be gated by OwnershipGuard (see §10).

Import History: Users can only query their own statement_imports.

12. Database Gap Analysis & Constraints

MIGRATION AUTHORIZED FOR IMPLEMENTATION.

With all lifecycle decisions resolved, the dormant schema must be updated. Crucially, database indexes are NOT uniqueness constraints.

statement_imports requirements:

file_hash (string). (account_id, file_hash) -> UNIQUE (Unique constraint per authenticated account applied to reject exact identical-file replay).

parser_profile (string).

status (enum: UPLOADED, QUEUED, PARSING, VALIDATING, PREVIEW_READY, CONFIRMED, COMPLETED, FAILED).

error_payload (json, nullable).

original_filename (string).

storage_path (string) - pointing to retained raw file.

statement_transactions requirements:

row_fingerprint (string) - fallback identity hash.

reference_number (string, nullable) - primary identity.

direction (enum: INFLOW, OUTFLOW).

(account_id, row_fingerprint) -> INDEX ONLY (Non-unique index).

(account_id, reference_number) -> INDEX ONLY (Non-unique index).

Explicitly forbid uniqueness assumptions on row/reference fields.

row_fingerprint and reference_number indexes are lookup aids only; no database UNIQUE constraint may be added to either field in Phase 7 V1.

13. Resolved Decisions (Formerly Open Decisions / Blockers)

ALL PROJECT OWNER BUSINESS DECISIONS ARE RESOLVED.

OD1 & OD2 (Architecture & Concurrency): FROZEN. Queued & Persisted.

OD3 (Error / Rejection Model): FROZEN. Strict / All-or-Nothing (Fallback collision is NOT a failure).

OD4 (Original File Retention): FROZEN. Indefinite private retention.

OD5 (Duplicate Identity Policy): FROZEN. Primary identity = confirmed match. Fallback = candidate. Same-file identical rows = allowed.

OD6 (System Resource Limits): FROZEN. 5 MB / 10,000 rows.

OD10 (Legacy XLS Scope): FROZEN. OUT OF SCOPE.

OD11 (Profile Selection Mechanism): FROZEN. Hybrid Auto/UI (Zero matches = reject; Unique = auto-select; Multiple = prompt user).

14. Testing Strategy

Must be fully implemented before PR completion:

Profile Selection Tests

Exactly one match -> automatically detected and selected.

Zero matches -> file rejected as unsupported/unrecognized format.

Multiple matches -> automatic parsing halts; prompts user to select from matched candidates.

Explicit user-selected profile validates correctly, or rejects gracefully on structural failure.

Misleading filename ignored.

Duplicate & Identity Tests

Same file uploaded twice -> second upload rejected by (account_id, file_hash).

Same primary bank reference in overlapping imports -> confirmed duplicate; entire batch FAILED under OD3.

Same date + amount + direction + description, but no primary reference -> NOT automatically rejected.

Two legitimate same-day identical transactions -> both preserved.

Same fallback fingerprint in two different imports -> flagged as duplicate candidate, not silently discarded.

Different primary references but identical fallback fingerprint -> both preserved.

Same primary reference but materially conflicting transaction data -> batch FAILED; conflict exposed in error details.

Fallback collision preview -> row is visibly classified as DUPLICATE_CANDIDATE, remains staged, does not fail the batch, and is never silently discarded.

No V1 deletion path -> retained raw files and persisted imports have no user-facing delete operation.

Standard & Security Tests

Format Tests: Legacy XLS gracefully rejected. V1 CSV/XLSX parsed securely (Formula injection mitigation, MIME validation).

XLSX Security Tests: Defenses proven against Zip bombs (decompression limits), oversized dimensions, hidden worksheets (ignored), macros (ignored), password-protected/corrupt files (graceful rejection), and memory exhaustion bounds.

Tenant Isolation Tests: User A cannot upload to User B's account; User A cannot access User B's batch, preview, or raw file; foreign ID injection yields 403/404.

Phase Boundary Negative Tests: Assert Transaction and LedgerEntry count remains identical before and after import. Assert zero reconciliation matches are generated.

Regression: Prove the Phase 1–6 baseline remains 100% green.

15. Adversarial Test Matrix

Attack

Expected Result

Misleading Filename (KVB.xlsx with ICICI content)

File is parsed by ICICI profile via content sniffing, never filename.

Zero matching profiles during detection

Rejected as unsupported/unrecognized format.

Multiple matching profiles during detection

Automatic parsing halted; prompts user to select from matched candidates.

Fallback Fingerprint Collision (Two identical $5 coffees)

Candidate flagged; NOT a failure (OD3); NOT silently discarded.

Duplicate Candidate Lifecycle

Candidate remains staged and visible in Preview; no row deletion and no batch failure.

No V1 Import Deletion

No user-facing delete action exists for retained imports/raw files.

Cross-import Replay (Overlapping period with Primary Ref)

Confirmed duplicate via primary reference; batch FAILED (OD3).

Cross-tenant account ID injected during upload

Rejected via OwnershipGuard / 403 Forbidden or 404.

Cross-tenant import batch ID access

Rejected via OwnershipGuard / 404 Not Found.

Path Traversal filename (../../../etc/passwd)

Safely sanitized/ignored; framework-generated paths utilized.

Unauthorized Public Download

Rejected; file is kept in private storage, auth required.

MIME Spoofing (PHP script renamed to .csv)

Rejected via strict MIME sniffing and content validation.

Legacy XLS upload

Gracefully rejected as unsupported format per OD10.

Oversized file payload (>5MB)

Rejected immediately.

Massive row count (>10,000)

Rejected immediately.

CSV Formula Injection (`=CMD

' /C calc'!A0`)

Values sanitized/escaped before rendering to views or exporting.

XLSX Zip Bomb (Decompression Bomb)

Rejected during decompression; expansion ratio limits enforced.

XLSX Oversized Dimensions

Rejected before full array memory allocation.

XLSX Hidden Worksheet with malicious/dummy data

Hidden sheet is strictly ignored.

XLSX Macros/Embedded Objects (.xlsm / VBA)

Macros ignored; raw text extracted safely or file gracefully rejected.

Malformed/Corrupt XLSX archive

Graceful rejection (Validation Error), no 500 exception.

Password-protected XLSX

Rejected gracefully as unsupported workbook structure.

Un-authorized Ledger Mutation

Zero changes to transactions or ledger_entries tables.

Reconciliation Scope Creep

Zero attempts to match or link statement_transactions to transactions.

16. File Boundary

Expected new files:

app/Domain/Services/StatementImportService.php

app/Http/Controllers/StatementImportController.php

Form Requests (e.g., UploadStatementRequest)

Parsers (ParserFactory, CsvParser, XlsxParser, BankProfiles/*)

Queue Jobs (ParseStatementImportJob)

Blade views (imports/index, imports/create, imports/preview, imports/show)

Tests (StatementImportServiceTest, StatementAdversarialTest, StatementOwnershipTest, ParserProfileTest)

Expected modified files:

routes/web.php

resources/views/layouts/app.blade.php (Navigation)

Dormant Migrations (create_statement_imports_table, create_statement_transactions_table)

Explicitly forbidden files:

app/Models/Transaction.php

app/Models/LedgerEntry.php

app/Domain/Services/AccountBalanceService.php

app/Domain/Services/TransactionService.php

app/Domain/Services/ReportingService.php

app/Domain/Services/BudgetService.php

All Phase 1–6 test files.

17. Implementation Sequence

Read and validate frozen Phase 7 contract.

Verify current Phase 1–6 repository state.

Obtain external adversarial GO.

Implement database migrations for dormant tables.

Define import domain boundary & job infrastructure.

Implement parser abstraction & Hybrid bank profiles.

Implement validation, security limits (5MB/10k rows), and retention policies.

Implement queued persistence and strict duplicate/error handling.

Implement UI (Upload, Ambiguous Selection, Preview, Confirmation).

Implement tests.

Run full regression suite.

Run Pint/static checks.

Generate implementation report.

Generate external audit bundle.

STOP for source-code adversarial review.

Only then commit/push with explicit authorization.

18. Documentation Lifecycle

Phase 7 must use the established documentation triad:

PHASE_7_DECISION_PACKAGE.md

PHASE_7_IMPLEMENTATION_REPORT.md

PHASE_7_EXTERNAL_AUDIT_BUNDLE.md

19. Change History

v1.5.3 — Clarified DUPLICATE_CANDIDATE as a derived Preview-validation state without introducing a new financial persistence state; aligned batch correction with indefinite raw-file retention and the V1 no-delete contract; added candidate-preview and no-deletion acceptance tests; explicitly confirmed row/reference indexes remain non-unique lookup indexes only.

v1.5.2 — Refined profile selection contract (zero matches = reject, multiple = prompt). Added strict XLSX security implementation invariants (decompression limits, dimension bounds, hidden sheets, macros, memory limits) and corresponding adversarial tests.

v1.5.1 — Correction 1: Primary bank reference = confirmed identity. Fallback fingerprint = duplicate candidate only. Same-file identical rows = allowed. Cross-import primary-reference match = confirmed duplicate. Fallback-only cross-import collision = candidate, not automatic failure. No silent deletion. Correction 2: (account_id, file_hash) -> unique for exact file replay. (account_id, row_fingerprint) -> non-unique index. (account_id, reference_number) -> non-unique index. Explicitly forbid uniqueness assumptions on row/reference fields. Correction 3: Indefinite retention, private tenant-scoped storage, generated storage paths, filename is metadata only, no public URL, ownership checked on access, no deletion UI in Phase 7 V1. Correction 4: Confirmed duplicate/invalid row -> batch FAILED; fallback collision alone -> NOT failure. Correction 5: Added 7 exact duplicate/identity testing cases.

v1.5.0 — All business decisions frozen. Queued architecture mandated. Strict all-or-nothing error handling adopted. Legacy XLS excluded. Hybrid profile selection established. Allowed internal duplicate transactions while confirming strict overlap identity rejection. Defined 5MB / 10k row limits.

v1.4.0 — Finalized parser-profile selection safeguards, explicitly separated legacy XLS scope from CSV/XLSX scope, and formalized duplicate identity precedence and uniqueness semantics.

v1.2.0 — Real ICICI/Kotak/KVB statement-format analysis incorporated; CSV/XLSX V1 scope clarified; PDF parser framework boundary clarified; bank-specific parser profiles specified; duplicate model refined; XLSX security expanded; database gap analysis updated.

v1.0.0 — Initial Phase 7 Decision Package formulation.

20. Final Package Status

PHASE 7 IMPLEMENTATION: NOT AUTHORIZED

AWAITING FINAL INDEPENDENT CHATGPT/GEMINI ADVERSARIAL REVIEW