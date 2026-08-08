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
