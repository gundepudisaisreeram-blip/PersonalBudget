# 00_DOMAIN_MODEL.md

## 1. Domain Philosophy: The Four Realities
Every entity in this system belongs strictly to one of four realities:
*   **PLANNED:** What is expected to happen (Configuration & Obligations).
*   **ACTUAL:** What definitively happened (The Immutable Ledger).
*   **STAGED:** External data awaiting verification (Imported Bank Statements).
*   **DERIVED:** Calculations, cached states, and linkages.

## 2. Core Entities & Definitions

### 2.1 The Ledger (ACTUAL)
*   **Account:** A discrete financial bucket owned by the user. Must be typed:
    *   *Asset Account:* Holds positive value (e.g., Savings, Cash Wallet).
    *   *Liability Account:* Holds debt (e.g., Credit Card, Loan Account).
*   **Transaction:** A record of an *actual* financial event. The ledger of transactions is the single authoritative source of truth.
*   **Income:** A Transaction that increases total personal wealth.
*   **Expense:** A Transaction that decreases total personal wealth.
*   **Transfer:** A paired movement of money between two Accounts owned by the same user. It does *not* alter total personal wealth.
*   **Refund/Reversal:** A Transaction that explicitly negates or returns funds from a previous Expense, linked back to the original event.
*   **Adjustment:** A controlled correction entry (with audit history) used to fix discrepancies between the system ledger and reality, without silently overwriting historical data.

### 2.2 The Budget (PLANNED)
*   **Recurring Payment Template:** A user-defined configuration describing a repeated expected obligation.
*   **Monthly Payment (Obligation):** A specific, point-in-time instance generated from a Template. 
*   **Budget Category:** A classification grouping similar financial events.
*   **Variable Budget:** A user-defined spending limit for a specific Budget Category within a Financial Period.

### 2.3 The Import System (STAGED)
*   **Import Batch:** A metadata record of a physical bank statement file uploaded by the user.
*   **Statement Transaction:** A raw, unprocessed line item extracted from an Import Batch. 
*   **Categorization Rule:** A logic condition used to automatically suggest a Budget Category for a Statement Transaction.

### 2.4 Relationships & Linkages (DERIVED)
*   **Account Balance:** A derived value calculated strictly by summing the ledger movements against the opening balance. It is never an independent source of truth.
*   **Reconciliation Allocation:** A many-to-many linkage with allocated amounts. 
    *   *Example:* One ₹10,000 Actual Transaction can fulfill two ₹5,000 Monthly Payments.
    *   *Example:* Two ₹5,000 Actual Transactions can fulfill one ₹10,000 Monthly Payment.
*   **Financial Period:** A reporting/budget boundary (e.g., a calendar month). A transaction's period is derived dynamically from its `transaction_date`.

## 3. Lifecycle Semantics

### 3.1 Monthly Payment Lifecycle
*   **Generated:** Created by the system based on the Template.
*   **Pending:** Awaiting fulfillment.
*   **Paid:** Linked fully to Actual Transaction(s) via allocation.
*   **Partially Paid:** Linked to Actual Transaction(s), but the allocated amount is less than the obligation.
*   **Skipped:** User explicitly chose not to pay this instance. Remains historically visible but excludes its amount from pending obligation totals.

### 3.2 Recurring Template Lifecycle
*   **Active:** Currently generating future obligations.
*   **Cancelled:** User ended the recurrence. Historical obligations remain untouched; future obligations are no longer generated.

### 3.3 Statement Transaction Processing States (Non-Sequential)
A Statement Transaction tracks multiple parallel statuses:
*   `categorization_status`: Uncategorized, Suggested, Confirmed.
*   `match_status`: Unmatched, Suggested Match, Confirmed Match.
*   `duplicate_status`: Unique, Potential Duplicate, Confirmed Duplicate.
*   `commit_status`: Staged, Committed (to Ledger), Ignored, Failed.

## 4. Domain Invariants (Unbreakable Rules)

1.  **Transfer Symmetry:** A Transfer must always have exactly two legs (Outbound from Account A, Inbound to Account B) of the exact same monetary amount.
2.  **Ledger Authority:** A Statement Transaction can never affect an Account balance directly. Only an Actual Transaction affects the derived balance.
3.  **Template Independence:** Canceling or altering a Recurring Payment Template must never alter, delete, or detach historical Monthly Payments.
4.  **Credit Card Semantics:** Paying a credit card is a Transfer (reduces Asset, reduces Liability). Buying something with the credit card is the Expense (increases Liability, decreases personal wealth).
5.  **Investment Semantics:** Moving money into a brokerage or mutual fund via SIP is a Transfer (from Cash Asset to Investment Asset), not an Expense, unless specifically configured otherwise by the user.

## 5. Terminology Rules & Forbidden Ambiguities

*   **FORBIDDEN:** Using "Payment" or "Bill" to mean a general expense.
    *   *Correction:* Use `Monthly Payment` for the planned item, and `Transaction` (type: Expense) for the actual movement of money.
*   **FORBIDDEN:** Using "Transfer" to mean paying a merchant via bank transfer or UPI. 
    *   *Correction:* A `Transfer` is strictly internal between user-owned accounts. Paying a merchant is an `Expense`.
*   **FORBIDDEN:** Destructive edits on the ledger.
    *   *Correction:* Use Adjustments, Refunds, or Reversals to maintain a traceable audit history.