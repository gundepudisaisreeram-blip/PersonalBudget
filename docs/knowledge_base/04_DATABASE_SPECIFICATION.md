# 04_DATABASE_SPECIFICATION.md

## Status

Version: 1.6 FINAL
Status: APPROVED DATABASE BASELINE (FROZEN)  
Purpose: Define the logical relational model and strict data invariants. Exact migration syntax belongs to implementation.

### v1.6 amendment (2026-08-09)

Reconciles this document's field lists with the tenant-isolation columns required by `09_ERD_AND_MIGRATION_DESIGN.md` v1.3 §2.3 ("Composite foreign keys are mandatory for financial execution relationships"). `09` requires a `user_id` column on `ledger_entries`, `obligation_allocations`, `reconciliation_matches`, `statement_transactions`, and `account_reconciliations` to support composite tenant foreign keys; this column was implicit in `09`'s physical design but missing from this document's logical field lists. No entity, transaction type, relationship, or financial formula is changed. See `08_CHANGELOG.md` for the full change record.

## 1. Database Principles

- MySQL.
- All user-owned financial data is scoped by `user_id`.
- Monetary fields use `DECIMAL(15,2)`.
- Foreign keys protect relationships.
- Historical records are preserved (immutability).
- Derived/cached values are never the only source of truth.
- V2 features (Goals, Forecasting, Materialized Summaries) are explicitly excluded from this V1 baseline.

## 2. Tables

### users
Authentication owner.

Fields:
- id
- name
- email
- password
- timezone
- created_at
- updated_at

### accounts
Fields:
- id
- user_id
- name
- institution
- account_type: `ASSET` / `LIABILITY`
- subtype
- currency
- opening_balance
- opening_balance_date
- status: `ACTIVE` / `CLOSED`
- notes
- created_at
- updated_at

**Invariant:** `opening_balance` is stored as an absolute magnitude. It is interpreted according to the `account_type` (Asset = owned wealth, Liability = owed debt).

### categories
Fields:
- id
- user_id nullable for system defaults
- name
- category_type
- parent_id nullable
- icon
- is_active
- created_at
- updated_at

### category_rules
Fields:
- id
- user_id
- name
- priority
- match_type
- match_value
- bank nullable
- account_id nullable
- category_id
- is_active
- created_at
- updated_at

### recurring_payment_templates
Fields:
- id
- user_id
- name
- amount
- frequency
- due_rule
- category_id
- default_account_id nullable
- is_mandatory
- starts_on
- ends_on nullable
- status
- notes
- created_at
- updated_at

### payment_obligations
Fields:
- id
- user_id
- recurring_payment_template_id nullable
- occurrence_key nullable
- idempotency_key nullable
- category_id
- planned_account_id nullable
- period_start
- period_end
- due_date
- planned_amount
- status
- is_mandatory
- notes
- created_at
- updated_at

**Invariants:** 
- **Identity Mutual Exclusivity:** An obligation must be strictly Recurring OR One-time. 
  - *Recurring:* `recurring_payment_template_id` IS NOT NULL, `occurrence_key` IS NOT NULL, and `idempotency_key` IS NULL.
  - *One-time:* `recurring_payment_template_id` IS NULL, `occurrence_key` IS NULL, and `idempotency_key` IS NOT NULL.
- **Recurring Uniqueness:** A unique composite index on `(recurring_payment_template_id, occurrence_key)` guarantees idempotent generation.
- **One-Time Uniqueness:** A unique index on `idempotency_key` guarantees prevention of duplicate generation.

### budgets
Fields:
- id
- user_id
- category_id
- period_start
- period_end
- budget_amount
- is_mandatory_reserve
- created_at
- updated_at

### transactions
Parent actual financial events.

Fields:
- id
- user_id
- transaction_date
- transaction_type: `EXPENSE` / `INCOME` / `TRANSFER` / `REFUND` / `REVERSAL` / `ADJUSTMENT`
- description
- reference
- source: `MANUAL` / `BANK_IMPORT` / `ADJUSTMENT` / `SYSTEM`
- status: `POSTED` (Default. Transactions are historically immutable.)
- category_id nullable
- parent_transaction_id nullable
- notes
- created_at
- updated_at

**Invariants:**
- `transaction_type`: Restricted strictly to these V1 values. AI/Implementation must NOT invent additional transaction types during V1. V1 does not model new loan origination/disbursement as a transaction type; existing loan opening balances are established through account opening balances or controlled adjustments.
- `source` represents origin data path, not whether a user or system initiated it.
- `category_id`: V1 supports one primary category per transaction. Future transaction splitting must be implemented via a dedicated allocation model, not by altering this table structure.
- `parent_transaction_id`: Used explicitly for Refund and Reversal lineage. Generic/polymorphic link tables are forbidden.
- **V1 Ledger Movement Patterns:** Implementation MUST generate exact ledger entries based on the transaction type:
  - **Expense:** 1 ledger entry, OUTFLOW.
  - **Income:** 1 ledger entry, INFLOW.
  - **Transfer:** exactly 2 ledger entries, distinct accounts, same amount, one INFLOW and one OUTFLOW.
  - **Refund:** 1 ledger entry, INFLOW, mandatory parent Expense. V1 Refund can reference only an Expense.
  - **Reversal:** mirrors the parent's complete ledger-entry structure, with every direction inverted and the same amounts/accounts. Therefore a one-entry transaction produces one reversal entry, while a two-entry Transfer produces two reversal entries.
  - **Adjustment:** 1 ledger entry, INFLOW or OUTFLOW according to the correction.

### ledger_entries
Account-level financial movements.

Fields:
- id
- user_id
- transaction_id
- account_id
- direction: `INFLOW` / `OUTFLOW`
- amount
- created_at
- updated_at

**Invariants:**
- **Magnitude:** `amount` MUST be strictly > 0. The polarity of the movement is carried entirely by the `direction`.
- **Mathematical Effect:** Asset + INFLOW = Balance Increases. Asset + OUTFLOW = Balance Decreases. Liability + INFLOW = Balance Decreases (Debt reduced). Liability + OUTFLOW = Balance Increases (Debt grows).
- **Tenant isolation:** `user_id` is a denormalized column required to support the composite tenant foreign keys `(user_id, transaction_id) → transactions(user_id, id)` and `(user_id, account_id) → accounts(user_id, id)` defined in `09_ERD_AND_MIGRATION_DESIGN.md`.

### obligation_allocations
Many-to-many bridge between actual transactions and planned obligations.

Fields:
- id
- user_id
- payment_obligation_id
- transaction_id
- allocated_amount
- created_at
- updated_at

**Constraints & Invariants:**
- `allocated_amount` > 0
- **Eligibility:** Only actual transactions representing fulfillment of an obligation may be allocated. `REFUND`, `REVERSAL`, and `ADJUSTMENT` transactions cannot directly fulfill an obligation.
- **Concurrency:** Aggregate limits (sum of allocations <= planned amount) cannot be reliably enforced by standard database constraints. Implementation MUST use application-level logic wrapped in database transactions with row-level pessimistic locking (`FOR UPDATE`) to prevent race conditions.
- **Tenant isolation:** `user_id` is a denormalized column required to support the composite tenant foreign keys `(user_id, payment_obligation_id) → payment_obligations(user_id, id)` and `(user_id, transaction_id) → transactions(user_id, id)` defined in `09_ERD_AND_MIGRATION_DESIGN.md`.

### statement_imports
Fields:
- id
- user_id
- account_id
- bank
- original_filename
- storage_path
- file_hash
- file_type
- period_from nullable
- period_to nullable
- opening_balance nullable
- closing_balance nullable
- transaction_count
- status
- parser_version nullable
- error_message nullable
- created_at
- updated_at

**Invariant:** `file_hash` provides import-level idempotency to prevent uploading the same physical file twice.

### statement_transactions
Fields:
- id
- user_id
- statement_import_id
- transaction_date
- value_date nullable
- description
- reference nullable
- normalized_amount
- direction
- statement_balance nullable
- normalized_hash
- raw_data nullable
- processing_status
- categorization_status
- match_status
- duplicate_status
- suggested_category_id nullable
- confidence_score nullable
- created_at
- updated_at

**Invariants:** 
- `normalized_amount` and `direction` are the canonical source of financial truth for the parser. Original overlapping formats (debit/credit arrays or text) are preserved strictly within the `raw_data` JSON for auditing/debugging.
- `normalized_hash` is a matching signal used for candidate detection across statements. It is NOT globally unique, as legitimate identical transactions can occur.
- **Tenant isolation:** `user_id` is required to support `UNIQUE(user_id, id)`, which `09_ERD_AND_MIGRATION_DESIGN.md` requires as the referenced side of the `reconciliation_matches` composite tenant foreign key.

### reconciliation_matches
Fields:
- id
- user_id
- statement_transaction_id
- transaction_id
- match_type
- confidence_score nullable
- explanation nullable
- status
- created_at
- updated_at

**Invariants:** 
- Both `statement_transaction_id` and `transaction_id` are MANDATORY. 
- This table represents ONLY Statement Transaction (STAGED) to Actual Transaction (ACTUAL) mapping. 
- **Cardinality:** `UNIQUE(statement_transaction_id)` ensures a staged record matches exactly one actual ledger event. `transaction_id` MUST NOT be unique, permitting overlapping bank statements to correctly link to the same underlying actual transaction.
- **Tenant isolation:** `user_id` is a denormalized column required to support the composite tenant foreign keys `(user_id, statement_transaction_id) → statement_transactions(user_id, id)` and `(user_id, transaction_id) → transactions(user_id, id)` defined in `09_ERD_AND_MIGRATION_DESIGN.md`.

### account_reconciliations
Fields:
- id
- user_id
- account_id
- statement_import_id nullable
- reconciliation_date
- ledger_balance
- statement_balance
- difference
- status
- resolution_note nullable
- adjustment_transaction_id nullable
- created_at
- updated_at

### audit_logs
Fields:
- id
- user_id
- action
- entity_type
- entity_id
- old_values nullable
- new_values nullable
- metadata nullable
- ip_address nullable
- user_agent nullable
- created_at

### settings
Fields:
- id
- user_id
- key
- value
- created_at
- updated_at

**Invariant:** Settings configure user preferences but must never redefine hardcoded financial invariants or business formulas.

## 3. Relationship Summary

```text
User
├── Accounts
├── Categories
├── Category Rules
├── Recurring Payment Templates
├── Payment Obligations
├── Budgets
├── Transactions
├── Ledger Entries
├── Obligation Allocations
├── Statement Imports
├── Statement Transactions
├── Reconciliation Matches
├── Account Reconciliations
├── Audit Logs
└── Settings

Account
└── Ledger Entries

Transaction
├── Ledger Entries
├── Obligation Allocations
└── Statement/Match relationships (via Reconciliation Matches)

Recurring Template
└── Payment Obligations

Payment Obligation
└── Obligation Allocations

Statement Import
└── Statement Transactions

Statement Transaction
└── Reconciliation Matches