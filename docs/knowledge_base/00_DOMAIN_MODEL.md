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
