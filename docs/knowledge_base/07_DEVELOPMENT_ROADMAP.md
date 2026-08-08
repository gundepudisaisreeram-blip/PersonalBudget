# 07_DEVELOPMENT_ROADMAP.md

## Status

Version: 1.0  
Purpose: Define controlled implementation phases.

## Phase 0 — Specification & Architecture

### Objective

Freeze the domain, business rules, product, architecture, database, UX, and reconciliation specifications.

### Deliverables

- 00_DOMAIN_MODEL.md
- 01_BUSINESS_RULES.md
- 02_PRODUCT_SPECIFICATION.md
- 03_ARCHITECTURE.md
- 04_DATABASE_SPECIFICATION.md
- 05_UI_UX_SPECIFICATION.md
- 06_RECONCILIATION_SPECIFICATION.md

### Stop Condition

No production implementation until specifications are reviewed and approved.

---

## Phase 1 — Laravel Foundation

### Build

- Laravel application
- Authentication
- Base layout
- Bootstrap 5
- PWA shell
- Database connection
- Settings foundation
- Error handling
- Testing foundation

### Acceptance

Application boots cleanly, authentication works, database migrations run, and PWA shell is functional.

---

## Phase 2 — Accounts & Categories

### Build

- Account CRUD
- Opening balances
- Asset/liability classification
- Account closure
- Categories
- Policies
- Account detail

### Acceptance

Accounts have reproducible derived balances and authorization is enforced.

---

## Phase 3 — Transaction Ledger

### Build

- Income
- Expense
- Transfer
- Refund
- Reversal
- Adjustment
- Ledger movements
- Transaction history
- Audit logging

### Acceptance

Financial movement tests pass and transfers do not affect total personal wealth.

---

## Phase 4 — Obligations & Monthly Budget

### Build

- Recurring templates
- Payment obligations
- Idempotent generation
- Partial allocation
- Overpayment
- Skip/cancel
- Variable budgets

### Acceptance

Monthly cycle works without duplicate obligations and historical obligations remain protected.

---

## Phase 5 — Dashboard & Safe-to-Spend

### Build

- Current Asset Balance
- Pending obligations
- Pending investments
- Safe Balance
- Safe-to-Spend
- Upcoming payments
- Attention Center
- Budget snapshot

### Acceptance

All formulas are centralized and covered by tests.

---

## Phase 6 — Reports & Month Close

### Build

- Cash flow
- Budget reports
- Obligation reports
- Trends
- Monthly summaries
- Month close/review

### Acceptance

Reports reconcile to underlying ledger data.

---

## Phase 7 — Statement Import Foundation

### Build

- Import batches
- Private file storage
- CSV/XLSX pipeline
- PDF parser framework
- Normalization
- Statement validation
- Duplicate fingerprints

### Acceptance

No staged import modifies the ledger without explicit commit/link behavior.

---

## Phase 8 — Bank Adapters

Implement and verify separately:

1. ICICI
2. SBI
3. KVB
4. Kotak

### Acceptance

Each parser has representative fixtures and tests.

---

## Phase 9 — Categorization & Reconciliation

### Build

- Categorization rules
- Confidence
- Matching
- Allocation
- Existing transaction verification
- Unaccounted queue
- Duplicate review
- Balance reconciliation

### Acceptance

Reconciliation tests cover all documented scenarios.

---

## Phase 10 — Credit Cards, Loans & Investments

### Build

- Liability account UX
- Credit-card purchase/payment semantics
- Loan obligations
- Future principal/interest extensibility
- Investment accounts
- Planned investment allocation

---

## Phase 11 — Goals, Forecasting & What-If

### Build

- Goals
- Contributions
- Forecasting
- Minimum buffer
- What-if simulator

---

## Phase 12 — Notifications & PWA Hardening

### Build

- Installability
- Notifications
- Offline shell
- Online/offline indicator
- Reminder workflows

No offline financial mutation unless separately approved.

---

## Phase 13 — Production Hardening

### Verify

- Security
- Authorization
- File upload security
- Database integrity
- Financial calculations
- Reconciliation
- Parser fixtures
- Performance
- Mobile UX
- Backup/restore
- Scheduler/queue behavior
- Error handling

### Release Gate

No production release until critical tests pass and no unresolved P0/P1 financial integrity issue remains.

## Implementation Rules

For every phase:

1. Inspect existing project state.
2. Compare implementation with approved specifications.
3. Implement only phase scope.
4. Run tests.
5. Verify migrations and data integrity.
6. Report:
   - DONE
   - VERIFIED
   - IN PROGRESS
   - BLOCKED
   - NOT IMPLEMENTED
   - FILES CHANGED
   - DATABASE CHANGES
   - TEST RESULTS
   - NEXT STEP

Never silently skip acceptance criteria.
