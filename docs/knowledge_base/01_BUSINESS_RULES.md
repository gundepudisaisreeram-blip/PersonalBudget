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
