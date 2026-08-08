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
