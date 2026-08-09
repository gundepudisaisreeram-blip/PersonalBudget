# PHASE 1 KNOWLEDGE BASE — SOURCE BUNDLE

Concatenation of all 11 Knowledge Base documents, verbatim, in canonical order.


---

## FILE: docs/knowledge_base/00_DOMAIN_MODEL.md

# 00_DOMAIN_MODEL.md

## Status

Version: 1.0  
Status: APPROVED FOUNDATION  
Purpose: Define the canonical vocabulary and domain concepts for the Personal Budget Manager.

## 1. Domain Philosophy — Four Realities

The application separates financial information into four realities:

1. **PLANNED** — what is expected or intended to happen.
2. **ACTUAL** — what definitively happened and belongs to the financial ledger.
3. **STAGED** — external data that has been imported but is not yet trusted as ledger truth.
4. **DERIVED** — calculations, summaries, matches, forecasts, and cached representations.

These realities must never be silently conflated.

## 2. Core Domain Entities

### 2.1 User

The authenticated owner of the personal financial data.

### 2.2 Account

A financial account or bucket owned/controlled by the user.

Account types include:

- Asset — bank account, cash, investment account, wallet.
- Liability — credit card, loan, other debt.

An account has an opening balance and an opening-balance date. Its authoritative balance is derived from ledger activity plus the opening balance; a cached balance may exist for performance but is never an independent source of truth.

### 2.3 Transaction

An actual financial event recorded in the ledger.

A transaction is the parent business event. Its financial impact is represented by one or more ledger movements/entries against accounts.

Transaction classifications include:

- Income
- Expense
- Transfer
- Refund
- Reversal
- Adjustment

### 2.4 Ledger Movement

The account-level financial effect produced by a transaction.

The ledger must support balanced multi-account movements where required, without requiring the user-facing application to expose full accounting terminology.

### 2.5 Income

An actual event that increases personal wealth.

A loan received is not ordinary income because the corresponding liability also increases.

### 2.6 Expense

An actual event that decreases personal wealth and/or represents consumption.

A credit-card purchase is an Expense even though cash has not yet left the bank account.

### 2.7 Transfer

Movement between user-owned accounts. It does not represent personal income or expense.

Examples:

- ICICI Savings → SBI Savings
- Savings → Credit Card
- Savings → Investment Account

### 2.8 Refund

A separate actual event linked to an original Expense and reversing its financial effect.

### 2.9 Reversal

A separate actual event that reverses a previous financial event, such as a failed or reversed bank transaction. Both original and reversal remain historically visible.

### 2.10 Adjustment

A controlled correction used to reconcile the application's ledger with verified real-world state. It requires a reason and audit history.

## 3. Planned Domain

### 3.1 Recurring Payment Template

A reusable configuration describing a recurring expected obligation.

Examples:

- Home Loan
- Car Loan
- Netflix
- Insurance
- SIP

A template can be active or cancelled. Cancellation affects future generation only.

### 3.2 Payment Obligation

A specific planned occurrence generated from a recurring template or created as a one-time planned obligation.

Examples:

- August Home Loan — ₹65,000
- 11-August Google subscription — ₹1,950

An obligation is planned, not actual.

### 3.3 Budget Category

A classification used to organize spending and planning.

Examples:

- Food
- Transport
- Shopping
- Loans
- Subscriptions
- Insurance
- Investments

### 3.4 Variable Budget

A planned spending limit for a category during a financial period.

## 4. Staged Import Domain

### 4.1 Import Batch

Metadata and processing state for an uploaded bank statement.

### 4.2 Statement Transaction

A normalized/raw transaction extracted from a bank statement. It remains STAGED until committed or linked/verified.

### 4.3 Categorization Rule

A deterministic rule used to suggest a category based on transaction attributes.

### 4.4 Import Processing State

Statement transactions may independently track:

- Processing status
- Categorization status
- Match status
- Duplicate status
- Commit status

These are not one sequential state machine.

## 5. Derived Domain

### 5.1 Account Balance

Derived from opening balance plus ledger movements.

### 5.2 Reconciliation Allocation

A linkage between actual transaction(s) and planned obligation(s), carrying an allocated amount.

The relationship is many-to-many:

- One transaction can fulfill multiple obligations.
- Multiple transactions can fulfill one obligation.

### 5.3 Transaction Reconciliation

Determines whether a staged statement transaction corresponds to an existing actual transaction or should create a new actual transaction.

### 5.4 Balance Reconciliation

Compares calculated ledger balance with a trusted statement closing balance.

### 5.5 Financial Period

A reporting/budget boundary, normally a calendar month. A transaction's default period is derived from transaction date.

## 6. Lifecycle Semantics

### Payment Obligation

```text
GENERATED → PENDING
PENDING → PARTIALLY_PAID
PARTIALLY_PAID → PAID
PENDING → PAID
PENDING → SKIPPED
PARTIALLY_PAID → SKIPPED
```

Skipped obligations remain historically visible.

### Recurring Template

```text
ACTIVE → CANCELLED
```

Historical obligations remain unchanged.

### Statement Transaction

Processing dimensions are independent:

```text
processing_status:
STAGED / PROCESSING / READY_FOR_REVIEW / COMMITTED / IGNORED / FAILED

categorization_status:
UNCATEGORIZED / SUGGESTED / CONFIRMED

match_status:
UNMATCHED / SUGGESTED / CONFIRMED

duplicate_status:
UNIQUE / POTENTIAL_DUPLICATE / CONFIRMED_DUPLICATE
```

## 7. Domain Invariants

1. Only ACTUAL ledger movements affect authoritative account balances.
2. STAGED statement transactions cannot directly affect balances.
3. A transfer between two user-owned accounts must have balanced outbound/inbound movements of equal amount.
4. Transfers are not personal income or expense.
5. A credit-card purchase is an Expense; paying the credit card is a Transfer.
6. Investment contributions can be Transfers while simultaneously fulfilling planned investment obligations.
7. Historical obligations are independent of later template changes.
8. Statement imports must not silently create duplicate ledger transactions.
9. Overpayment beyond an obligation must remain separately identifiable.
10. Refunds and reversals preserve links to the events they reverse.
11. Adjustments never silently rewrite historical transactions.
12. Derived balances must be reproducible from authoritative records.
13. Historical financial events remain auditable.

## 8. Terminology Rules

Do not use:

- "Payment" to mean a generic expense.
- "Transfer" to mean a merchant payment.
- "Ledger" to mean future budget/planning data.
- "Balance" as an independent editable truth when it is derived.

Preferred terms:

- Payment Obligation = planned expected payment.
- Transaction = actual financial event.
- Transfer = internal movement between user-owned accounts.
- Statement Transaction = staged external bank record.
- Account Balance = derived/cached financial position.

## 9. Important Domain Examples

### Partial payment

Obligation ₹19,159:

- Transaction A ₹10,000 allocated.
- Remaining obligation ₹9,159.
- Status PARTIALLY_PAID.

### Overpayment

Obligation ₹19,159; actual transaction ₹19,200:

- Allocate ₹19,159 to obligation.
- Remaining ₹41 is unallocated and requires review.
- Never silently absorb the ₹41.

### Credit card

Purchase ₹5,000:

- Expense.
- Credit-card liability increases.

Bill payment ₹5,000:

- Transfer.
- Bank asset decreases.
- Credit-card liability decreases.

### Investment

Savings → Mutual Fund ₹10,000:

- Transfer between asset accounts.
- May fulfill a planned investment obligation.
- Not an expense for wealth/cash-flow classification.

## 10. Scope Boundary

This model does not require full enterprise accounting, CQRS, Event Sourcing, or a public banking integration. It provides the minimum robust domain semantics required for accurate personal cash-flow management, bank reconciliation, budgeting, and forecasting.


---

## FILE: docs/knowledge_base/01_BUSINESS_RULES.md

# 01_BUSINESS_RULES.md

## Status

Version: 1.0  
Status: APPROVED BASELINE  
Purpose: Define the financial and logical rules that implementation must obey.

## A. Money & Precision

**BR-001 — Currency:** All monetary values are INR unless future multi-currency support is explicitly approved.

**BR-002 — Precision:** Monetary database columns use DECIMAL(15,2) unless a documented domain requirement justifies another precision.

**BR-003 — Floating-Point Ban:** PHP floats and JavaScript floating-point arithmetic must not be authoritative for financial calculations. Server/database values are authoritative.

**BR-004 — Monetary Sign Convention:** Store monetary amounts as non-negative magnitudes where practical and derive direction from transaction/ledger semantics. Any signed presentation must be derived consistently.

## B. Account Rules

**BR-005 — Asset Accounts:** Savings, current accounts, cash, wallets, and investments may be represented as assets. Positive balance represents owned value.

**BR-006 — Liability Accounts:** Credit cards, loans, and other debts are liabilities. Positive balance represents amount owed.

**BR-007 — Account Opening State:** Every account has an opening balance and opening-balance date. Opening state must be auditable.

**BR-008 — Account Isolation:** Closed accounts cannot accept current/future-dated transactions, but historical transactions can be imported/reconciled when they fall within the account's valid historical period.

## C. Transaction & Ledger Rules

**BR-009 — Ledger Authority:** Actual ledger movements are the authoritative source for account balances.

**BR-010 — Balanced Movement:** Transactions affecting multiple accounts must produce balanced account movements appropriate to their semantics.

**BR-011 — Income:** Income increases personal wealth. A loan received is not ordinary income because it simultaneously creates/increases a liability.

**BR-012 — Expense:** Expense represents consumption or a decrease in personal wealth. Credit-card purchases are Expenses even though bank cash leaves later.

**BR-013 — Transfer:** A transfer between user-owned accounts is not income or expense. It must have equal outbound/inbound amounts.

**BR-014 — Refund:** A refund is a separate transaction linked to its original Expense and reduces the effective net expense/budget utilization where applicable.

**BR-015 — Reversal:** A reversal is a separate transaction linked to the original event. Both remain historically visible and their combined effect may net to zero.

**BR-016 — Adjustment:** An adjustment requires amount, account, date, reason, and audit information. It corrects verified discrepancies without rewriting history.

## D. Planned Obligations

**BR-017 — Payment Obligation:** Recurring templates generate Payment Obligations. One-time obligations may also be created directly.

**BR-018 — Generation:** Active recurring templates generate obligations for their applicable periods. Generation must be idempotent; running it multiple times must not create duplicates.

**BR-019 — Partial Allocation:** If an obligation is ₹19,159 and ₹10,000 is allocated to it, status is PARTIALLY_PAID and ₹9,159 remains outstanding.

**BR-020 — Overpayment:** If an obligation is ₹19,159 and actual transaction is ₹19,200, allocate exactly ₹19,159 to the obligation. The remaining ₹41 remains separately identifiable and requires review.

**BR-021 — Skipped:** A skipped obligation remains historically visible but is excluded from active pending-obligation calculations.

**BR-022 — Cancellation:** Cancelling a recurring template prevents future generation but never changes historical obligations.

## E. Budget Rules

**BR-023 — Fixed Commitments:** Fixed commitments are represented by Payment Obligations such as EMIs and subscriptions.

**BR-024 — Variable Budgets:** Variable budgets define category spending limits for a financial period.

**BR-025 — Budget Utilization:** Category utilization is based on net eligible expenses in the period. Transfers and pure adjustments do not count. Linked refunds reduce net utilization.

**BR-026 — Budget Overspend:** Actual spending may exceed budget. Reporting must show the negative variance/overspend, even if Safe-to-Spend uses a non-negative remaining-budget reserve.

**BR-027 — Remaining Variable Budget:** For Safe-to-Spend, remaining budget for a category is MAX(Budget - eligible actual utilization, 0).

## F. Balance & Safe-to-Spend

**BR-028 — Current Asset Balance:** Current Asset Balance is the sum of derived balances of asset accounts. Liability balances are excluded from available cash.

**BR-029 — Pending Mandatory Fixed Obligations:** Sum of outstanding amounts on mandatory Payment Obligations in the relevant current period.

**BR-030 — Pending Mandatory Investments:** Sum of outstanding amounts on mandatory planned investment obligations in the relevant current period.

**BR-031 — Safe Balance:**  
`Safe Balance = Current Asset Balance - Pending Mandatory Fixed Obligations - Pending Mandatory Investments`

**BR-032 — Safe-to-Spend:**  
`Safe-to-Spend = Safe Balance - Remaining Variable Budget Reserve`

**BR-033 — Variable Budget Reserve:** The reserve is the sum of remaining amounts across variable budgets that are intended to be honored within the period.

**BR-034 — Negative Safe-to-Spend:** The system must allow a negative derived Safe-to-Spend value and clearly flag it as a shortfall. It must not silently clamp the displayed financial result to zero.

## G. Loans, Credit Cards & Investments

**BR-035 — Credit Card Spending:** Credit-card purchase is an Expense and increases liability.

**BR-036 — Credit Card Payment:** Paying a credit card from an asset account is a Transfer that decreases the asset and decreases the liability. It is not a new Expense.

**BR-037 — Loan EMI V1:** V1 may treat an EMI as one cash-flow expense for budgeting. The domain must preserve the ability to split principal, interest, and fees later without corrupting historical transactions.

**BR-038 — Investment Transfer:** Moving cash into an investment asset is a Transfer. It may simultaneously fulfill a planned investment obligation.

**BR-039 — Loan Proceeds:** Loan proceeds are not personal income; they increase an asset and a liability.

## H. Bank Statement Rules

**BR-040 — Import Staging:** Uploaded statements are parsed into STAGED Statement Transactions and do not affect the ledger until committed or explicitly linked to an existing ledger transaction.

**BR-041 — Supported Formats:** Initial supported formats are PDF, CSV, and XLSX.

**BR-042 — Statement Balance Capture:** Opening and closing balances should be captured when available for validation, but are not themselves ledger transactions.

**BR-043 — Statement Validation:** When reliable opening/closing balances and complete transaction coverage are available: Opening Balance + net statement movements = Closing Balance. Mismatch must be flagged.

**BR-044 — Pending Bank Transactions:** Transactions explicitly marked pending/processing must not be committed as cleared actual transactions until they clear, unless the product explicitly introduces pending-ledger support.

**BR-045 — Import Idempotency:** Re-uploading the same statement or overlapping statement periods must not create duplicate ledger transactions.

## I. Reconciliation Rules

**BR-046 — Transaction Reconciliation:** Determines whether a Statement Transaction matches an existing actual transaction, fulfills a planned obligation, or should create a new actual transaction.

**BR-047 — Balance Reconciliation:** Independently compares calculated ledger balance with trusted bank statement closing balance.

**BR-048 — Matching Signals:** Matching may use exact amount, transaction direction, account, date proximity, reference, and normalized description similarity.

**BR-049 — Default Date Tolerance:** Default automatic matching date tolerance is ±3 calendar days unless bank-specific rules justify another value.

**BR-050 — Exact Amount:** Automatic matching should require exact amount unless an explicit reconciliation rule permits a difference.

**BR-051 — Many-to-Many Allocation:** One actual transaction may fulfill multiple Payment Obligations and multiple actual transactions may fulfill one Payment Obligation through explicit allocation records.

**BR-052 — Allocation Limit:** Total allocation against a transaction cannot exceed the transaction's allocatable amount. Total allocation against an obligation cannot exceed the obligation amount.

**BR-053 — Existing Transaction Match:** If a Statement Transaction matches an existing manual/ledger transaction, the system verifies/links it rather than creating a duplicate ledger transaction.

**BR-054 — Unaccounted Transaction:** A staged bank transaction without a valid existing match becomes a review candidate for categorization and potential ledger commitment.

## J. Corrections & Historical Integrity

**BR-055 — Historical Protection:** Changing a template, category rule, or current configuration must not silently rewrite committed historical transactions.

**BR-056 — Audited Recategorization:** Historical recategorization is allowed only through an explicit user action and must be auditable.

**BR-057 — Correction Preference:** Prefer reversal, refund, adjustment, or controlled correction mechanisms over destructive edits when the change represents a financial event.

**BR-058 — Auditability:** Financially significant changes must be traceable to user/action/time where practical.

## K. Period Rules

**BR-059 — Transaction Period:** A transaction's default Financial Period is derived from its transaction date, regardless of import date.

**BR-060 — Obligation Period:** Payment Obligations belong to the period for which they were planned/due.

**BR-061 — Month Boundary:** Month-end and year-end calculations must use the user's configured application timezone and explicit period boundaries.

## L. Statement & Account Reconciliation

**BR-062 — Statement Reconciliation:** If a statement provides a closing balance, the system should compare it with the calculated ledger balance for the same account and effective date.

**BR-063 — Balance Difference:** A balance mismatch must remain visible until resolved, explained, or explicitly adjusted.

**BR-064 — Adjustment Traceability:** Any balance adjustment created to resolve a mismatch must contain a reason and audit trail.

## M. Product Safety Rule

**BR-065 — No Silent Financial Assumptions:** If an implementation decision changes financial meaning, the system must not invent a rule silently. It must use an approved business rule or stop for clarification.


---

## FILE: docs/knowledge_base/02_PRODUCT_SPECIFICATION.md

# 02_PRODUCT_SPECIFICATION.md

## Status

Version: 1.0  
Status: APPROVED PRODUCT BASELINE  
Purpose: Define what the user can do and what the application must provide.

## 1. Product Vision

A mobile-first PWA that replaces manual Samsung Notes budget tracking with a reliable personal cash-flow system.

The product must make it easy to answer:

1. How much money do I have?
2. How much is committed?
3. What has been paid?
4. What is pending?
5. What is coming next?
6. How much can I safely spend?
7. What transactions need my attention?
8. Does my ledger agree with my bank statements?

## 2. Product Scope

### V1 Core

- Authentication
- Accounts
- Categories
- Recurring Payment Templates
- Payment Obligations
- Variable Budgets
- Manual Transactions
- Transfers
- Dashboard
- Safe-to-Spend
- Monthly cycle
- Reports
- PWA shell

### V1.5

- Bank statement import
- Statement staging
- Categorization rules
- Duplicate detection
- Transaction reconciliation
- Balance reconciliation

### V2

- Advanced credit-card/loan/investment analytics
- Goals
- Forecasting
- What-if analysis
- Advanced notifications

## 3. First-Time Onboarding

### Step 1 — Create accounts

Capture:

- Account name
- Institution
- Account type
- Opening balance
- Opening balance date
- Optional account notes

### Step 2 — Create categories

Provide sensible defaults and allow customization.

### Step 3 — Configure recurring obligations

Capture:

- Name
- Amount
- Frequency
- Due date/day
- Category
- Default account
- Mandatory flag
- Start date
- Optional end date

### Step 4 — Configure variable budgets

Capture category and period budget.

### Step 5 — Review dashboard

Show initial balance, commitments, budgets, and Safe-to-Spend.

## 4. Navigation

### Desktop

- Dashboard
- Accounts
- Transactions
- Budget & Obligations
- Statements
- Reconciliation
- Reports
- Settings

### Mobile

Bottom navigation:

`Dashboard | Budget | Add | Activity | More`

## 5. Dashboard

### Purpose

Single screen for current financial health.

### Required cards

- Current Asset Balance
- Pending Mandatory Obligations
- Pending Mandatory Investments
- Safe Balance
- Safe-to-Spend

### Required sections

#### Attention Center

Examples:

- Unaccounted transactions
- Statement balance mismatch
- Missing obligation
- Overpayment difference
- Overdue obligation
- Budget overspend

#### Upcoming

Show next 7/14 days of Payment Obligations.

#### Monthly commitment progress

Paid / Partially Paid / Pending / Skipped.

#### Budget snapshot

Show configurable top categories with budget, actual, remaining, utilization.

#### Account snapshot

Show balances by account, especially the accounts funding upcoming obligations.

## 6. Accounts

### User actions

- Add account
- Edit account
- Close account
- View account
- Set opening balance
- View ledger
- Reconcile balance

### Account detail

Show:

- Derived balance
- Account type
- Opening balance
- Recent transactions
- Upcoming obligations assigned to account
- Statement reconciliation status

## 7. Transactions

### List

Search/filter by:

- Date
- Account
- Category
- Type
- Amount
- Description
- Source
- Reconciliation status

### Create transaction

Types:

- Income
- Expense
- Transfer
- Refund
- Reversal
- Adjustment

### Mark obligation paid

This action must never simply change status.

User must either:

- Create an actual transaction, or
- Link an existing transaction.

### Transaction detail

Show:

- Date
- Amount
- Account(s)
- Type
- Category
- Description
- Source
- Linked obligation(s)
- Allocation amounts
- Linked statement transaction
- Refund/reversal relationship
- Audit information where applicable

## 8. Budget & Obligations

### Recurring Templates

Actions:

- Create
- Edit
- Cancel
- View historical obligations

Deleting historical templates is prohibited when history exists; cancellation is used instead.

### Payment Obligations

Show:

- Due date
- Planned amount
- Allocated actual amount
- Remaining amount
- Status
- Account
- Category

Statuses:

- Pending
- Partially Paid
- Paid
- Skipped
- Cancelled where appropriate

### Variable budgets

Show:

- Budget
- Actual
- Remaining
- Variance
- Utilization

Overspend must remain visible.

## 9. Bank Statement Import

### Supported

- PDF
- CSV
- XLSX

### Upload flow

```text
Select Bank
→ Select Account
→ Select File
→ Upload
→ Parse
→ Validate
→ Categorize
→ Match
→ Review
```

### Upload screen

Capture:

- Bank
- Account
- File
- Optional period
- Optional statement password only for in-memory processing if technically required; never persist it.

### Processing states

- Uploading
- Parsing
- Validating
- Categorizing
- Matching
- Ready for Review
- Failed

### Statement validation

Show:

- Statement period
- Opening balance if available
- Closing balance if available
- Parsed transaction count
- Balance validation result
- Warnings

## 10. Statement Review

Separate the following concepts:

### Already matched

Statement transaction corresponds to an existing ledger transaction. Do not create another transaction.

### New candidate

Statement transaction has no existing match and may become a new ledger transaction.

### Potential duplicate

Possible duplicate requires review.

### Needs review

Low-confidence category/match or data-quality issue.

Actions:

- Confirm match
- Create transaction
- Edit category
- Ignore
- Mark duplicate
- Link obligation
- Split allocation where supported

## 11. Transaction Reconciliation

Dedicated screen showing:

- Matched
- Suggested matches
- Unmatched
- Potential duplicates
- New candidates
- Missing obligations
- Overpayment differences

Every suggested match should explain the signals used where practical.

## 12. Balance Reconciliation

Account-level workflow:

```text
Ledger Balance
vs
Statement Closing Balance
```

Show:

- Ledger balance
- Statement balance
- Difference
- Last reconciled date
- Unresolved discrepancy
- Adjust/review action

## 13. Categorization

Support:

- Default categories
- User rules
- Suggested categories
- Confidence
- Manual confirmation
- Future-rule learning from corrections

Rules must be editable and disableable.

## 14. Credit Cards

Support liability accounts.

### Purchase

Expense on credit-card account.

### Payment

Transfer from asset account to credit-card liability.

Dashboard must distinguish cash available from outstanding credit-card liability.

## 15. Loans

V1 supports loan obligations and cash-flow tracking.

An EMI can be represented as one Payment Obligation and one Expense for budgeting.

Architecture must not prevent future principal/interest/fee allocation.

## 16. Investments

Investment accounts can be assets.

An investment contribution:

- Creates a Transfer from funding account to investment account.
- May fulfill a planned investment obligation.
- Is not an Expense merely because cash moved.

## 17. Monthly Cycle

### Beginning of period

- Generate applicable Payment Obligations idempotently.
- Activate period budgets.

### During period

- Record actual transactions.
- Fulfill obligations through allocation.
- Monitor budgets and Safe-to-Spend.

### End of period

- Import statements.
- Reconcile transactions.
- Reconcile balances.
- Review unresolved items.
- Generate monthly summary.

### Month close

Closing a month creates a clear reporting/audit point. It must not destroy the ability to make explicitly audited corrections later.

## 18. Reports

Required reports:

### Cash Flow

- Opening balance
- Income
- Expenses
- Transfers
- Investments
- Closing balance

### Budget

- Budget
- Actual
- Remaining
- Variance
- Utilization

### Obligations

- Planned
- Paid
- Partially Paid
- Pending
- Skipped
- Overdue

### Reconciliation

- Imported
- Matched
- Unmatched
- Duplicates
- Unresolved
- Balance differences

### Trends

Monthly income, expenses, savings/investments, and balance trends.

## 19. Search & Filtering

All large datasets must support server-side pagination and practical filtering.

## 20. Notifications

Future-capable notifications include:

- Upcoming obligation
- Budget warning
- Unaccounted transaction
- Reconciliation mismatch
- Statement processing result

## 21. PWA

V1:

- Installable
- Responsive
- Offline shell
- Cached static assets
- Clear offline indicator

No offline financial mutation in V1.

## 22. Global UI States

Important screens must define:

- Loading
- Empty
- Success
- Validation error
- System error
- Partial processing
- Permission denied

## 23. Accessibility

Use semantic HTML, keyboard accessibility, visible focus states, meaningful labels, and sufficient contrast.

## 24. Product Acceptance Principles

A feature is product-complete only when:

- User workflow is clear.
- Financial rules are respected.
- Validation exists.
- Error/empty states exist.
- Mobile behavior is defined.
- Relevant acceptance criteria are testable.


---

## FILE: docs/knowledge_base/03_ARCHITECTURE.md

# 03_ARCHITECTURE.md

## Status

Version: 1.0  
Status: ARCHITECTURE BASELINE  
Purpose: Define how the Laravel application is structured and how domain responsibilities are separated.

## 1. Architecture Principles

1. Domain/business rules are authoritative.
2. Controllers orchestrate; they do not own financial calculations.
3. Models represent persistence and relationships.
4. Services/Actions own meaningful business operations.
5. Financial calculations are centralized and testable.
6. Staged imports are isolated from the actual ledger.
7. Database transactions protect multi-record financial operations.
8. Historical data is preserved.
9. External bank parsing is adapter-based.
10. No unnecessary infrastructure.

## 2. Technology

- Laravel 13
- PHP 8.3+
- MySQL
- Blade
- Bootstrap 5
- Vanilla JS / Alpine.js where justified
- Chart.js
- PWA
- Hostinger shared hosting

Production must not require Node.js.

## 3. Suggested Laravel Layers

```text
HTTP
 ├── Controllers
 ├── Form Requests
 └── Policies

Application
 ├── Actions
 ├── Services
 └── Jobs

Domain-oriented services
 ├── Accounts
 ├── Transactions
 ├── Obligations
 ├── Budgets
 ├── Statements
 ├── Categorization
 ├── Reconciliation
 └── Forecasting

Persistence
 ├── Eloquent Models
 ├── Migrations
 └── Database

Presentation
 └── Blade + Bootstrap
```

Do not create unnecessary repositories/interfaces merely for abstraction.

## 4. Core Services

Expected services include:

- AccountBalanceService
- TransactionService
- TransferService
- PaymentObligationService
- BudgetService
- SafeToSpendService
- StatementImportService
- StatementValidationService
- CategorizationService
- ReconciliationService
- BalanceReconciliationService
- MonthlyGenerationService
- ReportingService

Exact names may change if implementation demonstrates a better coherent structure.

## 5. Transaction Boundary Rules

Use database transactions for:

- Transfers
- Creating a transaction with multiple ledger movements
- Allocating transactions to obligations
- Committing statement transactions
- Adjustments
- Reversals/refunds where multiple records must remain consistent

Failure must roll back the complete financial operation.

## 6. Ledger Architecture

A Transaction is a business event.

Ledger movements/entries represent account-level effects.

The design must support:

- One-account effects where appropriate.
- Two-account transfers.
- Asset/liability movements.
- Future loan principal/interest splits.

The implementation does not need a full general-ledger accounting UI.

## 7. Planned vs Actual vs Staged

Planned records never affect actual account balances.

Staged records never affect actual account balances.

Actual ledger movements affect derived balances.

Derived values must never become a second source of truth.

## 8. Monthly Generation

Monthly generation must:

1. Determine applicable active templates.
2. Determine target financial period.
3. Calculate due date.
4. Create obligation only if an equivalent obligation does not already exist.
5. Preserve historical instances.
6. Be safe to run repeatedly.

The scheduler and a manual recovery action may invoke the same service.

## 9. Statement Parser Architecture

Use a common parsing contract and bank-specific adapters.

Conceptually:

```text
StatementParser
├── ICICIParser
├── SBIParser
├── KVBParser
└── KotakParser
```

A parser should produce normalized statement transactions independent of UI/database details.

Parser failures must be explicit.

## 10. Statement Pipeline

```text
Upload
→ Secure Storage
→ Detect/Confirm Bank
→ Parse
→ Normalize
→ Validate
→ Deduplicate
→ Categorize
→ Match
→ Review
→ Commit/Link/Ignore
```

No direct import-to-ledger shortcut.

## 11. Reconciliation Architecture

Separate:

### Transaction Reconciliation

Statement transaction ↔ existing ledger transaction and/or planned obligation.

### Balance Reconciliation

Ledger balance ↔ statement closing balance.

Do not merge these into one status.

## 12. Categorization Architecture

Rules are deterministic and ordered.

A rule may use:

- Normalized description
- Reference
- Bank
- Account
- Direction
- Amount range where appropriate

Rules produce suggestions with confidence/priority.

User corrections can create/update rules through explicit actions.

## 13. Authorization

All user-owned financial resources must be scoped to the authenticated user.

Policies should protect:

- Accounts
- Transactions
- Obligations
- Budgets
- Statements
- Reports
- Goals

Never trust IDs supplied by the browser.

## 14. File Security

Uploaded bank statements must:

- Be validated by MIME/type and size.
- Be stored privately.
- Never be executable.
- Not be directly publicly accessible.
- Have controlled lifecycle/retention.

## 15. PWA Architecture

Use:

- Web manifest
- Service worker
- Cache static assets
- Offline fallback

No offline mutation synchronization in V1.

## 16. Scheduler / Queue

Use Laravel Scheduler for recurring generation and scheduled processing.

Use database-backed queues initially if asynchronous processing is required and Hostinger supports the required setup.

Design jobs to be idempotent.

## 17. Error Handling

Financial operations should fail loudly and safely.

User-facing errors should be understandable without exposing sensitive internals.

Technical details should be logged securely.

## 18. Observability

Maintain:

- Application logs
- Financial audit logs
- Import processing status
- Failed job information
- Reconciliation history

Never log secrets or bank credentials.

## 19. Architecture Boundaries

Do not add:

- Microservices
- External event buses
- CQRS
- Event sourcing
- Full accounting ERP features
- Direct bank API integration

unless explicitly approved by a later architecture decision.

## 20. Testing Architecture

Use unit tests for deterministic calculations and feature/integration tests for:

- Transactions
- Transfers
- Obligations
- Statement import
- Reconciliation
- Authorization
- Database integrity

## 21. Deployment

The production application must be deployable to Hostinger shared hosting with:

- PHP
- MySQL
- Composer-compatible dependencies
- Prebuilt frontend assets
- Laravel scheduler/cron where available

No production Node runtime.

## 22. Architecture Decision Records

Significant changes to this architecture should be recorded in the changelog or an architecture decision record with:

- Decision
- Reason
- Alternatives
- Consequences
- Approval/status


---

## FILE: docs/knowledge_base/04_DATABASE_SPECIFICATION.md

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

---

## FILE: docs/knowledge_base/05_UI_UX_SPECIFICATION.md

# 05_UI_UX_SPECIFICATION.md

## Status

Version: 1.0  
Purpose: Define the user experience and interaction model.

## 1. UX Principles

1. Mobile-first.
2. Fast daily review.
3. Financially meaningful numbers first.
4. Clear planned vs actual distinction.
5. Never hide reconciliation problems.
6. Minimize repetitive data entry.
7. Use plain language.
8. Destructive financial actions require confirmation.
9. No financial mutation should appear successful while offline.

## 2. Global Layout

### Desktop

Sidebar:

- Dashboard
- Accounts
- Transactions
- Budget & Obligations
- Statements
- Reconciliation
- Reports
- Settings

### Mobile

Bottom navigation:

`Dashboard | Budget | Add | Activity | More`

Global Add button:

- Income
- Expense
- Transfer
- Payment
- Adjustment

## 3. Dashboard

### Hero

Show:

- Current Asset Balance
- Safe Balance
- Safe-to-Spend

### Secondary metrics

- Pending mandatory obligations
- Pending mandatory investments
- Paid obligations
- Variable budget reserve

### Attention Center

Priority order:

1. Balance mismatch
2. Failed import
3. Unaccounted transaction
4. Missing/overdue obligation
5. Overpayment difference
6. Budget overspend

### Upcoming

Next 7/14 days with due date, name, amount, account, status.

## 4. Accounts Screen

Card/list:

- Account name
- Institution
- Type
- Balance
- Last reconciled

Filters:

- Asset
- Liability
- Active
- Closed

Account detail:

- Balance
- Upcoming obligations
- Transactions
- Reconciliation
- Account settings

## 5. Transaction Screen

Quick-add form should be optimized for mobile.

Required:

- Type
- Amount
- Date
- Account
- Category when relevant
- Description

Transfer requires:

- From account
- To account
- Amount

Adjustment requires:

- Account
- Amount
- Date
- Reason

Refund/reversal requires appropriate original transaction linkage.

## 6. Obligation Screen

Show:

- Due date
- Planned amount
- Allocated amount
- Remaining amount
- Status
- Account
- Category

Primary actions:

- Mark paid/create transaction
- Link existing transaction
- Allocate transaction
- Skip
- View history

Never allow a status-only "Paid" operation without an actual financial linkage.

## 7. Budget Screen

For each category:

- Budget
- Actual
- Remaining
- Variance
- Utilization

Use clear warning states for overspending.

## 8. Statement Upload

Fields:

- Bank
- Account
- File
- Optional period

Accepted:

- PDF
- CSV
- XLSX

Display processing progress.

## 9. Statement Review

Tabs:

- All
- Needs Review
- Suggested Matches
- Matched
- Potential Duplicates
- New Candidates
- Ignored

Each row should show:

- Date
- Description
- Amount
- Direction
- Suggested category
- Match status
- Confidence

Actions:

- Confirm
- Edit
- Match
- Create transaction
- Ignore
- Duplicate

## 10. Reconciliation

### Transaction reconciliation

Show side-by-side:

```text
Statement transaction
vs
Existing transaction / obligation
```

Show match explanation.

### Balance reconciliation

Show:

```text
Ledger balance
Statement closing balance
Difference
```

Provide review/adjust workflow.

## 11. Reports

Charts should have accessible tabular summaries.

Required:

- Cash flow
- Category spending
- Budget variance
- Obligation status
- Reconciliation
- Monthly trends

## 12. Forms & Validation

Use inline validation.

Never clear unrelated fields after validation errors.

Amounts must use currency-aware input formatting without compromising server-side decimal validation.

## 13. Empty States

Examples:

Dashboard:

> Set up your first account to begin.

Transactions:

> No transactions for this period.

Statements:

> No statements awaiting review.

Reconciliation:

> Everything is reconciled.

## 14. Error States

Financial errors must explain:

- What failed
- Whether data was saved
- What the user should do next

Never show a success state if the database operation failed.

## 15. Loading States

Use skeletons/spinners for:

- Dashboard
- Reports
- Statement parsing
- Large transaction lists

## 16. Accessibility

- Semantic controls
- Labels
- Keyboard navigation
- Focus states
- Screen-reader-friendly status text
- Color must not be the only indicator

## 17. Responsive Requirements

Primary daily workflows must be fully usable on small Android screens.

Tables should transform into cards or horizontally scroll only when necessary.

## 18. PWA

- Installable
- Responsive
- Offline shell
- Static asset caching
- Online/offline status
- No offline mutation in V1

## 19. Confirmation Rules

Require confirmation for:

- Delete/cancel operations affecting future planning
- Account closure
- Ignore statement batch
- Commit large import batch where appropriate
- Adjustments
- Historical recategorization

## 20. UX Acceptance

Every primary screen must have:

- Loading
- Empty
- Success
- Validation error
- System error
- Mobile layout
- Accessibility support


---

## FILE: docs/knowledge_base/06_RECONCILIATION_SPECIFICATION.md

# 06_RECONCILIATION_SPECIFICATION.md

## Status

Version: 1.0  
Purpose: Define bank statement import, transaction matching, allocation, and balance reconciliation.

## 1. Scope

Two independent processes exist:

1. Transaction Reconciliation
2. Balance Reconciliation

They must never be represented as the same status.

## 2. Import Pipeline

```text
Upload
→ Validate
→ Parse
→ Normalize
→ Validate statement
→ Deduplicate
→ Categorize
→ Match
→ Review
→ Commit/Link/Ignore
```

## 3. Supported Banks

Initial adapters:

- ICICI
- SBI
- KVB
- Kotak

Formats:

- PDF
- CSV
- XLSX

The parser layer must be bank-specific and output a common normalized structure.

## 4. Normalized Statement Transaction

Minimum normalized fields:

- transaction_date
- value_date if available
- description
- reference
- amount
- direction
- statement_balance if available
- source bank
- source account
- import batch

## 5. Statement Validation

When opening and closing balances are present:

```text
Opening Balance
+ Credits
- Debits
= Closing Balance
```

If the statement provides enough information to validate the period and the result does not match:

`Statement Balance Mismatch`

must be recorded.

Opening/closing rows are not ledger transactions.

## 6. Duplicate Detection

Use normalized fingerprints incorporating appropriate combinations of:

- Account
- Date
- Amount
- Direction
- Reference
- Normalized description

Do not rely solely on description.

Test:

- Same file twice
- Overlapping statements
- Same transaction across periods
- Manual transaction already present
- Slight description differences

## 7. Categorization

Rule priority:

1. Exact user rule
2. Specific merchant rule
3. Bank/account-specific rule
4. General normalized description rule
5. Default suggestion

Every suggestion should carry a confidence/priority.

Low-confidence transactions remain in review.

## 8. Transaction Matching

Potential match signals:

- Exact amount
- Same account
- Direction
- Date proximity
- Reference
- Description similarity
- Existing source

Default date tolerance:

±3 calendar days.

Amount must match exactly for automatic matching unless an approved rule says otherwise.

## 9. Match Outcomes

### Confirmed Existing Transaction

Statement verifies an already recorded transaction.

Do not create a duplicate.

### New Candidate

No existing match. User can create a new actual transaction.

### Obligation Match

Actual transaction may fulfill one or more Payment Obligations through allocations.

### Potential Duplicate

Requires user review.

### Ignored

User intentionally dismisses the staged record.

## 10. Allocation

Bridge actual transactions to planned obligations.

Examples:

### One transaction → multiple obligations

```text
Transaction ₹6,000
├── Obligation A ₹3,000
└── Obligation B ₹3,000
```

### Multiple transactions → one obligation

```text
Obligation ₹10,000
├── Transaction A ₹5,000
└── Transaction B ₹5,000
```

Never allocate beyond either side's allocatable amount.

## 11. Overpayment

Obligation ₹19,159.

Actual ₹19,200.

System:

```text
Allocate ₹19,159
Remaining ₹41
```

The ₹41 remains unallocated for review.

## 12. Partial Payment

Obligation ₹19,159.

Actual allocation ₹10,000.

Remaining obligation:

₹9,159.

Status:

`PARTIALLY_PAID`.

## 13. Missing Obligation

If a planned obligation has no actual allocation by its due date/grace period:

`MISSING / OVERDUE`

as configured by product rules.

Do not automatically assume non-payment solely from absence of a statement transaction if statement coverage is incomplete.

## 14. Unaccounted Transaction

A cleared actual bank transaction that has no existing ledger match becomes an unaccounted candidate.

User actions:

- Categorize
- Create expense
- Create income
- Mark transfer
- Link existing transaction
- Ignore

## 15. Transfers

If a bank statement shows:

ICICI -₹25,000

and another account shows:

SBI +₹25,000

the system may suggest an internal transfer when signals support it.

The transfer must remain one financial event, not an expense plus income.

## 16. Credit Cards

Credit-card purchase:

- Expense
- Liability increases

Credit-card payment:

- Transfer
- Asset decreases
- Liability decreases

Do not count the payment as a second expense.

## 17. Refunds & Reversals

Refund:

- Link to original expense.
- Reverse effective expense/budget utilization.

Reversal:

- Link to original transaction.
- Preserve both records.
- Combined financial effect may net to zero.

## 18. Balance Reconciliation

For an account/date:

```text
Calculated Ledger Balance
vs
Statement Closing Balance
```

Show difference.

If different:

- Flag mismatch.
- Show candidate causes where possible.
- Allow controlled adjustment with reason.
- Preserve audit trail.

## 19. Commit Rules

A staged transaction can be committed only when:

- Parsing is successful.
- Required fields are valid.
- Duplicate status is resolved.
- Match status is resolved sufficiently.
- Category is confirmed when required.
- User/system has chosen commit, link, or ignore.

Committing a matched statement transaction must not create another ledger transaction.

## 20. Import Recovery

If parsing partially fails:

- Preserve batch.
- Record failure details.
- Do not silently commit partial results.
- Allow retry/reprocess after parser correction.

## 21. Security

Uploaded statement files are sensitive.

- Store privately.
- Validate type/size.
- Do not execute.
- Do not expose public URLs.
- Never persist bank passwords or OTPs.

## 22. Reconciliation Acceptance Criteria

The system must be able to demonstrate:

- No duplicate transaction after repeated upload.
- Existing manual transaction is verified rather than duplicated.
- Partial payments remain outstanding.
- Overpayments remain separately identifiable.
- One-to-many and many-to-one allocations work.
- Transfers do not become expenses.
- Credit-card payments do not double-count.
- Balance mismatches remain visible.


---

## FILE: docs/knowledge_base/07_DEVELOPMENT_ROADMAP.md

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


---

## FILE: docs/knowledge_base/08_CHANGELOG.md

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


---

## FILE: docs/knowledge_base/09_ERD_AND_MIGRATION_DESIGN.md

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


---

## FILE: docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md

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

