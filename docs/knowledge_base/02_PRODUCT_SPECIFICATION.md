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
