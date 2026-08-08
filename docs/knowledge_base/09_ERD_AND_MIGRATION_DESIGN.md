# 09_ERD_AND_MIGRATION_DESIGN.md

## Status

**Version:** 1.3 PROPOSED FOR GEMINI REVIEW  
**Status:** REVIEW REQUIRED — NOT YET APPROVED FOR MIGRATION IMPLEMENTATION  
**Purpose:** Translate `04_DATABASE_SPECIFICATION.md v1.5 FINAL` into a physical MySQL relational design while explicitly defining database-enforced invariants, Laravel application-enforced invariants, lifecycle transitions, tenant isolation, reconciliation semantics, and migration dependencies.

> This document is a targeted hardening revision of v1.2. It does **not** redesign the domain model, introduce new entities, add transaction types, or change financial formulas. It addresses the remaining implementation ambiguities identified during adversarial review.

---

# 1. Complete ERD / Relationship Diagram

```text
[users] 1 -- * [accounts]
[users] 1 -- * [categories]
[users] 1 -- * [category_rules]
[users] 1 -- * [recurring_payment_templates]
[users] 1 -- * [payment_obligations]
[users] 1 -- * [budgets]
[users] 1 -- * [transactions]
[users] 1 -- * [ledger_entries]
[users] 1 -- * [obligation_allocations]
[users] 1 -- * [statement_imports]
[users] 1 -- * [statement_transactions]
[users] 1 -- * [reconciliation_matches]
[users] 1 -- * [account_reconciliations]
[users] 1 -- * [audit_logs]
[users] 1 -- * [settings]

[categories] 1 -- * [recurring_payment_templates]
[categories] 1 -- * [payment_obligations]
[categories] 1 -- * [budgets]
[categories] 1 -- * [transactions]
[categories] 1 -- * [category_rules]

[accounts] 1 -- * [ledger_entries]
[accounts] 1 -- * [account_reconciliations]
[accounts] 1 -- * [statement_imports]
[accounts] 1 -- * [category_rules]
[accounts] 1 -- * [recurring_payment_templates] (default_account_id)
[accounts] 1 -- * [payment_obligations] (planned_account_id)

[transactions] 1 -- * [ledger_entries]
[transactions] 1 -- * [obligation_allocations]
[transactions] 1 -- 1 [transactions] (parent_transaction_id for Refund/Reversal)
[transactions] 1 -- * [reconciliation_matches]

[recurring_payment_templates] 1 -- * [payment_obligations]
[payment_obligations] 1 -- * [obligation_allocations]

[statement_imports] 1 -- * [statement_transactions]
[statement_imports] 1 -- * [account_reconciliations]

[statement_transactions] 1 -- 0..1 [reconciliation_matches]
```

---

# 2. Physical Design Principles

## 2.1 Monetary Precision

All monetary database values use:

```text
DECIMAL(15,2)
```

No financial calculation may rely on PHP floating-point arithmetic or JavaScript floating-point arithmetic.

## 2.2 Historical Immutability

Actual financial transactions and their ledger entries are historical facts.

The application MUST NOT silently edit or delete posted financial records.

Corrections use explicit:

- Refunds
- Reversals
- Adjustments

## 2.3 Tenant Isolation

The design uses **hybrid tenant isolation**:

### Database-enforced

Composite foreign keys are mandatory for financial execution relationships where both parent and child records are user-owned:

```text
(user_id, transaction_id)
    → transactions(user_id, id)

(user_id, account_id)
    → accounts(user_id, id)
```

### Application-enforced

Relationships that may reference global/system categories cannot use the same composite strategy because:

```text
categories.user_id IS NULL
```

represents a system category.

Laravel services MUST therefore enforce:

```text
category.user_id IS NULL
OR
category.user_id = authenticated_user_id
```

A valid numeric ID alone is never sufficient authorization.

---

# 3. Table-by-Table Physical Design

## users

```text
id          BIGINT PK
name        VARCHAR
email       VARCHAR UNIQUE
password    VARCHAR
timezone    VARCHAR DEFAULT 'Asia/Kolkata'
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

---

## accounts

```text
id                    BIGINT PK
user_id               BIGINT FK → users.id
name                  VARCHAR
institution           VARCHAR
account_type          ENUM('ASSET','LIABILITY')
subtype               VARCHAR
currency              VARCHAR DEFAULT 'INR'
opening_balance       DECIMAL(15,2) DEFAULT 0.00
opening_balance_date  DATE
status                ENUM('ACTIVE','CLOSED') DEFAULT 'ACTIVE'
notes                 TEXT NULL
created_at            TIMESTAMP
updated_at            TIMESTAMP
```

### Invariants

- `opening_balance >= 0`.
- `opening_balance` is an absolute magnitude.
- Asset balance represents owned value.
- Liability balance represents owed debt.
- Closed accounts retain historical records.
- Closed accounts cannot accept invalid new activity.

Required index:

```text
UNIQUE(user_id, id)
```

to support composite tenant FKs.

---

## categories

```text
id             BIGINT PK
user_id        BIGINT FK NULL → users.id
name           VARCHAR
category_type  VARCHAR
parent_id      BIGINT FK NULL → categories.id
icon           VARCHAR NULL
is_active      BOOLEAN DEFAULT 1
created_at     TIMESTAMP
updated_at     TIMESTAMP
```

### Ownership semantics

```text
user_id IS NULL
    = system/default category

user_id IS NOT NULL
    = user-owned category
```

### Important FK rule

Category relationships MUST use standard foreign keys for `category_id`.

They MUST NOT use:

```text
(user_id, category_id)
    → categories(user_id, id)
```

because system categories have `user_id = NULL`.

Laravel MUST enforce:

```text
category.user_id IS NULL
OR
category.user_id = authenticated_user_id
```

---

## category_rules

```text
id           BIGINT PK
user_id      BIGINT FK → users.id
name         VARCHAR
priority     INT DEFAULT 0
match_type   VARCHAR
match_value  VARCHAR
bank         VARCHAR NULL
account_id   BIGINT FK NULL
category_id  BIGINT FK
is_active    BOOLEAN DEFAULT 1
created_at   TIMESTAMP
updated_at   TIMESTAMP
```

### Application invariants

- `account_id`, when present, must belong to the authenticated user.
- `category_id` must reference either a system category or a category owned by the authenticated user.

---

## recurring_payment_templates

```text
id                   BIGINT PK
user_id              BIGINT FK → users.id
name                 VARCHAR
amount               DECIMAL(15,2)
frequency            VARCHAR
due_rule             VARCHAR
category_id          BIGINT FK
default_account_id   BIGINT FK NULL
is_mandatory         BOOLEAN DEFAULT 1
starts_on            DATE
ends_on              DATE NULL
status               ENUM('ACTIVE','CANCELLED') DEFAULT 'ACTIVE'
notes                TEXT NULL
created_at           TIMESTAMP
updated_at           TIMESTAMP
```

### Application invariants

- `category_id` must be system-owned or owned by the authenticated user.
- `default_account_id`, when present, must belong to the authenticated user.
- Cancelling a template never changes historical obligations.

---

## payment_obligations

```text
id                             BIGINT PK
user_id                        BIGINT FK → users.id
recurring_payment_template_id  BIGINT FK NULL
occurrence_key                 VARCHAR NULL
idempotency_key                VARCHAR NULL
category_id                    BIGINT FK
planned_account_id             BIGINT FK NULL
period_start                   DATE
period_end                     DATE
due_date                       DATE
planned_amount                 DECIMAL(15,2)
status                         ENUM(
                                  'PENDING',
                                  'PARTIALLY_PAID',
                                  'PAID',
                                  'SKIPPED',
                                  'CANCELLED'
                               ) DEFAULT 'PENDING'
is_mandatory                   BOOLEAN DEFAULT 1
notes                          TEXT NULL
created_at                     TIMESTAMP
updated_at                     TIMESTAMP
```

## Identity invariant

An obligation is exactly one of:

### Recurring

```text
recurring_payment_template_id IS NOT NULL
occurrence_key IS NOT NULL
idempotency_key IS NULL
```

### One-Time

```text
recurring_payment_template_id IS NULL
occurrence_key IS NULL
idempotency_key IS NOT NULL
```

MySQL `CHECK` constraint MUST enforce this mutual exclusivity.

### Uniqueness

```text
UNIQUE(recurring_payment_template_id, occurrence_key)
UNIQUE(idempotency_key)
```

### Application invariants

- `category_id` must be system-owned or owned by the authenticated user.
- `planned_account_id`, when present, must belong to the authenticated user.
- Historical obligations must survive template cancellation.

---

# 4. Payment Obligation Status Semantics

Payment Obligation status is **not an arbitrary editable field**.

For active obligations, status is derived from allocation totals.

```text
allocated total = 0
    → PENDING

0 < allocated total < planned_amount
    → PARTIALLY_PAID

allocated total = planned_amount
    → PAID
```

Explicit lifecycle states:

```text
SKIPPED
CANCELLED
```

are controlled workflow states.

## Allocation/status invariant

An active obligation cannot have:

```text
SKIPPED + active allocations
CANCELLED + active allocations
```

The service layer MUST reject such states.

## Allocation modification

If an allocation is removed or reduced, the obligation status MUST be recalculated:

```text
PAID
  ↓ allocation reduced
PARTIALLY_PAID

PARTIALLY_PAID
  ↓ all allocations removed
PENDING
```

Status must never be manually changed in a way that contradicts the allocation totals.

---

# 5. budgets

```text
id                    BIGINT PK
user_id               BIGINT FK → users.id
category_id           BIGINT FK
period_start          DATE
period_end            DATE
budget_amount         DECIMAL(15,2)
is_mandatory_reserve  BOOLEAN DEFAULT 0
created_at            TIMESTAMP
updated_at            TIMESTAMP
```

### Invariants

- Category must be system-owned or owned by the authenticated user.
- Budget belongs to the authenticated user.
- `budget_amount >= 0`.

---

# 6. transactions

```text
id                    BIGINT PK
user_id               BIGINT FK → users.id
transaction_date      DATE
transaction_type      ENUM(
                         'EXPENSE',
                         'INCOME',
                         'TRANSFER',
                         'REFUND',
                         'REVERSAL',
                         'ADJUSTMENT'
                       )
description           VARCHAR
reference             VARCHAR NULL
source                ENUM(
                         'MANUAL',
                         'BANK_IMPORT',
                         'ADJUSTMENT',
                         'SYSTEM'
                       )
status                ENUM('POSTED') DEFAULT 'POSTED'
category_id           BIGINT FK NULL
parent_transaction_id BIGINT FK NULL → transactions.id
notes                 TEXT NULL
created_at            TIMESTAMP
updated_at            TIMESTAMP
```

## Transaction invariants

### Immutability

Posted transactions are immutable.

### Category

V1 supports one primary category.

Category must be:

```text
system category
OR
authenticated user's category
```

### Parent lineage

`parent_transaction_id` is used only for:

- Refund
- Reversal

No generic polymorphic relationship is permitted.

### Parent ownership

The parent transaction MUST belong to the same authenticated user.

---

# 7. Ledger Entries

```text
id              BIGINT PK
user_id         BIGINT FK → users.id
transaction_id  BIGINT FK → transactions.id
account_id      BIGINT FK → accounts.id
direction       ENUM('INFLOW','OUTFLOW')
amount          DECIMAL(15,2)
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

## Mathematical effect

```text
ASSET + INFLOW   = balance increases
ASSET + OUTFLOW  = balance decreases

LIABILITY + INFLOW   = debt decreases
LIABILITY + OUTFLOW  = debt increases
```

## Composite tenant FKs

Both parent relationships MUST be tenant-protected:

```text
(user_id, transaction_id)
    → transactions(user_id, id)

(user_id, account_id)
    → accounts(user_id, id)
```

## Transaction movement patterns

```text
EXPENSE
    = 1 OUTFLOW

INCOME
    = 1 INFLOW

TRANSFER
    = 2 entries
      1 OUTFLOW
      1 INFLOW
      same amount
      distinct accounts

REFUND
    = 1 INFLOW
      parent must be EXPENSE

REVERSAL
    = exact mirror of parent's complete ledger structure
      every direction inverted
      same accounts
      same amounts

ADJUSTMENT
    = 1 INFLOW or OUTFLOW
```

---

# 8. obligation_allocations

```text
id                    BIGINT PK
user_id               BIGINT FK → users.id
payment_obligation_id BIGINT FK → payment_obligations.id
transaction_id        BIGINT FK → transactions.id
allocated_amount      DECIMAL(15,2)
created_at            TIMESTAMP
updated_at            TIMESTAMP
```

## Composite tenant FKs

```text
(user_id, payment_obligation_id)
    → payment_obligations(user_id, id)

(user_id, transaction_id)
    → transactions(user_id, id)
```

## Allocation constraints

```text
allocated_amount > 0
```

Aggregate allocation cannot exceed the obligation:

```text
SUM(allocated_amount) <= planned_amount
```

This requires application logic plus locking.

## Concurrency

The implementation MUST use:

```sql
SELECT ...
FROM payment_obligations
WHERE id = ?
FOR UPDATE;
```

inside a database transaction before calculating/inserting an allocation.

## Allocation eligibility

Only:

```text
EXPENSE
TRANSFER
```

may fulfill a Payment Obligation.

Prohibited:

```text
INCOME
REFUND
REVERSAL
ADJUSTMENT
```

---

# 9. statement_imports

```text
id                 BIGINT PK
user_id            BIGINT FK → users.id
account_id         BIGINT FK → accounts.id
bank               VARCHAR
original_filename  VARCHAR
storage_path       VARCHAR
file_hash          VARCHAR
file_type          VARCHAR
period_from        DATE NULL
period_to          DATE NULL
opening_balance    DECIMAL(15,2) NULL
closing_balance    DECIMAL(15,2) NULL
transaction_count  INT DEFAULT 0
status             ENUM(
                     'UPLOADING',
                     'PARSING',
                     'VALIDATING',
                     'CATEGORIZING',
                     'MATCHING',
                     'READY_FOR_REVIEW',
                     'FAILED',
                     'COMPLETED'
                   )
parser_version     VARCHAR NULL
error_message      TEXT NULL
created_at         TIMESTAMP
updated_at         TIMESTAMP
```

## Invariants

- `file_hash` is unique per user, not globally.
- Unique constraint:

```text
UNIQUE(user_id, file_hash)
```

- `account_id` must belong to the authenticated user.
- A statement import belongs to exactly one account.
- Every child Statement Transaction inherits that account.

---

# 10. statement_transactions

```text
id                    BIGINT PK
user_id               BIGINT FK → users.id
statement_import_id   BIGINT FK → statement_imports.id
transaction_date      DATE
value_date            DATE NULL
description           TEXT
reference             VARCHAR NULL
normalized_amount     DECIMAL(15,2)
direction             ENUM('INFLOW','OUTFLOW')
statement_balance     DECIMAL(15,2) NULL
normalized_hash       VARCHAR
raw_data              JSON NULL
processing_status     ENUM(
                         'STAGED',
                         'PROCESSING',
                         'READY_FOR_REVIEW',
                         'COMMITTED',
                         'IGNORED',
                         'FAILED'
                       )
categorization_status ENUM(
                         'UNCATEGORIZED',
                         'SUGGESTED',
                         'CONFIRMED'
                       )
match_status          ENUM(
                         'UNMATCHED',
                         'SUGGESTED',
                         'CONFIRMED'
                       )
duplicate_status      ENUM(
                         'UNIQUE',
                         'POTENTIAL_DUPLICATE',
                         'CONFIRMED_DUPLICATE'
                       )
suggested_category_id BIGINT FK NULL
confidence_score      INT NULL
created_at            TIMESTAMP
updated_at            TIMESTAMP
```

## Account inheritance

`statement_transactions` intentionally has **no account_id**.

The authoritative account is:

```text
statement_transactions.statement_import_id
    ↓
statement_imports.account_id
```

A Statement Transaction MUST never be assigned to another account independently.

## Canonical parser truth

The canonical normalized financial values are:

```text
normalized_amount
direction
```

Original bank-specific representations remain in:

```text
raw_data
```

for audit/debugging.

---

# 11. reconciliation_matches

```text
id                       BIGINT PK
user_id                  BIGINT FK → users.id
statement_transaction_id BIGINT FK NOT NULL
transaction_id           BIGINT FK NOT NULL
match_type               VARCHAR
confidence_score         INT NULL
explanation              VARCHAR NULL
status                   ENUM(
                           'SUGGESTED',
                           'CONFIRMED',
                           'REJECTED'
                         )
created_at               TIMESTAMP
updated_at               TIMESTAMP
```

## Composite tenant FKs

```text
(user_id, statement_transaction_id)
    → statement_transactions(user_id, id)

(user_id, transaction_id)
    → transactions(user_id, id)
```

## Cardinality

```text
UNIQUE(statement_transaction_id)
```

A Statement Transaction has at most one **current** reconciliation candidate/relationship.

`transaction_id` is intentionally NOT unique.

Therefore multiple Statement Transactions from overlapping statements may point to the same Actual Transaction.

## Rejection/replacement semantics

`REJECTED` represents rejection of the current candidate.

A rejected candidate MUST NOT permanently prevent future matching.

When a new candidate is proposed:

1. Lock the current reconciliation row if present.
2. Replace/update the current candidate inside a DB transaction.
3. Record the rejection/replacement in `audit_logs`.
4. Never create multiple current reconciliation rows for the same `statement_transaction_id`.

The reconciliation table therefore represents the **current matching state**, while audit logs preserve the history of matching decisions.

---

# 12. account_reconciliations

```text
id                         BIGINT PK
user_id                    BIGINT FK → users.id
account_id                 BIGINT FK → accounts.id
statement_import_id        BIGINT FK NULL
reconciliation_date        DATE
ledger_balance             DECIMAL(15,2)
statement_balance          DECIMAL(15,2)
difference                 DECIMAL(15,2)
status                     ENUM(
                              'MATCHED',
                              'MISMATCH',
                              'RESOLVED',
                              'NOT_VALIDATABLE'
                            )
resolution_note            TEXT NULL
adjustment_transaction_id  BIGINT FK NULL
created_at                 TIMESTAMP
updated_at                 TIMESTAMP
```

## Mathematical definition

```text
difference = ledger_balance - statement_balance
```

## Application invariants

If `statement_import_id` is present:

```text
statement_import.account_id
=
account_reconciliation.account_id
```

The statement import must belong to the same user.

If `adjustment_transaction_id` is present:

- It must belong to the same user.
- It must belong to the reconciled account.
- It must be an `ADJUSTMENT` transaction.

---

# 13. audit_logs

```text
id           BIGINT PK
user_id      BIGINT FK NULL
action       VARCHAR
entity_type  VARCHAR
entity_id    BIGINT
old_values   JSON NULL
new_values   JSON NULL
metadata     JSON NULL
ip_address   VARCHAR NULL
user_agent   VARCHAR NULL
created_at   TIMESTAMP
```

Audit logs preserve important financial workflow decisions, including:

- Rejected/replaced reconciliation candidates
- Adjustments
- Explicit corrections
- Allocation changes
- Important status transitions

Secrets MUST never be stored.

---

# 14. settings

```text
id          BIGINT PK
user_id     BIGINT FK → users.id
key         VARCHAR
value       TEXT
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

Settings may configure preferences but MUST NOT redefine financial invariants or formulas.

---

# 15. Foreign-Key Delete / Update Policies

## Financial history

Use:

```text
ON DELETE RESTRICT
```

for relationships involving:

- accounts
- transactions
- ledger_entries
- payment_obligations
- obligation_allocations
- statement_imports
- statement_transactions
- reconciliation_matches
- account_reconciliations

## Configuration

Cascade is permitted only where historical financial facts are not destroyed.

Examples:

```text
settings → user
category_rules → user/category/account
```

must be reviewed individually according to whether the operation can destroy historical financial meaning.

## User deletion

Deleting a user MUST NOT rely on database cascade deletion of financial records.

A dedicated, auditable off-boarding workflow is required.

---

# 16. Unique Constraints

Required unique constraints:

```text
users.email

statement_imports(user_id, file_hash)

reconciliation_matches.statement_transaction_id

payment_obligations(recurring_payment_template_id, occurrence_key)

payment_obligations.idempotency_key
```

Important MySQL note:

Because recurring and one-time obligation identities use nullable columns, correctness depends on the identity `CHECK` constraint plus application validation.

The implementation MUST verify MySQL behavior during migration tests rather than assuming nullable composite uniqueness alone is sufficient.

---

# 17. Database CHECK Constraints

Required checks:

```text
ledger_entries.amount > 0

obligation_allocations.allocated_amount > 0

accounts.opening_balance >= 0

payment_obligations identity mutual exclusivity
```

Identity constraint:

```sql
CHECK (
    (
        recurring_payment_template_id IS NOT NULL
        AND occurrence_key IS NOT NULL
        AND idempotency_key IS NULL
    )
    OR
    (
        recurring_payment_template_id IS NULL
        AND occurrence_key IS NULL
        AND idempotency_key IS NOT NULL
    )
)
```

Aggregate allocation limits are application-enforced with row locking.

---

# 18. Cross-Tenant FK Strategy

## Database-enforced composite relationships

### ledger_entries

```text
(user_id, transaction_id)
    → transactions(user_id, id)

(user_id, account_id)
    → accounts(user_id, id)
```

### obligation_allocations

```text
(user_id, payment_obligation_id)
    → payment_obligations(user_id, id)

(user_id, transaction_id)
    → transactions(user_id, id)
```

### reconciliation_matches

```text
(user_id, statement_transaction_id)
    → statement_transactions(user_id, id)

(user_id, transaction_id)
    → transactions(user_id, id)
```

Parent tables participating in these composite FKs require:

```text
UNIQUE(user_id, id)
```

## Standard FK + application enforcement

Category relationships use normal foreign keys because system categories have:

```text
user_id IS NULL
```

Laravel service validation is mandatory for all category references.

---

# 19. Application-Enforced Ownership Guards

Laravel services MUST validate:

### Categories

```text
category.user_id IS NULL
OR
category.user_id = authenticated_user_id
```

### Transaction lineage

```text
parent_transaction.user_id = authenticated_user_id
```

### Account references

Validate ownership for:

- recurring template default account
- planned obligation account
- category-rule account
- statement import account
- reconciliation account
- adjustment transaction account

### Reconciliation

Validate:

```text
statement_import.user_id = authenticated_user_id
statement_import.account_id = reconciliation.account_id
```

### Adjustment

Validate:

```text
adjustment.user_id = authenticated_user_id
adjustment.account_id = reconciled account
adjustment.transaction_type = ADJUSTMENT
```

---

# 20. Ledger Movement Rules

The service layer MUST generate the exact ledger footprint:

```text
EXPENSE
    1 × OUTFLOW

INCOME
    1 × INFLOW

TRANSFER
    1 × OUTFLOW
    1 × INFLOW
    same amount
    distinct accounts

REFUND
    1 × INFLOW
    parent = EXPENSE

REVERSAL
    mirror every parent ledger entry
    invert every direction
    preserve accounts and amounts

ADJUSTMENT
    1 × INFLOW or OUTFLOW
```

A Transaction and all of its Ledger Entries MUST be created atomically inside one database transaction.

---

# 21. Refund and Reversal Rules

## Refund

V1 Refund MUST:

- reference exactly one parent transaction;
- parent must be `EXPENSE`;
- belong to the same user;
- create one `INFLOW` ledger entry;
- preserve historical parent transaction.

## Reversal

V1 Reversal MUST:

- reference exactly one parent transaction;
- belong to the same user;
- copy every parent ledger entry;
- preserve the same account and amount for every copied entry;
- invert every direction.

Examples:

```text
Expense:
    Account A OUTFLOW ₹100

Reversal:
    Account A INFLOW ₹100
```

```text
Transfer:
    Account A OUTFLOW ₹100
    Account B INFLOW  ₹100

Reversal:
    Account A INFLOW  ₹100
    Account B OUTFLOW ₹100
```

---

# 22. Allocation Rules

Only:

```text
EXPENSE
TRANSFER
```

may fulfill a Payment Obligation.

The allocation amount:

```text
> 0
```

and:

```text
SUM(allocations)
<= planned_amount
```

must always hold.

Allocation changes MUST recalculate Payment Obligation status.

No allocation may be created for:

```text
INCOME
REFUND
REVERSAL
ADJUSTMENT
```

---

# 23. Lifecycle Transition Rules

## Payment Obligation

Allocation-derived states:

```text
PENDING
    ↓ allocation > 0
PARTIALLY_PAID
    ↓ allocation reaches planned_amount
PAID
```

If allocation is reduced:

```text
PAID
    ↓
PARTIALLY_PAID
```

If all allocations are removed:

```text
PARTIALLY_PAID
    ↓
PENDING
```

Explicit terminal/override states:

```text
PENDING / PARTIALLY_PAID
    ↓
SKIPPED

PENDING / PARTIALLY_PAID
    ↓
CANCELLED
```

`SKIPPED` and `CANCELLED` cannot coexist with active allocations.

---

## Statement Import

```text
UPLOADING
    ↓
PARSING
    ↓
VALIDATING
    ↓
CATEGORIZING
    ↓
MATCHING
    ↓
READY_FOR_REVIEW
    ↓
COMPLETED
```

Any processing state may transition to:

```text
FAILED
```

No completed import may silently return to parsing.

---

## Statement Transaction

```text
STAGED
    ↓
PROCESSING
    ↓
READY_FOR_REVIEW
    ↓
COMMITTED
```

or:

```text
READY_FOR_REVIEW
    ↓
IGNORED
```

Any processing state may become:

```text
FAILED
```

A committed Statement Transaction cannot return to staged processing.

---

## Account Reconciliation

```text
MATCHED
    ↓
RESOLVED

MISMATCH
    ↓
RESOLVED

MISMATCH
    ↓
NOT_VALIDATABLE
```

Status transitions are controlled by reconciliation services, not arbitrary CRUD updates.

---

# 24. Concurrency and Locking

## Obligation allocation

All allocation writes MUST occur inside:

```text
DB::transaction()
```

with:

```sql
SELECT planned_amount
FROM payment_obligations
WHERE id = ?
FOR UPDATE;
```

Then:

```text
calculate current allocation total
validate new total
insert/update allocation
recalculate obligation status
commit
```

## Reconciliation replacement

When replacing a current reconciliation candidate:

```text
BEGIN
lock current reconciliation row
validate ownership
record rejection/replacement in audit log
update current candidate
COMMIT
```

This prevents concurrent matching operations from creating conflicting current relationships.

---

# 25. Transaction Boundaries

## Ledger creation

The following MUST be atomic:

```text
Transaction
+
Ledger Entries
```

## Statement commit

The following MUST be atomic:

```text
Statement Transaction
        ↓
Actual Transaction
        ↓
Ledger Entries
        ↓
Reconciliation Match
        ↓
Statement Transaction = COMMITTED
```

If any operation fails, the entire operation rolls back.

---

# 26. Migration Dependency and Ordering

Recommended order:

```text
1.  users

2.  categories

3.  accounts

4.  category_rules

5.  budgets

6.  recurring_payment_templates

7.  payment_obligations

8.  transactions
    - self-reference parent_transaction_id

9.  ledger_entries

10. obligation_allocations

11. statement_imports

12. statement_transactions

13. reconciliation_matches

14. account_reconciliations

15. audit_logs

16. settings
```

Before writing migrations, Claude MUST validate the complete FK dependency graph and adjust migration ordering if necessary.

---

# 27. Index Strategy

Required indexes include:

```text
accounts:
    user_id
    user_id + status
    UNIQUE(user_id, id)

categories:
    user_id
    parent_id

transactions:
    user_id + transaction_date
    user_id + transaction_type
    parent_transaction_id
    UNIQUE(user_id, id)

ledger_entries:
    transaction_id
    account_id
    user_id + transaction_id
    user_id + account_id

obligation_allocations:
    payment_obligation_id
    transaction_id
    user_id + payment_obligation_id
    user_id + transaction_id

statement_imports:
    user_id + file_hash
    user_id + account_id

statement_transactions:
    statement_import_id
    user_id + transaction_date
    normalized_hash

reconciliation_matches:
    UNIQUE(statement_transaction_id)
    transaction_id
    user_id + transaction_id

account_reconciliations:
    account_id
    statement_import_id
```

The implementation may add indexes for demonstrated query needs, but must not introduce redundant indexes without justification.

---

# 28. Seed Data

Production seed data may contain only system-level defaults such as:

```text
categories.user_id = NULL
```

Never seed mock:

- accounts
- transactions
- payments
- bank statements
- balances

into production.

---

# 29. Rollback Strategy

Migration `down()` methods must:

1. Remove dependent composite foreign keys.
2. Remove dependent standard foreign keys.
3. Drop child tables before parent tables.
4. Reverse the migration dependency order.

Financial migrations must not silently destroy production data.

Rollback safety is a deployment concern; destructive production rollback requires explicit operational approval.

---

# 30. Final Pre-Migration Verification Checklist

Before generating Laravel migration PHP, Claude MUST verify:

### Schema

- [ ] Every table matches `04_DATABASE_SPECIFICATION.md v1.5 FINAL`.
- [ ] No undocumented entities.
- [ ] No undocumented transaction types.
- [ ] All monetary fields are `DECIMAL(15,2)`.

### Tenant isolation

- [ ] Composite FKs protect ledger execution relationships.
- [ ] System categories remain compatible with `user_id IS NULL`.
- [ ] Application ownership guards exist for category references.
- [ ] Parent transaction ownership is enforced.
- [ ] Account ownership is enforced.

### Financial integrity

- [ ] Ledger movement patterns are exact.
- [ ] Transfer symmetry is enforced by service logic.
- [ ] Reversal mirrors the complete parent ledger structure.
- [ ] Refund parent must be an Expense.
- [ ] Posted transactions are immutable.

### Obligations

- [ ] Recurring/one-time identity CHECK is present.
- [ ] Allocation concurrency uses `FOR UPDATE`.
- [ ] Allocation eligibility excludes Income, Refund, Reversal, Adjustment.
- [ ] Obligation status is consistent with allocation totals.
- [ ] Skipped/Cancelled obligations cannot have active allocations.

### Reconciliation

- [ ] `UNIQUE(statement_transaction_id)` exists.
- [ ] `transaction_id` is not unique.
- [ ] Rejected candidates can be replaced.
- [ ] Reconciliation history is audit logged.
- [ ] Statement import account matches reconciliation account.

### Imports

- [ ] `UNIQUE(user_id, file_hash)` exists.
- [ ] Statement Transaction inherits account from Statement Import.
- [ ] `normalized_amount` and `direction` are canonical parser values.
- [ ] Raw bank-specific values remain available through `raw_data`.

### Lifecycle

- [ ] Invalid status regressions are prevented.
- [ ] Status transitions are implemented in services, not arbitrary CRUD.
- [ ] Allocation changes trigger obligation status recalculation.

### Operations

- [ ] DB transactions protect multi-record financial operations.
- [ ] Migration dependency order is verified.
- [ ] No production mock financial seed data.
- [ ] Rollback behavior is documented.

---

# 31. Explicit Non-Goals

This migration design does NOT introduce:

- Double-entry accounting beyond the defined ledger-entry movement model.
- Transaction splitting.
- Goals.
- Forecasting.
- Materialized monthly summaries.
- Investment valuation.
- Loan principal/interest splitting.
- Advanced accounting journals.
- Generic polymorphic relationship tables.

These remain outside the V1 implementation unless separately approved.

---

# 32. Review Request for Gemini

This document is intentionally submitted for adversarial review.

Gemini MUST review this v1.3 against:

```text
00_DOMAIN_MODEL.md
01_BUSINESS_RULES.md
02_PRODUCT_SPECIFICATION.md
03_ARCHITECTURE.md
04_DATABASE_SPECIFICATION.md v1.5 FINAL
05_UI_UX_SPECIFICATION.md
06_RECONCILIATION_SPECIFICATION.md
07_DEVELOPMENT_ROADMAP.md
08_CHANGELOG.md
```

Review ONLY for:

1. Contradictions with the frozen domain/business rules.
2. MySQL constraint correctness.
3. Laravel migration feasibility.
4. Composite-FK correctness.
5. System-category compatibility.
6. Tenant isolation gaps.
7. Reconciliation cardinality and replacement behavior.
8. Obligation allocation correctness and concurrency.
9. Lifecycle/state-machine correctness.
10. Migration dependency ordering.
11. Financial immutability.
12. Any remaining ambiguity that could cause an AI coding agent to invent behavior.

### Review constraints

Do NOT:

- redesign the domain;
- introduce new entities;
- introduce new transaction types;
- change financial formulas;
- expand V1 scope;
- generate Laravel migration PHP;
- change established terminology without identifying a concrete contradiction.

If no material issues remain, return:

```text
GO — DATABASE MIGRATION DESIGN APPROVED
```

If issues remain, classify each as:

```text
P0 — Must fix before migration
P1 — Should fix before migration
P2 — Implementation detail
```

Do not declare GO until every P0 issue is resolved.
