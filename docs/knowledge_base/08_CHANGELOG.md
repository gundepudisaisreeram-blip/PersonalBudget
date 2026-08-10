# 08_CHANGELOG.md

## Purpose

Track approved changes to the Personal Budget Manager specifications and architecture.

## Versioning Rules

- MAJOR: Breaking domain or financial-rule change.
- MINOR: New capability that does not invalidate existing rules.
- PATCH: Clarification, typo, or non-semantic wording change.

## 2026-08-09 — Initial Specification Baseline

### Approved

- Four-reality domain model:
  - PLANNED
  - ACTUAL
  - STAGED
  - DERIVED
- Ledger authority for actual financial movements.
- Asset and liability account semantics.
- Payment Obligation terminology.
- Many-to-many obligation allocation.
- Credit-card purchase vs payment distinction.
- Investment transfer semantics.
- Statement staging.
- Transaction reconciliation and balance reconciliation as separate processes.
- Historical financial protection.
- Safe-to-Spend calculation framework.
- Hostinger-compatible Laravel architecture.

### Initial documentation set

- 00_DOMAIN_MODEL.md
- 01_BUSINESS_RULES.md
- 02_PRODUCT_SPECIFICATION.md
- 03_ARCHITECTURE.md
- 04_DATABASE_SPECIFICATION.md
- 05_UI_UX_SPECIFICATION.md
- 06_RECONCILIATION_SPECIFICATION.md
- 07_DEVELOPMENT_ROADMAP.md

## 2026-08-09 — Tenant-Isolation Field Reconciliation (04 ↔ 09)

### Date

2026-08-09

### Version

04_DATABASE_SPECIFICATION.md: 1.5 FINAL → 1.6 FINAL

### Change

Added `user_id` to the Fields: list of `ledger_entries`, `obligation_allocations`, `reconciliation_matches`, `statement_transactions`, and `account_reconciliations`; added `updated_at` to `ledger_entries`; marked `recurring_payment_templates.default_account_id` as `nullable`; extended the `## 3. Relationship Summary` diagram to list all six previously-omitted direct User relations (Ledger Entries, Obligation Allocations, Statement Transactions, Reconciliation Matches, Account Reconciliations, Settings).

### Reason

A Phase 1 schema audit found that `04`'s logical field lists omitted columns that `09_ERD_AND_MIGRATION_DESIGN.md` v1.3 §2.3 requires physically (composite tenant foreign keys such as `(user_id, transaction_id) → transactions(user_id, id)` cannot exist without a `user_id` column on the child table). `04`'s own Purpose statement defers "exact migration syntax" to implementation, and `09`'s stated purpose is exactly that physical translation, so this is a completeness gap in `04` rather than a genuine design disagreement. Confirmed with the project owner before editing the frozen baseline.

### Impact

Database schema only. No entity, transaction type, relationship cardinality, or financial formula changed. Six tables gain a `user_id` column (five already required it structurally for composite tenant FKs; the sixth, `recurring_payment_templates.default_account_id`, gains an explicit nullability marker matching `09`).

### Files

- 04_DATABASE_SPECIFICATION.md (v1.5 FINAL → v1.6 FINAL)

### Approval

Approved (user confirmed "09 governs; update 04 to match" during Phase 1 reconciliation)

### Migration Required

Yes — direct `user_id → users.id` foreign keys added to `ledger_entries`, `obligation_allocations`, `reconciliation_matches`; `accounts.institution`/`accounts.subtype` nullability and `accounts.currency` length corrected to match the (unchanged) existing spec for those columns.

### Implementation Status

Complete

## 2026-08-10 — Phase 4 Decision Package Created

### Date

2026-08-10

### Version

N/A (new document; not a revision to an existing 00–09 specification)

### Change

Created `docs/knowledge_base/phase_decisions/PHASE_4_DECISION_PACKAGE.md`, a version-controlled record of the Phase 4 (Budgets, Recurring Payment Templates, Monthly Obligation Generation, Payment Obligations, Obligation Allocations) architectural decisions, per the Phase Decision Package governance process added to `10_IMPLEMENTATION_CONTRACT.md`.

### Reason

`10_IMPLEMENTATION_CONTRACT.md` now requires every development phase to have a corresponding version-controlled Decision Package. No such file existed for Phase 4 at the time Phase 4 code was implemented; this entry and the accompanying document formalize those already-made, already-implemented decisions into the required artifact, retrospectively rather than in advance.

### Impact

Documentation only. No `00`–`09` specification document was changed. No PHP, Blade, migration, or test file was changed while creating this entry or the accompanying Decision Package.

### Files

- docs/knowledge_base/phase_decisions/PHASE_4_DECISION_PACKAGE.md (created)

### Approval

Pending — the decisions recorded in the Decision Package were approved by the Project Owner prior to implementation, but the independent external adversarial review (ChatGPT/Gemini) that `10_IMPLEMENTATION_CONTRACT.md`'s new process describes as a prerequisite has not occurred. See the Decision Package's own section 25 (Governance Status) for the full explanation.

### Migration Required

No

### Implementation Status

Complete (the Phase 4 code this document describes was already implemented, tested, and passing before this document was written)

## Change Entry Template

### Date

YYYY-MM-DD

### Version

X.Y.Z

### Change

Describe the change.

### Reason

Why the change was required.

### Impact

Affected domain/business/product/architecture/database behavior.

### Files

List affected specification files.

### Approval

Pending / Approved / Rejected

### Migration Required

Yes / No

### Implementation Status

Not Started / In Progress / Complete
