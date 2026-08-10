# 10_IMPLEMENTATION_CONTRACT.md

## Status

**Version:** 1.0 PROPOSED FOR GEMINI REVIEW  
**Status:** REVIEW REQUIRED — EXECUTION CONTRACT ONLY  
**Purpose:** Define the mandatory execution rules for AI-assisted implementation of the Personal Budget Manager.

This document controls **HOW implementation is executed**.

It does not redefine the domain, business rules, product scope, database model, UI/UX, reconciliation model, or architecture.

---

# 1. Authority Hierarchy

The following documents are the authoritative project knowledge base.

```text
00_DOMAIN_MODEL.md
01_BUSINESS_RULES.md
02_PRODUCT_SPECIFICATION.md
03_ARCHITECTURE.md
04_DATABASE_SPECIFICATION.md
05_UI_UX_SPECIFICATION.md
06_RECONCILIATION_SPECIFICATION.md
07_DEVELOPMENT_ROADMAP.md
08_CHANGELOG.md
09_ERD_AND_MIGRATION_DESIGN.md
10_IMPLEMENTATION_CONTRACT.md
```

## Rule

If two documents appear to conflict:

1. Do NOT silently choose one.
2. Identify the conflict.
3. Stop implementation of the affected scope.
4. Report the exact conflicting rules.
5. Request an explicit decision or specification update.

The AI must never resolve a financial ambiguity by guessing.

---

# 2. Frozen Architecture Rule

After a document has been approved, its architectural decisions are frozen.

The implementation agent MUST NOT independently:

- redesign database relationships;
- introduce alternative transaction semantics;
- add transaction types;
- change financial formulas;
- change reconciliation cardinality;
- replace ledger semantics;
- introduce CQRS/event sourcing;
- introduce generic polymorphic financial relationships;
- add V2 functionality;
- bypass approved business services;
- create duplicate sources of financial truth.

If implementation reveals a genuine architectural defect:

```text
STOP
→ document the defect
→ identify affected specification
→ propose a minimal correction
→ wait for approval
→ continue only after the baseline is updated
```

---

# 3. Scope Control

Every implementation task must have an explicit scope.

Before changing code, the agent must identify:

```text
PHASE
FEATURE
FILES / MODULES AFFECTED
DATABASE IMPACT
BUSINESS RULES INVOLVED
DEPENDENCIES
TESTS REQUIRED
```

The agent must not implement unrelated improvements merely because they are visible nearby.

### Prohibited scope expansion

Do not add:

- speculative features;
- unused abstractions;
- V2 functionality;
- unnecessary packages;
- unrelated refactoring;
- cosmetic redesign outside the requested scope;
- alternative architectures.

---

# 4. Repository Inspection Before Coding

Before modifying an existing project, the agent MUST inspect:

```text
repository structure
composer.json
package.json, if present
.env.example
routes
models
controllers
services/actions
migrations
views
tests
existing configuration
existing documentation
```

The agent must determine:

```text
What already exists?
What is missing?
What depends on the requested change?
What could regress?
```

Never assume an existing file is correct merely because it exists.

---

# 5. Implementation Order

Implementation must proceed in controlled layers.

Recommended order:

```text
1. Foundation
2. Database migrations
3. Models / relationships
4. Domain services / actions
5. Validation / authorization
6. Automated tests
7. UI / controllers
8. Background jobs / imports
9. Integration testing
10. Documentation / changelog
```

The order may be adjusted only when an approved roadmap explicitly requires it.

---

# 6. Financial Code Rules

Financial calculations are safety-critical.

## Mandatory rules

- All money uses exact decimal semantics.
- Database monetary columns use `DECIMAL(15,2)`.
- PHP floats are forbidden for financial calculations.
- JavaScript floating-point arithmetic is forbidden for authoritative financial calculations.
- Database values are authoritative.
- Rounding rules must be explicit.
- No silent truncation.
- No silent currency conversion.
- No negative monetary magnitudes where the schema defines absolute amounts.

## Authoritative calculation location

Financial formulas MUST live in a centralized, testable domain/service layer.

Do not duplicate the same financial formula across:

```text
Controller
Model
Blade
JavaScript
Dashboard widget
```

The UI displays calculated results; it does not redefine them.

---

# 7. Ledger Integrity Contract

Every Actual Transaction MUST have valid Ledger Entries according to the approved transaction type.

```text
EXPENSE
    → exactly 1 OUTFLOW

INCOME
    → exactly 1 INFLOW

TRANSFER
    → exactly 2 entries
       1 OUTFLOW
       1 INFLOW
       same amount
       distinct accounts

REFUND
    → exactly 1 INFLOW
       parent = EXPENSE

REVERSAL
    → exact mirror of parent ledger structure
       same accounts
       same amounts
       directions inverted

ADJUSTMENT
    → exactly 1 INFLOW or OUTFLOW
```

Transaction creation and Ledger Entry creation MUST be atomic.

A transaction must never become visible as a valid posted financial event without its required ledger entries.

---

# 8. Historical Immutability

Posted financial transactions are immutable.

The implementation agent MUST NOT provide ordinary CRUD behavior that allows users to silently alter:

```text
transaction amount
transaction date
transaction type
ledger entries
historical obligation fulfillment
historical reconciliation facts
```

Corrections must use the approved mechanisms:

```text
Refund
Reversal
Adjustment
Audited recategorization where explicitly allowed
```

If a UI requires editing a historical record, the agent must stop and verify the approved correction semantics.

---

# 9. Obligation Allocation Contract

Allocation operations MUST execute inside a database transaction.

Before changing allocations:

```text
LOCK payment_obligation FOR UPDATE
        ↓
calculate current allocations
        ↓
validate requested allocation
        ↓
ensure total <= planned_amount
        ↓
write allocation
        ↓
recalculate obligation status
        ↓
commit
```

Valid allocation transaction types:

```text
EXPENSE
TRANSFER
```

Invalid:

```text
INCOME
REFUND
REVERSAL
ADJUSTMENT
```

The agent must never bypass the allocation service by directly inserting allocation records from controllers or UI code.

---

# 10. Reconciliation Contract

The reconciliation workflow MUST preserve the staged/actual boundary.

```text
UPLOAD
  ↓
PARSE
  ↓
NORMALIZE
  ↓
VALIDATE
  ↓
CATEGORIZE
  ↓
MATCH
  ↓
REVIEW
  ↓
COMMIT
```

A Statement Transaction MUST NOT directly affect an Account balance.

Only a committed Actual Transaction may affect the ledger.

## Reconciliation authority

`reconciliation_matches` is the authoritative current relationship between:

```text
Statement Transaction
        ↓
Actual Transaction
```

Do not recreate this relationship using:

```text
matched_transaction_id
committed_transaction_id
generic polymorphic links
```

---

# 11. Bank Import Safety

Bank statement uploads must be treated as untrusted external input.

The implementation MUST:

- validate file type;
- validate file size;
- validate parser compatibility;
- isolate bank-specific parsing;
- normalize records;
- preserve raw source data where required;
- detect duplicate imports using the approved file hash;
- detect potential duplicate transactions;
- validate opening/closing balances when possible;
- never commit imported financial data automatically without the approved review/commit workflow.

Parser failures must be visible.

No malformed record may be silently converted into a financial transaction.

---

# 12. Security Contract

Never store:

```text
Internet banking passwords
UPI PINs
OTP values
CVVs
ATM PINs
```

The implementation MUST use:

- authentication;
- authorization/policies;
- CSRF protection;
- secure sessions;
- Form Requests or equivalent validation;
- ownership checks;
- database transactions for financial operations;
- audit logging for material financial changes.

User input must never be trusted merely because it originated from an authenticated session.

---

# 13. Tenant / Ownership Contract

Every user-owned financial object must be scoped to the authenticated user.

Before using an ID supplied by the browser, verify ownership.

Examples:

```text
account_id
category_id
transaction_id
payment_obligation_id
statement_import_id
statement_transaction_id
reconciliation_match_id
```

System categories are the only intentional exception:

```text
category.user_id IS NULL
```

or:

```text
category.user_id = authenticated_user_id
```

Cross-user references are forbidden.

---

# 14. Database Change Contract

Database changes are high-risk.

Before creating or modifying migrations, the agent MUST verify:

```text
04_DATABASE_SPECIFICATION.md
09_ERD_AND_MIGRATION_DESIGN.md
existing migrations
foreign-key dependency order
indexes
unique constraints
CHECK constraints
nullable semantics
delete behavior
historical-data implications
```

Never modify an existing production migration simply because it is convenient.

Prefer a new migration for changes to an already-applied schema.

Every migration must be reversible where operationally safe.

---

# 15. Migration Verification

After migration creation, verify:

```text
php artisan migrate
php artisan migrate:fresh
php artisan migrate:rollback
php artisan migrate
```

where appropriate to the development environment.

Also verify:

- foreign keys;
- unique constraints;
- CHECK constraints;
- indexes;
- nullable fields;
- enum values;
- self-referencing relationships;
- composite foreign keys.

If the database engine behaves differently from the specification, STOP rather than weakening the invariant.

---

# 16. Model and Relationship Contract

Laravel models must reflect the approved domain vocabulary.

Use:

- Eloquent relationships;
- policies;
- Form Requests;
- services/actions for business logic;
- database transactions where required.

Do not put complex financial behavior into:

```text
Blade templates
controllers
migration files
JavaScript
```

Models may express relationships and simple domain behavior, but financial workflows belong in dedicated services/actions.

---

# 17. Controller Contract

Controllers must remain thin.

Controllers may:

```text
receive request
authorize
validate
invoke service/action
return response
```

Controllers must NOT independently implement:

- ledger mathematics;
- obligation allocation mathematics;
- reconciliation matching;
- bank parsing;
- Safe-to-Spend calculations;
- duplicate detection algorithms.

---

# 18. UI Contract

The UI must never become a second financial engine.

The UI may:

- collect user input;
- display authoritative calculations;
- show validation errors;
- show reconciliation suggestions;
- request confirmation;
- provide navigation.

The UI must NOT independently determine:

```text
account balance
Safe Balance
Safe-to-Spend
obligation status
ledger balance
reconciliation difference
```

These values come from authoritative backend calculations.

---

# 19. API / AJAX / JavaScript Contract

Any asynchronous request must use the same backend authorization and validation rules as normal HTTP requests.

Never rely on JavaScript to enforce:

```text
ownership
financial limits
allocation limits
transaction types
```

Client-side validation is UX assistance only.

Server-side validation is authoritative.

---

# 20. Testing Contract

Every financial feature requires automated tests.

At minimum test:

### Ledger

- Expense movement
- Income movement
- Transfer symmetry
- Refund
- Reversal of one-entry transaction
- Reversal of Transfer
- Adjustment

### Obligations

- Generation
- Idempotent generation
- Partial allocation
- Full allocation
- Overpayment/unallocated amount
- Allocation removal
- Status recalculation
- Skipped obligation
- Cancelled obligation

### Reconciliation

- Manual transaction matching
- Duplicate detection
- Same transaction matched by overlapping statements
- Rejected candidate replacement
- Statement balance mismatch
- Successful statement reconciliation

### Ownership

- User A cannot access User B's account.
- User A cannot allocate against User B's obligation.
- User A cannot reconcile User B's statement.
- User A cannot use User B's transaction as a parent transaction.

### Security

- Unauthorized access
- Invalid input
- CSRF
- authentication boundaries
- prohibited sensitive data storage

---

# 21. Financial Invariant Tests

Tests must verify invariants, not only UI outcomes.

Examples:

```text
Transfer:
    outbound amount == inbound amount

Transfer:
    source account != destination account

Reversal:
    reversal entries == parent entries with directions inverted

Refund:
    parent transaction type == EXPENSE

Allocation:
    SUM(allocations) <= planned_amount

Posted transaction:
    ledger structure cannot be silently modified

Statement:
    staged data cannot alter balance before commit
```

---

# 22. Definition of DONE

A task is NOT DONE merely because code was written.

A feature is `DONE` only when:

```text
[ ] Approved scope implemented
[ ] Relevant existing code inspected
[ ] No architectural assumptions invented
[ ] Validation implemented
[ ] Authorization implemented
[ ] Database changes verified
[ ] Business rules verified
[ ] Automated tests added/updated
[ ] Tests pass
[ ] Relevant regression tests pass
[ ] Error paths tested
[ ] Financial invariants verified
[ ] Documentation updated if required
[ ] Git diff reviewed
```

If any required verification is missing:

```text
Status = NOT VERIFIED
```

not `DONE`.

---

# 23. Required Implementation Report

After every implementation task, the agent MUST report:

```text
STATUS:
DONE / IN PROGRESS / BLOCKED / NOT VERIFIED

SCOPE:
<what was requested>

IMPLEMENTED:
<what was actually implemented>

FILES CHANGED:
<exact files>

DATABASE CHANGES:
<migrations/schema/indexes/constraints>

BUSINESS RULES:
<BR rules affected>

TESTS:
<tests added/changed>

VERIFICATION:
<commands executed and results>

SECURITY:
<security/ownership checks performed>

REGRESSION:
<existing functionality checked>

KNOWN LIMITATIONS:
<anything not completed>

NEXT STEP:
<only if applicable>
```

Never claim verification that was not actually performed.

---

# 24. Stop Conditions

The implementation agent MUST STOP and request clarification when:

1. Two approved documents contradict each other.
2. A financial formula is ambiguous.
3. A transaction type's ledger behavior is undefined.
4. A database relationship is unclear.
5. A migration would destroy historical data.
6. A requested feature requires changing an approved invariant.
7. A cross-user ownership path is unclear.
8. A bank parser cannot reliably determine transaction direction.
9. A statement cannot be safely normalized.
10. A financial calculation requires an undefined rounding rule.
11. A proposed package introduces architectural consequences not approved.
12. A V2 feature is requested during V1 implementation without explicit scope approval.

Do not guess.

---

# 25. Error Handling Contract

Financial errors must be explicit.

Do NOT:

```text
catch → ignore
catch → return success
catch → silently continue
```

Errors must:

- rollback relevant database transactions;
- preserve historical integrity;
- provide actionable application-level messages;
- log appropriate technical information;
- avoid exposing secrets or sensitive internals.

---

# 26. Git Discipline

Each implementation phase should produce intentional commits.

A commit should represent one coherent change.

Examples:

```text
feat: add financial database foundation

feat: implement account ledger services

test: add ledger integrity coverage

feat: implement payment obligation allocation

feat: implement statement staging pipeline
```

Do not mix:

```text
financial feature
+
unrelated UI redesign
+
dependency upgrade
```

in one commit unless explicitly required.

Before committing:

```text
git diff
git status
tests
migration status
```

must be reviewed.

---

# 27. Package Discipline

Before adding a package, the agent must answer:

```text
Why is Laravel's existing functionality insufficient?
What problem does the package solve?
Does it affect production hosting?
Does it require Node.js?
Does it introduce a long-term maintenance dependency?
```

No package is added merely because it is convenient.

The Hostinger production constraint remains:

```text
NO Node.js runtime dependency in production
```

Frontend assets must be built locally and deployed as compiled assets.

---

# 28. Performance Contract

Do not prematurely optimize.

However, avoid known financial-system hazards:

- N+1 queries;
- loading entire ledgers unnecessarily;
- recalculating large histories inside every request;
- duplicate bank parsing;
- unbounded statement imports;
- missing indexes on high-volume reconciliation queries.

Optimization must never weaken financial correctness.

---

# 29. Documentation Contract

When implementation changes approved behavior, documentation must be updated only through the approved change-control process.

The implementation agent must NOT silently modify:

```text
00–09
```

to make code appear compliant.

If a specification change is genuinely required:

```text
STOP
→ propose specification change
→ obtain approval
→ update affected document/version
→ update 08_CHANGELOG.md
→ resume implementation
```

---

# 30. AI Behavior Contract

The AI implementation agent must behave as:

```text
Senior Laravel Engineer
+
Financial Systems Engineer
+
Strict Code Reviewer
```

It must prioritize:

```text
correctness
→ financial integrity
→ security
→ maintainability
→ testability
→ simplicity
→ performance
→ convenience
```

Never reverse this order for speed.

---

# 31. No Silent Assumptions

The agent must distinguish between:

```text
SPECIFIED
INFERRED
ASSUMED
UNKNOWN
```

For normal implementation details, reasonable assumptions may be used when they cannot affect financial correctness.

For financial behavior, database integrity, reconciliation, ownership, or historical data:

```text
UNKNOWN = STOP
```

---

# 32. Implementation Phase Gate

No phase may begin until:

```text
Previous phase = VERIFIED
```

A phase may be marked:

```text
DONE
```

only after all phase-specific acceptance criteria pass.

Recommended state machine:

```text
PLANNED
   ↓
IN PROGRESS
   ↓
IMPLEMENTED
   ↓
TESTED
   ↓
VERIFIED
   ↓
DONE
```

If verification fails:

```text
TESTED
   ↓
FAILED
   ↓
IN PROGRESS
```

---

# 33. Phase 1 Execution Contract

The first implementation phase is intentionally limited to:

```text
Laravel foundation
Database migrations
Foreign keys
Indexes
CHECK constraints
Models
Relationships
System category seeds
Financial integrity tests
```

Phase 1 MUST NOT implement:

```text
Dashboard
Bank statement parsing
Reconciliation UI
Advanced analytics
Forecasting
Goals
Investment valuation
Loan principal/interest splitting
Transaction splitting
```

Phase 1 acceptance requires:

```text
[ ] Fresh database migration succeeds
[ ] Rollback/re-migration succeeds where safe
[ ] Models load correctly
[ ] Relationships work
[ ] Ownership boundaries are tested
[ ] Ledger movement invariants are tested
[ ] Obligation identity constraints are tested
[ ] Allocation locking path is tested
[ ] Reversal/refund semantics are tested
[ ] No V2 functionality introduced
```

---

# 34. Phase 1 Stop Conditions

Claude MUST STOP before proceeding beyond Phase 1 if:

- migrations fail;
- foreign keys cannot be created as specified;
- CHECK constraints do not behave as required;
- composite tenant FKs are not enforceable as designed;
- financial invariant tests fail;
- ownership tests fail;
- migration rollback would destroy required historical data;
- Laravel implementation requires an undocumented schema change.

The failure must be reported rather than patched through silently.

---

# 35. Final Execution Principle

The implementation workflow is:

```text
READ
 ↓
INSPECT
 ↓
PLAN
 ↓
IMPLEMENT
 ↓
TEST
 ↓
VERIFY
 ↓
REPORT
```

Never:

```text
GUESS
 ↓
PATCH
 ↓
DECLARE DONE
```

The Personal Budget Manager is a financial system.

**Correctness is more important than speed.**

**Verified behavior is more important than claimed completion.**

**The approved Knowledge Base is more authoritative than implementation convenience.**

---

# 36. Gemini Review Request

This document is intentionally submitted for adversarial validation.

Review `10_IMPLEMENTATION_CONTRACT.md` against the complete frozen Knowledge Base:

```text
00_DOMAIN_MODEL.md
01_BUSINESS_RULES.md
02_PRODUCT_SPECIFICATION.md
03_ARCHITECTURE.md
04_DATABASE_SPECIFICATION.md
05_UI_UX_SPECIFICATION.md
06_RECONCILIATION_SPECIFICATION.md
07_DEVELOPMENT_ROADMAP.md
08_CHANGELOG.md
09_ERD_AND_MIGRATION_DESIGN.md
```

Review specifically for:

1. Contradictions with approved domain rules.
2. Contradictions with database invariants.
3. Missing implementation stop conditions.
4. Missing financial safety controls.
5. Missing ownership/security controls.
6. Incorrect transaction/ledger semantics.
7. Incorrect obligation/allocation behavior.
8. Incorrect reconciliation behavior.
9. Unsafe migration practices.
10. Inconsistent definition of DONE.
11. AI-agent ambiguity that could cause implementation drift.
12. Scope creep between V1 and V2.
13. Laravel/Hostinger implementation conflicts.
14. Testing gaps that could allow financial defects.
15. Any rule that is too strict, redundant, contradictory, or technically impossible.

## Review constraints

Do NOT redesign the product.

Do NOT introduce new V1 features.

Do NOT change financial formulas.

Do NOT generate Laravel code.

Do NOT approve merely because the document is comprehensive.

Identify concrete contradictions or implementation risks.

Classify every finding:

```text
P0 — Must fix before implementation
P1 — Should fix before implementation
P2 — Implementation detail / optional
```

If no material issues remain, return exactly:

```text
GO — IMPLEMENTATION CONTRACT APPROVED
```

If issues remain, return:

```text
NO-GO
```

followed by the findings and exact proposed corrections.

Every development phase MUST have a corresponding version-controlled
Phase Decision Package before implementation begins.

The implementation agent MUST:

1. Read the frozen Knowledge Base.
2. Inspect the current repository/codebase.
3. Create or update the phase-specific Decision Package Markdown file.
4. Stop implementation completely.
5. Submit the Decision Package for independent external review.
6. Resolve only explicitly identified findings.
7. Obtain an external GO decision.
8. Implement strictly according to the approved Decision Package.
9. Produce an implementation report.
10. Stop for independent source-code adversarial review.
11. Only after final GO may the phase be committed.

The Decision Package is the contract between planning and implementation.

No implementation code may be written before the Decision Package
receives external approval.

If implementation discovers a contradiction between the approved
Decision Package, Knowledge Base, or existing certified architecture:

STOP.

Do not silently reinterpret the requirement.

Update the Decision Package only after the contradiction has been
reviewed and explicitly resolved.

Every material change to an approved Decision Package MUST be recorded
in the document itself and in 08_CHANGELOG.md where appropriate.

The approved Decision Package becomes frozen for that implementation
checkpoint.

File structure

docs/
└── knowledge_base/
    ├── 00_DOMAIN_MODEL.md
    ├── 01_BUSINESS_RULES.md
    ├── 02_PRODUCT_SPECIFICATION.md
    ├── 03_ARCHITECTURE.md
    ├── 04_DATABASE_SPECIFICATION.md
    ├── 05_UI_UX_SPECIFICATION.md
    ├── 06_RECONCILIATION_SPECIFICATION.md
    ├── 07_DEVELOPMENT_ROADMAP.md
    ├── 08_CHANGELOG.md
    ├── 09_ERD_AND_MIGRATION_DESIGN.md
    └── 10_IMPLEMENTATION_CONTRACT.md

    └── phase_decisions/
        ├── PHASE_1_DECISION_PACKAGE.md
        ├── PHASE_1_5_DECISION_PACKAGE.md
        ├── PHASE_2_DECISION_PACKAGE.md
        ├── PHASE_3_DECISION_PACKAGE.md
        └── PHASE_4_DECISION_PACKAGE.md

### Phase Decision Package Integrity

The Phase Decision Package MUST contain, at minimum:

- Phase objective and scope
- Explicit exclusions / out-of-scope items
- Current codebase analysis
- Relevant Knowledge Base references
- Architecture and implementation approach
- Database impact
- Routes/controllers/services/models involved
- Validation and authorization rules
- UI/UX approach where applicable
- Testing strategy
- Security and tenant-isolation considerations
- Financial/business invariants where applicable
- Exact files expected to be created or modified
- Risks and open decisions
- Acceptance criteria
- Implementation sequence
- Explicit statement that implementation MUST NOT begin until external approval

The implementation agent MUST NOT treat its own interpretation of an
ambiguous requirement as an approved decision.

Any unresolved ambiguity MUST be recorded under "Open Decisions" and
submitted for external review.

After external approval, the approved Decision Package becomes the
authoritative implementation contract for that phase.

The implementation agent MUST NOT expand the approved scope during
implementation without first stopping and obtaining approval for a
Decision Package amendment.

If the implementation differs from the approved Decision Package for
any reason, the difference MUST be explicitly reported and reviewed
before the phase can be certified.

# 37. Phase Documentation Lifecycle

Every development phase MUST maintain a complete, version-controlled
documentation trail.

Each phase MUST have the following permanent documents:

```text
docs/knowledge_base/phase_decisions/

PHASE_N_DECISION_PACKAGE.md
PHASE_N_IMPLEMENTATION_REPORT.md
PHASE_N_EXTERNAL_AUDIT_BUNDLE.md