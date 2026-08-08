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
