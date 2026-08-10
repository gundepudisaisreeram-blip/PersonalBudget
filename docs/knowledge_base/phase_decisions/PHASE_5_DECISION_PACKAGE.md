# PHASE 5 DECISION PACKAGE — Dashboard & Safe-to-Spend

## 1. Document Control

- **Phase:** 5 — Dashboard & Safe-to-Spend
- **Status:** PLANNING ONLY — no implementation authorized. Corrected per converged ChatGPT/Gemini adversarial review findings; P1 Upcoming Payments window contradiction corrected in v1.2.0; residual documentation inconsistencies corrected in v1.2.1, v1.2.2, and v1.2.3.
- **Version:** 1.2.3
- **Date:** 2026-08-10 (v1.0.0), corrected 2026-08-10 (v1.1.0), corrected 2026-08-10 (v1.2.0), corrected 2026-08-10 (v1.2.1), corrected 2026-08-10 (v1.2.2), corrected 2026-08-10 (v1.2.3 — see section 21)
- **Relationship to frozen Knowledge Base:** This document is subordinate to `00_DOMAIN_MODEL.md` through `10_IMPLEMENTATION_CONTRACT.md`. It does not amend, override, or reinterpret any frozen requirement. Every formula below is either quoted verbatim from `01_BUSINESS_RULES.md` (Category A) or explicitly labeled as a Phase 5 planning-level architectural decision (Category C) with its own reasoning.
- **Governance basis:** Per `10_IMPLEMENTATION_CONTRACT.md`'s Phase Decision Package process, this document must receive external adversarial review and an explicit GO before any implementation code is written.

---

## 2. Authority Hierarchy

`00_DOMAIN_MODEL.md` through `10_IMPLEMENTATION_CONTRACT.md` remain authoritative over this document at all times. If implementation discovers a contradiction between this document, the frozen Knowledge Base, or the existing certified architecture: **STOP. Do not silently reinterpret.** Several genuine ambiguities were found during preparation of this document; none were silently resolved — they are listed in full in section 22.

---

## 3. Phase 5 Objective

Per `07_DEVELOPMENT_ROADMAP.md`'s own Phase 5 definition (verbatim):

> **Build:** Current Asset Balance, Pending obligations, Pending investments, Safe Balance, Safe-to-Spend, Upcoming payments, Attention Center, Budget snapshot.
> **Acceptance:** All formulas are centralized and covered by tests.

Phase 5 is a **read-only aggregation and presentation layer**. It introduces no new financial transaction type, no new obligation type, no new ledger mechanism, and no new domain entity beyond a `SafeToSpendService` (explicitly named in `03_ARCHITECTURE.md` §4 Core Services — Category A) and the dashboard's own controller/views.

### Explicitly out of scope (Phase 6+, per `02_PRODUCT_SPECIFICATION.md` §2 and `07_DEVELOPMENT_ROADMAP.md`)

- Reports (`Phase 6`), bank statement import/parsing (`Phase 7`/`8`), categorization/reconciliation (`Phase 9`), credit-card/loan/investment-account UX enhancements (`Phase 10`), goals/forecasting (`Phase 11`), notifications/PWA hardening (`Phase 12`).
- Any Attention Center rule that depends on Statement Import or Reconciliation data (`unaccounted transactions`, `statement balance mismatch`) — these entities (`StatementImport`, `StatementTransaction`, `ReconciliationMatch`, `AccountReconciliation`) exist only as dormant Phase 1 schema/models with no processing logic anywhere in the codebase (verified by inspection — no controller, service, or route references them). They cannot be meaningfully populated in Phase 5 and are excluded, not deferred-and-guessed-at.
- Any new financial transaction type, ledger mechanism, or obligation type.
- Any modification to `Transaction`, `LedgerEntry`, `Money`, `TransactionService`, `TransferService`, `RefundService`, `ReversalService`, `AccountBalanceService`, `PaymentObligationService`, `ObligationAllocationService`, `MonthlyGenerationService`, or `BudgetService` beyond what section 6 explicitly identifies as read-only consumption.

### Phase 4 technical debt is explicitly NOT part of Phase 5

Per the certified Phase 4 P3 findings (`PHASE_4_IMPLEMENTATION_REPORT.md`): console batch-generation exception handling and the stale ownership comment in `PaymentObligationController.php` are **not** Phase 5 prerequisites and must not be fixed under this phase. The unresolved `RecurringPaymentTemplate` domain-service-boundary architectural item (`PHASE_4_DECISION_PACKAGE.md` §23) likewise remains unresolved and out of scope here — Phase 5 only *reads* `RecurringPaymentTemplate`/`PaymentObligation` data, it does not write to either.

---

## 4. Existing Certified Architecture Reuse

Phase 5 is additive and **read-only** against every certified Phase 1–4 component it touches:

| Component | Role in Phase 5 |
|---|---|
| `App\Domain\Services\AccountBalanceService::calculate()` | Authoritative source for each asset account's derived balance (BR-028). Called once per account, not reimplemented. |
| `App\Models\Account` | Source of `account_type`/`status` for asset-account filtering. |
| `App\Models\PaymentObligation` | Source of `planned_amount`, `is_mandatory`, `status`, `period_start`/`period_end`, `due_date`, `category_id` for BR-029/030 and Upcoming Payments. |
| `App\Models\ObligationAllocation` | Source of allocated totals per obligation, to compute "outstanding amount" (`planned_amount − SUM(allocated_amount)`). |
| `App\Domain\Services\BudgetService::calculateUtilization()` | Authoritative source of "eligible actual utilization" per budget (BR-025/BR-027), reused unmodified — never reimplemented in a dashboard query. |
| `App\Models\Budget` | Source of `budget_amount`/`period_start`/`period_end`/`category_id` for the Variable Budget Reserve (BR-033) and Budget Snapshot. |
| `App\Models\Category` | Source of `category_type` for the resolved Fixed-vs-Investment partition defined in §9.3: `category_type === 'INVESTMENT'` means Investment; every other value means Fixed. |
| `App\Domain\Services\OwnershipGuard` | Reused for any single-record authorization Phase 5 needs (see section 7); all aggregate dashboard queries are scoped by `user_id` directly, per the pattern already certified in every Phase 1–4 index/listing query (e.g., `$request->user()->paymentObligations()`, `$request->user()->accounts()`). |
| `App\Domain\Money` | All arithmetic in `SafeToSpendService` must use `Money::add()`/`Money::sub()`, per BR-003. |

No modification to any of the above is proposed. `SafeToSpendService` (new) is the only new domain service, and it is explicitly named in `03_ARCHITECTURE.md` §4 — this is not an invented service, it is a frozen-KB-anticipated one.

---

## 5. Domain Model and Lifecycle

Phase 5 introduces no new lifecycle. It reads:
- `Account` (Phase 2 lifecycle: create → edit → close, unchanged).
- `PaymentObligation` (Phase 4 lifecycle: PENDING → PARTIALLY_PAID → PAID / PENDING → SKIPPED / PENDING → CANCELLED, unchanged) — BR-021 already excludes SKIPPED obligations from "active pending-obligation calculations," which Phase 5's BR-029/030 sums must respect.
- `Budget` (Phase 4 lifecycle: create → edit, no deletion, unchanged).
- `RecurringPaymentTemplate` (read-only, for context/name display on Upcoming Payments only — not used in any Safe-to-Spend arithmetic).

No Phase 5 code creates, updates, or deletes any of the above records (see section 8).

---

## 6. Dashboard Architecture

**`DashboardController`** (new, thin) — resolves the authenticated user, calls `SafeToSpendService` for all financial figures, calls existing services/scoped queries for Upcoming Payments / Attention Center / Budget Snapshot / Account Snapshot, and passes prepared view-models to a single `dashboard/index.blade.php` view. No financial calculation occurs in the controller or in any Blade view, per `03_ARCHITECTURE.md` principle 2 and the pattern already certified for `BudgetController`/`PaymentObligationController`.

**`SafeToSpendService`** (new) — owns every formula in section 9. Its public surface (proposed, not yet implemented):
```php
calculateCurrentAssetBalance(User $user): string        // BR-028
calculatePendingMandatoryFixedObligations(User $user, Carbon $periodStart, Carbon $periodEnd): string   // BR-029
calculatePendingMandatoryInvestments(User $user, Carbon $periodStart, Carbon $periodEnd): string        // BR-030
calculateSafeBalance(User $user, Carbon $periodStart, Carbon $periodEnd): string                        // BR-031
calculateVariableBudgetReserve(User $user, Carbon $periodStart, Carbon $periodEnd): string               // BR-033
calculateSafeToSpend(User $user, Carbon $periodStart, Carbon $periodEnd): string                        // BR-032
```
All six values are needed simultaneously by the dashboard (section 3 "Hero" + "Secondary metrics" in `05_UI_UX_SPECIFICATION.md`), so the service should expose a single aggregate method (e.g. `snapshot(User $user, ?string $targetPeriod = null): SafeToSpendSnapshot`) returning all six as one value object/array to avoid six separate round-trips computing overlapping sub-queries — the exact shape of this aggregate return is an implementation detail deferred to the implementation turn, not decided here.

---

## 7. Tenant Isolation

Every Phase 5 query is scoped to the authenticated user directly via the existing certified pattern (`$user->accounts()`, `$user->paymentObligations()`, `$user->budgets()` — relationship methods already present on `User` since Phase 1–4), never via an unrestricted global query. No Phase 5 route accepts a foreign-key ID from the client (the dashboard has no create/update/delete action and no per-record detail route of its own — it only aggregates and links out to existing, already-certified detail routes such as `obligations.show`/`accounts.show`, which already enforce `OwnershipGuard`/Policy checks independently). Because Phase 5 introduces no direct-ID-accepting endpoint, there is no new `OwnershipGuard`/Policy method to add — this is a genuine architectural consequence of the read-only, aggregate-only nature of a dashboard, not an oversight, and is recorded here rather than silently assumed.

Per section 13's now-resolved decision, no dismissal mechanism (and therefore no record-ID-accepting Attention Center route) exists in Phase 5 at all. If a future phase introduces one, it must go through the same Policy/`OwnershipGuard` dual-layer pattern certified in Phases 1–4, with no exception — but that is out of scope here.

---

## 8. Historical / Financial Immutability

Phase 5 is read-only by construction: `SafeToSpendService`'s methods and `DashboardController` perform only `SELECT`-shaped operations (Eloquent queries, `DB::table()->select()`, calls to `AccountBalanceService::calculate()`/`BudgetService::calculateUtilization()`, both of which are themselves read-only). No Phase 5 code path calls `::create()`, `::update()`, `::delete()`, or opens a `DB::transaction()` for a write. This is verifiable by the file boundary in section 19 — no Phase 5 file is a migration, and no Phase 5 service constructor accepts a write-capable dependency beyond the read-only services listed in section 4.

---

## 9. Safe-to-Spend Mathematics

Every formula below is quoted or directly derived from `01_BUSINESS_RULES.md` §F (BR-027 through BR-034), reconciled against the current implementation. **Category A = frozen KB requirement, quoted verbatim. Category C = Phase 5 planning-level interpretation of how to compute a Category A formula against the actual current schema, not itself a business rule.**

### 9.1 Current Asset Balance — BR-028 (Category A, frozen)

> "Current Asset Balance is the sum of derived balances of asset accounts. Liability balances are excluded from available cash."

**Formula:**
```
Current Asset Balance = Σ AccountBalanceService::calculate($account)
                         for every $account where
                           $account->user_id === $user->id
                           AND $account->account_type === 'ASSET'
```

- **Authoritative data source (FROZEN, v1.1.0):** `AccountBalanceService::calculate()`, called once per included asset account. **BR-028 MUST NOT be computed via raw SQL `SUM()` aggregation over `ledger_entries`.** This is a hard architectural rule, not a style preference: `AccountBalanceService::calculate()` is the one certified, tested source of an account's derived balance (opening balance ± net ledger movement, direction-aware per `isAsset()`/`isLiability()`), and a parallel SQL aggregation in `SafeToSpendService` would be a second, potentially divergent implementation of the same calculation — exactly the "parallel financial calculation engine" this correction prohibits. See section 11 for the required per-account loop pattern.
- **Account inclusion/exclusion (RESOLVED, Project Owner Decision, v1.1.0):** all accounts with `account_type = 'ASSET'` for the user, **including CLOSED asset accounts that retain a balance.** Rationale (Project Owner, verbatim intent): "Closing an account does not erase the financial value represented by its existing balance." Liability accounts are never included, regardless of status. This was previously flagged as Open Decision 2 (now resolved) — see the adversarial test requirement in section 19/20 proving this exact behavior.
- **Investment accounts:** included at their current derived balance — `00_DOMAIN_MODEL.md` §2.2 explicitly lists "investment account" as an example Asset account type, and BR-028 makes no exclusion for them. This is Category A, resolved, not open.
- **Rounding/decimal behavior:** `Money::add()` throughout (bcmath, DECIMAL(15,2)-precision strings) — no floats, per BR-003.

### 9.2 Pending Mandatory Fixed Obligations — BR-029 (Category A, frozen)

> "Sum of outstanding amounts on mandatory Payment Obligations in the relevant current period."

**Formula (RESOLVED, v1.1.0 — Category C interpretation of a Category A formula, now Project-Owner-approved):**
```
Pending Mandatory Fixed Obligations =
  Σ (planned_amount − Σ allocated_amount)
  for every PaymentObligation where
    user_id === $user->id
    AND is_mandatory === true
    AND status IN ('PENDING', 'PARTIALLY_PAID')
    AND period_start <= today <= period_end        -- inclusive interval, current-period decision, see §15
    AND category.category_type !== 'INVESTMENT'    -- Fixed/Investment partition, see §9.3
```
- **Authoritative data source:** `payment_obligations.planned_amount` minus `SUM(obligation_allocations.allocated_amount)` grouped by `payment_obligation_id` — the identical "outstanding amount" computation already certified inside `ObligationAllocationService` (its `allocatedTotal()` private method), reused conceptually but as a read-only aggregate query here, not a call into that service (which is designed around a single locked obligation, not a cross-obligation sum).
- **Obligation inclusion/exclusion:** `is_mandatory = true` only (BR-029's own word "mandatory"). Status `PENDING`/`PARTIALLY_PAID` only — `SKIPPED` excluded per BR-021 ("excluded from active pending-obligation calculations"); `CANCELLED` excluded for the same reason; `PAID` excluded because its outstanding amount is definitionally zero (mathematically inert to include, but excluding it explicitly avoids an unnecessary row scan — an implementation detail, not a financial-meaning decision).
- **Treatment of refunds/reversals/transfers:** not applicable to this formula — it operates entirely on the *planned* domain (`PaymentObligation`/`ObligationAllocation`), not the *actual* ledger. Refunds/reversals affect the Current Asset Balance (§9.1, via `AccountBalanceService`) and Budget Utilization (§9.5), not this figure.
- **Treatment of closed accounts:** not applicable — obligations do not have a `status` dependency on their `planned_account_id`'s account status; BR-029 makes no such exclusion, and neither does any existing certified service.
- **Rounding/decimal behavior:** `Money::sub()`/`Money::add()` throughout.

### 9.3 Pending Mandatory Investments — BR-030 (Category A, frozen)

> "Sum of outstanding amounts on mandatory planned investment obligations in the relevant current period."

**Formula:** identical shape and scoping to §9.2, restricted to `category.category_type === 'INVESTMENT'` instead of excluded from it.

**Investment classification (RESOLVED, Project Owner Decision, v1.1.0):**
```
$obligation is Investment  ⟺  $obligation->category->category_type === 'INVESTMENT'
$obligation is Fixed       ⟺  NOT ($obligation->category->category_type === 'INVESTMENT')
```
This is the exact, exclusive, case-sensitive string comparison against the value the certified `CategorySeeder` assigns to the system "Investments" category (`category_type = 'INVESTMENT'`). It requires **no migration** — reuses the existing `categories.category_type` free-text column exactly as currently populated.

**Accepted limitation (documented, not resolved further):** `category_type` is currently free-text, not an enum or CHECK-constrained value. This classification therefore depends entirely on the existing canonical `'INVESTMENT'` value being present and correctly spelled/cased on whichever category a user assigns to an obligation. **Phase 5 must not invent additional classification heuristics** (e.g., fuzzy-matching category names, inferring investment intent from obligation names) — if a user's category data is inconsistent, the obligation is classified strictly by the literal `category_type` value, full stop. This is a known, accepted data-quality dependency, not a Phase 5 defect.

**Mutual exclusivity (FROZEN, v1.1.0):** the two partitions in §9.2 and §9.3 are constructed as exact logical complements of each other (`category_type === 'INVESTMENT'` vs. `category_type !== 'INVESTMENT'`) over the identical base query (same `user_id`/`is_mandatory`/`status`/period filters). Every included mandatory obligation therefore falls into **exactly one** of Fixed or Investment — never both, never neither. A dedicated adversarial test must prove this partition (section 20).

### 9.4 Safe Balance — BR-031 (Category A, frozen, verbatim formula)

> `Safe Balance = Current Asset Balance - Pending Mandatory Fixed Obligations - Pending Mandatory Investments`

```
Safe Balance = Money::sub(
    Money::sub(CurrentAssetBalance, PendingMandatoryFixedObligations),
    PendingMandatoryInvestments
)
```
No rounding/clamping — this figure can be negative if obligations exceed current assets, and BR-034's non-clamping rule (§9.7) applies transitively since Safe-to-Spend is derived from it.

### 9.5 Variable Budget Reserve — BR-033 (Category A, frozen) + BR-027 (Category A, frozen, per-category formula)

> BR-033: "The reserve is the sum of remaining amounts across variable budgets that are intended to be honored within the period."
> BR-027: "For Safe-to-Spend, remaining budget for a category is MAX(Budget - eligible actual utilization, 0)."

**Formula (RESOLVED, v1.1.0):**
```
Variable Budget Reserve =
  Σ MAX(Money::sub($budget->budget_amount, BudgetService::calculateUtilization($budget)), 0)
  for every Budget where
    user_id === $user->id
    AND period_start <= today <= period_end        -- inclusive interval, current-period decision, see §15
```
- **Authoritative data source (FROZEN):** `BudgetService::calculateUtilization()` (certified, unmodified, Phase 4) supplies "eligible actual utilization" for each budget. **Phase 5 consumes the certified `BudgetService::calculateUtilization()` result exactly as implemented in Phase 4. No budget-utilization mathematics are reimplemented in Phase 5.** In particular, Phase 5 does not calculate utilization by `transaction_type`, does not reproduce the `SUM(OUTFLOW) − SUM(INFLOW)` ledger formula, and does not re-derive Transfer's net-zero effect — all of that is entirely internal to `BudgetService::calculateUtilization()` and is opaque to Phase 5, which only reads its single returned string value per budget. The frozen Phase 4 ledger mathematics remain solely authoritative there.
- **Per-category floor:** `MAX(..., 0)` is applied **per budget row** before summing, per BR-027's exact wording ("remaining budget for a category is MAX(...)") — an individual overspent category contributes `0` to the reserve, not a negative number that would partially offset other categories' positive reserves. This is Category A, unambiguous.
- **Relationship to BR-026:** overspend must still be visible in reporting (Budget Snapshot, §14) even though it contributes `0`, not a negative value, to the Safe-to-Spend reserve — these are two different presentations of the same `BudgetService::calculateUtilization()` output, not a contradiction.

### 9.6 Safe-to-Spend — BR-032 (Category A, frozen, verbatim formula)

> `Safe-to-Spend = Safe Balance - Remaining Variable Budget Reserve`

```
Safe-to-Spend = Money::sub(SafeBalance, VariableBudgetReserve)
```

### 9.7 Negative Safe-to-Spend — BR-034 (Category A, frozen)

> "The system must allow a negative derived Safe-to-Spend value and clearly flag it as a shortfall. It must not silently clamp the displayed financial result to zero."

Explicit answers, all directly from BR-034's text:
- **Negative values allowed:** yes, mandatory.
- **Displayed as negative:** yes — the underlying financial value must be shown as computed, not clamped.
- **Clamped to zero:** **explicitly prohibited** by BR-034 for the *displayed* Safe-to-Spend figure itself.
- **Attention Center special treatment:** BR-034 requires the shortfall be "clearly flagged" — a negative-Safe-to-Spend Attention Center item is therefore Category A (required), not an invented rule. Its exact severity/priority position in the Attention Center ordering is Open Decision 5.
- **Underlying value stays negative even under a UI warning state:** yes — BR-034's "must not silently clamp" applies to the *financial result*, independent of whatever visual warning treatment (red text, icon, banner) the UI layer adds on top. The UI never substitutes a different (e.g., zero) number for display purposes.

### Refund / Reversal / Transfer treatment across all of §9

Refunds and Reversals affect §9.1 (via ledger entries → `AccountBalanceService::calculate()`, unmodified) and §9.5 (via whatever `BudgetService::calculateUtilization()` returns — Phase 5 does not know or care *how* that method arrives at its number, only that it is the authoritative figure). Transfers affect §9.1 (both legs are asset-account ledger movements included in `AccountBalanceService::calculate()`'s own per-account computation, net-zero on total wealth per BR-013 as an emergent property of summing both accounts, not a Phase 5 calculation) and are reflected in §9.5 solely through whatever `BudgetService::calculateUtilization()` returns for a given budget — again, Phase 5 performs no `transaction_type`-based reasoning of its own anywhere. Neither Refund, Reversal, nor Transfer is directly referenced in any Phase 5 query in §9.2/9.3/9.4/9.6 — those operate purely on the planned domain (`PaymentObligation`/`ObligationAllocation`/`Budget`), never touching `Transaction`/`LedgerEntry` directly.

---

## 10. Data Authority Table

| Metric | Authoritative source | Reused unmodified? |
|---|---|---|
| Current Asset Balance | `AccountBalanceService::calculate()` per asset account | Yes |
| Pending Mandatory Fixed Obligations | `payment_obligations` + `obligation_allocations` aggregate query (new, read-only, in `SafeToSpendService`) | New query, no new writer |
| Pending Mandatory Investments | Same query as Fixed Obligations, partitioned by `category.category_type === 'INVESTMENT'` (RESOLVED, §9.3) | New query, no new writer |
| Safe Balance | `SafeToSpendService`, composing the two above with Current Asset Balance | New (formula composition only) |
| Safe-to-Spend | `SafeToSpendService`, composing Safe Balance and Variable Budget Reserve | New (formula composition only) |
| Variable Budget Reserve | `BudgetService::calculateUtilization()` per budget, floored | Yes (utilization); new aggregation |
| Upcoming Payments | `payment_obligations` scoped query (due_date window) | New query, no new writer |
| Attention Center | Aggregation of the above plus explicit rule evaluation (§13) | New, no new writer |
| Budget Snapshot | `BudgetService::calculateUtilization()` per budget | Yes |
| Account Snapshot | `AccountBalanceService::calculate()` per account | Yes |

No dashboard metric reimplements a calculation already owned by a certified domain service — every "New" row above is either a straightforward `SELECT`-shaped aggregate over existing tables or a pure composition of existing service outputs, per section 6's instruction that "a dashboard must never independently reimplement financial calculations already owned by domain services."

---

## 11. Performance Architecture

- **BR-028 is explicitly excluded from raw-SQL aggregation (v1.1.0, FROZEN):** Current Asset Balance is computed by calling `AccountBalanceService::calculate($account)` once per included asset account and summing the returned strings with `Money::add()` in PHP — **not** a `DB::table('ledger_entries')->selectRaw('SUM(...)')`-style single aggregate query. This is a deliberate exception to the "aggregate in SQL" default below, made specifically to keep `AccountBalanceService` the single authoritative source (section 4/9.1) rather than reimplementing its opening-balance-plus-net-movement, direction-aware logic as a parallel SQL formula. The per-account loop is bounded (a user's account count is small, and the exact same loop is already required for the Account Snapshot card, section 12) — this is not a meaningful N+1 performance risk, and eager-loading is not applicable here since each call is a fresh aggregate query per account by design, identical in shape to how `AccountBalanceService::calculate()` is already invoked once per account on the existing `accounts/show.blade.php`/`accounts/index.blade.php` screens.
- **Aggregation location for everything else:** BR-029 and BR-030 (outstanding obligation sums) and the Variable Budget Reserve's per-budget utilization inputs are computed via `DB::table()->selectRaw('COALESCE(SUM(...), 0)')`-shaped queries (the identical pattern already certified in `BudgetService::calculateUtilization()`), not fetched-then-summed-in-PHP. `SafeToSpendService` composes the results of these SQL aggregates with the per-account `AccountBalanceService` loop's PHP-level sum — it does not introduce a third, competing calculation style; each authoritative source keeps its own established computation shape (per-account service calls for balances, SQL aggregates for obligation/allocation sums), and `SafeToSpendService`'s only job is composition via `Money::add()`/`Money::sub()`. No parallel financial calculation engine is created anywhere in Phase 5.
- **`BudgetService::calculateUtilization()` reuse:** this method runs two queries per budget. The Variable Budget Reserve (§9.5) and Budget Snapshot (§12) both need this value for every active budget in the period — the dashboard should call it once per budget and reuse the result for both, not call it twice. This requires a shared "active budgets for the current period" query fetched once by `DashboardController`, then a single loop computing utilization per budget — an implementation detail, not requiring a new decision.
- **N+1 prevention:** any per-obligation or per-account loop must eager-load its relationships (`->with(['category', 'plannedAccount'])`, mirroring the existing certified pattern in `PaymentObligationController::index()`).
- **Pagination:** the dashboard's own cards/widgets are bounded, aggregate figures (a handful of accounts, a handful of active budgets, at most 14 calendar dates of upcoming obligations — see §15) — no pagination is needed on the dashboard itself. `19_...` (Search & Filtering) 's server-side-pagination requirement applies to full list screens (Accounts, Transactions, Obligations), not this summary view.
- **Caching:** **not introduced.** No frozen document authorizes dashboard caching, and every certified read-heavy calculation in this codebase to date (`AccountBalanceService::calculate()`, `BudgetService::calculateUtilization()`) is deliberately recomputed per request, not cached — Phase 5 follows the same pattern for consistency and to avoid a stale-Safe-to-Spend risk that caching would introduce. If a future performance need arises, it is a separate, explicitly-approved architectural decision, not assumed here.

---

## 12. UI/UX Contract

Per `05_UI_UX_SPECIFICATION.md` §3 (Dashboard) and §13 (Empty States), reproduced and organized:

- **Hero:** Current Asset Balance, Safe Balance, Safe-to-Spend — the three headline figures.
- **Secondary metrics:** Pending mandatory obligations, Pending mandatory investments, Paid obligations (RESOLVED, v1.1.0 — see the dedicated definition below), Variable budget reserve.

**Paid Obligations metric (RESOLVED, Project Owner Decision, v1.1.0):** count and total planned amount of every `PaymentObligation` with `status = 'PAID'` whose period contains "today" (same inclusive current-period test as §9.2/9.3/9.5 — see §15), **regardless of `is_mandatory`** (all PAID obligations are included, not only mandatory ones). `PENDING`, `PARTIALLY_PAID`, `SKIPPED`, and `CANCELLED` obligations are never included in this metric. This is documented as a Phase 5 **presentation metric** — it composes existing certified data (`PaymentObligation.status`/`planned_amount`) but is not itself a new business rule (it has no BR number and does not participate in any Safe-to-Spend formula in section 9).
- **Layout:** Bootstrap 5 cards, consistent with every existing Phase 1–4 screen (`resources/views/budgets/index.blade.php` et al.) — no new CSS framework, no new JS framework beyond what's already in use (Vanilla JS/Alpine.js where justified, per `03_ARCHITECTURE.md` §2).
- **Responsive behavior:** mobile-first per `05_UI_UX_SPECIFICATION.md` §1; cards stack on narrow viewports, consistent with the existing Bootstrap grid usage in `budgets/index.blade.php`.
- **Warning/attention states:** a negative Safe-to-Spend figure must render as a visually distinct warning state (not merely plain text) per BR-034's "clearly flag it as a shortfall" — exact visual treatment (color/icon/banner) is a Category C implementation detail, not a financial-math decision.
- **Negative Safe-to-Spend presentation:** displayed as the true negative number (e.g. `-₹4,200.00`), never `₹0.00`, per §9.7.
- **Upcoming-payment presentation:** due date, name (from `PaymentObligation`/its `RecurringPaymentTemplate` if any), amount, account, status — per `05_UI_UX_SPECIFICATION.md` §3 "Upcoming."
- **Budget-snapshot presentation:** budget, actual, remaining, utilization per category — per `02_PRODUCT_SPECIFICATION.md` §5 and §8, reusing the exact same four figures already shown on `budgets/index.blade.php`.
- **Empty states:** `05_UI_UX_SPECIFICATION.md` §13 gives the exact required copy: *"Set up your first account to begin."* — shown when the user has zero accounts, per that document verbatim.
- **Loading states:** `05_UI_UX_SPECIFICATION.md` §15 requires a skeleton/spinner for the dashboard specifically — Category A, must be implemented, not optional.
- **Accessibility:** semantic HTML, keyboard navigation, visible focus states, meaningful labels, sufficient contrast, color-not-sole-indicator (for warning states) — per `05_UI_UX_SPECIFICATION.md` §16, reused verbatim from the existing accessibility bar every Phase 1–4 screen has been held to.

No unrelated Phase 1–4 page is redesigned by this phase.

---

## 13. Attention Center

Per `02_PRODUCT_SPECIFICATION.md` §5 and `05_UI_UX_SPECIFICATION.md` §3, the full example list is: unaccounted transactions, statement balance mismatch, missing obligation, overpayment difference, overdue obligation, budget overspend.

**V1 scope (RESOLVED, Project Owner Decision, v1.1.0):** Phase 5 implements exactly three deterministic rules — no more, no fewer:

| # | Trigger | Severity | Priority (highest first) | Source data | Ordering when multiple items of this type exist |
|---|---|---|---|---|---|
| 1 | Negative Safe-to-Spend | Critical | 1 (highest) | `SafeToSpendService`'s own §9.6 result `< 0` | Single item (Safe-to-Spend is one number per user; cannot recur) |
| 2 | Overdue Payment Obligation | High | 2 | `PaymentObligation` where `status IN ('PENDING','PARTIALLY_PAID')` AND `due_date < today` (user timezone) | One item per overdue obligation, ordered `due_date` ascending, then by `id` ascending as the stable tie-breaker |
| 3 | Budget Overspend | Medium | 3 | `Budget` where `BudgetService::calculateUtilization($budget)` > `$budget->budget_amount`, current period | One item per overspent budget, ordered utilization percentage descending, then category name ascending as the tie-breaker (identical ordering rule to Budget Snapshot, §14) |

This priority list (BR-034's shortfall flag first, then overdue commitments, then budget health) is a Project Owner ordering decision for V1 — it is a narrower, resolved subset of `05_UI_UX_SPECIFICATION.md` §3's six-item example list, not a reinterpretation of it.

**Explicitly deferred (not implemented in Phase 5, not open decisions blocking implementation — genuine, permanent-until-a-future-Decision-Package scope exclusions):**
- **Unaccounted transactions, statement balance mismatch** — require Statement Import/Reconciliation (Phase 7/9), which does not exist (unchanged from v1.0.0, section 3).
- **Missing obligation** — no deterministic definition exists anywhere in the frozen Knowledge Base. Phase 5 does not invent one. Deferred to a future Decision Package if/when a precise trigger is defined.
- **Overpayment difference** — a real, theoretically computable signal exists (an allocation-eligible transaction whose total allocated amount is less than its own amount) but no existing service exposes it and its exact scope is undefined. Phase 5 does not invent a definition. Deferred to a future Decision Package.

No other alert type or heuristic may be added to the Attention Center under this Decision Package.

**Dismissal (RESOLVED, Project Owner Decision, v1.1.0): no dismissal mechanism of any kind.** The Attention Center is fully derived/read-only — every alert is recalculated from current financial state on every dashboard request. No dismissal table, migration, controller action, route, or API is created in Phase 5. If a future phase requires persistent dismissal, that is a separate Decision Package decision, not something this document authorizes or anticipates further than naming it as explicitly out of scope.

---

## 14. Budget Snapshot

- **Selected budget period (RESOLVED, v1.1.0):** the budget(s) whose own `period_start <= today <= period_end` — the same inclusive-interval current-period test used throughout section 9 (see §15).
- **Category aggregation:** one row per `Budget` (which is already category-scoped, one budget per category per period, per the Phase 4-certified no-overlap rule) — no additional aggregation needed.
- **Budget amount / utilization / remaining / percentage used:** `$budget->budget_amount`, `BudgetService::calculateUtilization($budget)`, `Money::sub($budget->budget_amount, $utilization)` (unfloored, unlike §9.5's reserve — per BR-026's requirement that reporting show the true negative variance), and `utilization / budget_amount * 100` respectively.
- **Over-budget behavior:** shown as a negative "remaining" and/or >100% utilization, per BR-026 ("Reporting must show the negative variance/overspend, even if Safe-to-Spend uses a non-negative remaining-budget reserve") — this is the one place in Phase 5 where the *unfloored* utilization figure is intentionally shown, deliberately different from §9.5's floored reserve contribution, and this distinction must not be conflated in implementation.
- **Relationship to `BudgetService`:** exclusively a read consumer of `calculateUtilization()` — no budget mathematics are duplicated or reinterpreted, per the frozen wording in §9.5.
- **Top-categories ordering (RESOLVED, Project Owner Decision, v1.1.0):** no user-configurable dashboard setting is introduced in Phase 5 — `02_PRODUCT_SPECIFICATION.md` §5's word "configurable" is resolved to mean **deterministic ordering, not a user preference.** Order: (1) utilization percentage descending; (2) category name ascending as the deterministic tie-breaker. No dashboard-settings table, preference table, configuration UI, or migration is introduced — the ordering is computed at request time from existing Phase 4 `Budget` records.

---

## 15. Date / Time Semantics

- **User's timezone:** `$user->timezone ?? config('app.timezone')` — the exact expression already certified in `MonthlyGenerationService::resolvePeriod()`, reused verbatim for consistency across the codebase.
- **Current date determination:** `Carbon::now($timezone)`, per the same certified pattern.
- **Month boundaries:** `startOfMonth()`/`endOfMonth()`, per BR-061 ("Month-end and year-end calculations must use the user's configured application timezone") and the identical certified `MonthlyGenerationService` pattern. Used only where an explicit calendar-month concept is needed (there is none left in section 9 after v1.1.0 — see the current-period decision immediately below).
- **Current-period membership (RESOLVED, Project Owner Decision, v1.1.0) — applies identically and consistently to BR-029, BR-030, BR-033, and Budget Snapshot (§14):**
  ```
  A PaymentObligation or Budget belongs to the dashboard's current period
  ⟺  period_start <= user's local current date <= period_end
  ```
  This is an **inclusive interval test** against the record's own `period_start`/`period_end`, using `Carbon::now($timezone)->toDateString()` for "today." It is explicitly **not** "overlaps the current calendar month" and explicitly **not** "period equals the current calendar month exactly" — both alternative readings considered in v1.0.0 are rejected in favor of this single, simpler, uniformly-applied rule. This resolves former Open Decision 3.
- **Upcoming-payment window (RESOLVED, Project Owner Decision, v1.2.0 — corrected from v1.1.0, see §21):** a **true 14-calendar-date window**, `today` through `today + 13 days`, inclusive at both ends:
  ```
  due_date >= today  AND  due_date <= today + 13 days
  ```
  This spans exactly 14 calendar dates (day 0 through day 13). Exact boundary behavior:
  - due **today** (day 0) → **INCLUDED**
  - due **today + 13 days** → **INCLUDED**
  - due **today + 14 days** → **EXCLUDED**
  - due **today + 15 days** → **EXCLUDED**

  A single fixed 14-calendar-date window is used; no 7-day option or user toggle is introduced. This resolves former Open Decision 10. (v1.1.0 stated "next 14 calendar days" but used an inclusive `today + 14 days` formula, which actually spans 15 calendar dates — that contradiction is corrected here; see §21 Change History.)
- **Budget period / transaction date / obligation due-date semantics:** unchanged from the certified Phase 3/4 definitions (BR-059, BR-060) — Phase 5 introduces no new period concept, it only applies the current-period membership test above for dashboard-scoping purposes.

---

## 15b. Login Redirect (new in v1.1.0)

**RESOLVED, Project Owner Decision:** authenticated users land on `/dashboard`, not `/accounts`, once Phase 5 exists. This is an explicitly authorized Phase 5 UX change — see section 19b's file boundary for the exact `bootstrap/app.php` line this permits modifying. This resolves former Open Decision 11.

---

## 16. Phase Boundary

Restated for clarity, consistent with section 3:
- No bank statement import, reconciliation execution, advanced reporting, or forecasting.
- No Phase 6+ functionality.
- No new investment-management functionality (no new investment account type, no new investment-specific transaction flow) — Phase 5 only *reads* the existing Account/Category/PaymentObligation data to classify and sum, per §9.3.
- Phase 4 technical debt (console exception handling, stale comment, `RecurringPaymentTemplate` service boundary) is explicitly not touched.

---

## 17. Database / Migration Decision

**No migrations required (RESOLVED, v1.1.0 — no longer conditional).** The investment-classification question (former Open Decision 1) is now resolved in favor of the `category_type`-based approach (§9.3), which requires no schema change. Every Phase 5 data need is satisfiable from the existing certified schema (`accounts`, `payment_obligations`, `obligation_allocations`, `budgets`, `categories`, `ledger_entries`, `transactions` — all already migrated since Phase 1, unmodified through Phase 4).

No dashboard-settings table, preference table, or persistent-dismissal table is introduced either (sections 13/14) — these were explicitly considered and explicitly rejected for V1, not merely deferred.

---

## 18. Exception / HTTP Boundary

Phase 5 is read-only, so its exception surface is narrow:
- `SafeToSpendService`/`DashboardController` perform no writes, so `BudgetException`/`AllocationException` (write-path exceptions) cannot be thrown by any Phase 5 code path.
- The only realistic domain exception is `OwnershipViolationException`, and only if a future Phase 5 route accepts a record ID directly (see section 7's caveat) — mapped via the existing certified `bootstrap/app.php` `render()` callback (generic 403), reused unmodified.
- **Empty-state behavior:** zero accounts, zero obligations, zero budgets must each render a defined empty state (`05_UI_UX_SPECIFICATION.md` §13), not an error and not a crash — e.g., Current Asset Balance renders as `₹0.00` for a user with no accounts, not a division-by-zero or null-pointer failure. This must be explicitly tested (section 17 below).
- **No unhandled 500s:** every dashboard query must handle the zero-rows case gracefully (`COALESCE(SUM(...), 0)`, `?? '0.00'`), consistent with the existing certified pattern in `AccountBalanceService`/`BudgetService`.

---

## 19. Testing Strategy

To be written at implementation time, not now. Required coverage, updated for every v1.1.0 resolution:

**Financial correctness:** Safe Balance, Safe-to-Spend, pending mandatory fixed obligations, pending mandatory investments, budget snapshot, paid obligations metric — each against a known, hand-computed worked example, mirroring the rigor of Phase 4's frozen Reversal-of-Reversal worked-example test. Explicitly required: a formula-composition test asserting `Safe Balance = Current Asset Balance - Fixed - Investments` and `Safe-to-Spend = Safe Balance - Variable Budget Reserve` hold exactly, with hand-computed numbers at every step, not merely a passing end-to-end assertion.

**AccountBalanceService authoritative-source test (new, v1.1.0):** a test proving `SafeToSpendService`'s Current Asset Balance calls `AccountBalanceService::calculate()` per account rather than an independent SQL sum — e.g., by asserting the dashboard figure matches the sum of individually-called `AccountBalanceService::calculate()` results for a hand-constructed set of accounts with non-trivial ledger activity.

**Closed asset account test (new, v1.1.0):** a CLOSED asset account with a non-zero balance must still contribute to Current Asset Balance; a CLOSED liability account must never contribute regardless (liabilities are always excluded, closed or not).

**Investment vs. Fixed mutual exclusivity (new, v1.1.0):** a test with both an `is_mandatory` obligation on a category with `category_type = 'INVESTMENT'` and one on a non-investment category, asserting the Investment obligation appears only in §9.3's sum and the Fixed obligation appears only in §9.2's sum — never both, never neither.

**Current-period inclusive-boundary tests (new, v1.1.0):** an obligation/budget whose `period_start` exactly equals today is included; one whose `period_end` exactly equals today is included; one entirely before or after today is excluded — applied identically to BR-029, BR-030, BR-033, and Budget Snapshot.

**Paid-obligations metric (new, v1.1.0):** count and total planned amount assert correctly for a mix of PAID/PENDING/PARTIALLY_PAID/SKIPPED/CANCELLED obligations, including both mandatory and non-mandatory PAID obligations (both must count, per §12's resolution).

**Boundary conditions:** zero balance, negative Safe-to-Spend (BR-034 — must assert the *displayed* value is the true negative number, not zero), no obligations, no investments, refunds, reversals, transfers (net-zero effect on Current Asset Balance), budget fully utilized (exactly 100%), budget exceeded (over 100%, unfloored in Budget Snapshot, floored to 0 in Variable Budget Reserve — both must be asserted in the same test to prove the deliberate distinction in section 14 is implemented correctly), empty dashboard (zero of everything).

**Attention Center (updated, v1.1.0):** negative Safe-to-Spend triggers the Critical/priority-1 item; an overdue obligation triggers the High/priority-2 item, ordered by `due_date` ascending then `id`; an overspent budget triggers the Medium/priority-3 item, ordered by utilization percentage descending then category name ascending; a test proving Missing Obligation/Overpayment Difference/Unaccounted Transaction/Statement Mismatch never appear (they are deferred, not silently half-implemented).

**Upcoming Payments 14-calendar-date window (corrected, v1.2.0):** an obligation due today (day 0) is included; one due exactly `today + 13 days` out is included (inclusive upper boundary); one due `today + 14 days` out is excluded; one due `today + 15 days` out is excluded; a timezone-sensitive boundary test (an obligation whose due date falls on different calendar days in UTC vs. the user's configured timezone) mirroring `MonthlyGenerationTest.php`'s existing leap-year/month-boundary rigor.

**Budget Snapshot deterministic ordering (new, v1.1.0):** two budgets with the same utilization percentage must order by category name ascending as the tie-breaker, deterministically, across repeated calls.

**Login redirect (new, v1.1.0):** an authenticated user visiting `/login` is redirected to `/dashboard`, not `/accounts`; a full regression check that no existing Phase 1–4 test asserted a hard-coded `/accounts` post-login redirect that this change would break (if one exists, it must be identified and reconciled, not silently left failing).

**Tenant isolation:** User A's dashboard figures must never include User B's accounts/obligations/budgets — a cross-tenant data-leakage test (create data for both users, assert User A's computed figures reflect only User A's data) is required and is the correct adversarial-test shape for an aggregate-only screen (no Phase 5 route accepts a foreign ID, per section 7).

**Regression:** the full existing 254-test/720-assertion Phase 1–4 suite must remain green (`BudgetService`/`AccountBalanceService`/`PaymentObligationService` must be provably unmodified — a `git diff` against those specific files should be empty at Phase 5 completion, exactly as verified for the certified Phase 1–3 services throughout Phase 4).

---

## 20. Adversarial Test Matrix

| Attack | Expected Result |
|---|---|
| User A requests User B's dashboard data | No leakage — every figure reflects only User A's own accounts/obligations/budgets |
| Closed asset account with a balance excluded incorrectly | Must be **included** (RESOLVED, v1.1.0, §9.1) — a dedicated test proves this exact behavior |
| Closed liability account included incorrectly | Must always be **excluded** — liabilities never count toward Current Asset Balance regardless of status |
| Refund incorrectly counted | Current Asset Balance reflects the refund's actual ledger effect via `AccountBalanceService::calculate()`, unmodified — Budget Snapshot reflects whatever `BudgetService::calculateUtilization()` returns, not reimplemented here |
| Reversal incorrectly counted | Same as Refund — including the frozen Reversal-of-Reversal worked example, re-asserted through the dashboard's Budget Snapshot output as an integration-level regression check, sourced entirely from `BudgetService::calculateUtilization()`'s own certified result |
| Transfer double-counted | Net-zero effect on Current Asset Balance, an emergent property of `AccountBalanceService::calculate()` being called independently per account — Phase 5 performs no Transfer-specific logic of its own |
| Negative Safe-to-Spend | Deterministic, unclamped, per BR-034 — displayed as the true negative value; triggers the priority-1 Attention Center item |
| Budget overrun | Correct in both presentations: unfloored (Budget Snapshot) and floored to 0 (Variable Budget Reserve) — both asserted in the same test; also triggers the priority-3 Attention Center item |
| Cross-period transaction | Excluded from the current dashboard snapshot (transaction-level period membership is unchanged from BR-059, already certified) |
| Cross-period obligation/budget | Excluded per the inclusive `period_start <= today <= period_end` test (RESOLVED, v1.1.0, §15) — an off-by-one at either boundary must be caught by the inclusive-boundary tests in section 19 |
| Timezone boundary | Correct local-period behavior, mirroring `MonthlyGenerationTest.php`'s existing leap-year/month-boundary rigor |
| Empty financial state | Stable dashboard — zero accounts/obligations/budgets render defined empty states, no error |
| Direct ID manipulation | Not directly applicable (no Phase 5 route accepts a foreign ID — confirmed unchanged by v1.1.0, since dismissal was explicitly rejected) — tenant isolation is instead proven via the cross-tenant data-leakage test above |
| Mandatory vs. non-mandatory obligation misclassification | A non-mandatory (`is_mandatory = false`) obligation must never appear in BR-029/030's sums, even if overdue or otherwise attention-worthy |
| SKIPPED/CANCELLED obligation incorrectly included | Explicitly excluded per BR-021, tested directly |
| Investment obligation counted in both BR-029 and BR-030 | The `category_type === 'INVESTMENT'` partition (RESOLVED, v1.1.0, §9.3) must place every included obligation in exactly one sum — a dedicated mutual-exclusivity test is required |
| Non-mandatory obligation misclassified as Fixed/Investment | Never appears in either §9.2 or §9.3 regardless of category — `is_mandatory = true` is checked before the Fixed/Investment partition is even evaluated |
| Paid obligation double-counted or wrongly excluded | The Paid Obligations metric (§12) counts only `status = 'PAID'` in the current period, mandatory and non-mandatory alike — a test with a non-mandatory PAID obligation must prove it is still counted |
| Upcoming Payments `today+14`-day-out obligation included | Must be excluded — the corrected 14-calendar-date window's exact upper boundary is `today+13` (inclusive); `today+14` and `today+15` are both excluded, tested explicitly |
| Two overspent budgets with identical utilization ordered non-deterministically | Must order by category name ascending as the tie-breaker, identically between Attention Center and Budget Snapshot |
| Dismissal endpoint reachable despite being explicitly rejected | No such route exists — confirmed by route-list inspection, not merely by absence of a test failure |

---

## 19b. File Boundary

*(Numbered 19b to avoid renumbering the Testing Strategy section above, which the requested structure's own section 19 already occupies for testing; this document's section ordering otherwise follows the request's 23-section outline exactly.)*

Populated by direct inspection of the current repository (no dashboard route, controller, or view currently exists — confirmed via `routes/web.php` and `resources/views/` inspection this turn).

### Files expected to be created (not created this turn)
```
app/Domain/Services/SafeToSpendService.php
app/Http/Controllers/DashboardController.php
resources/views/dashboard/index.blade.php
resources/views/dashboard/_hero.blade.php            (or equivalent partials — exact partial breakdown is an implementation detail)
tests/Feature/Dashboard/SafeToSpendCalculationTest.php
tests/Feature/Dashboard/DashboardOwnershipTest.php
tests/Feature/Dashboard/AttentionCenterTest.php
tests/Unit/Domain/SafeToSpendServiceTest.php          (unit-level, deterministic-formula tests, per 03_ARCHITECTURE.md §20 "unit tests for deterministic calculations")
```

### Files expected to be modified (not modified this turn)
```
routes/web.php                    (+1 dashboard route)
bootstrap/app.php                 (RESOLVED, v1.1.0, explicitly authorized: change
                                    $middleware->redirectUsersTo('/accounts')
                                    to
                                    $middleware->redirectUsersTo('/dashboard')
                                    -- this single line only; no other bootstrap/app.php
                                    content may change under this Decision Package)
resources/views/layouts/app.blade.php   (+1 nav link, following the exact pattern already used for every prior phase's nav addition)
```

### Files explicitly forbidden from modification
```
app/Models/Transaction.php, LedgerEntry.php, Account.php, Category.php, Budget.php,
  RecurringPaymentTemplate.php, PaymentObligation.php, ObligationAllocation.php
app/Domain/Money.php
app/Domain/Services/AccountBalanceService.php
app/Domain/Services/TransactionService.php, TransferService.php, RefundService.php, ReversalService.php
app/Domain/Services/PaymentObligationService.php
app/Domain/Services/ObligationAllocationService.php
app/Domain/Services/MonthlyGenerationService.php
app/Domain/Services/BudgetService.php
app/Domain/Services/OwnershipGuard.php
All Phase 1–4 migration files
All Phase 1–4 test files
```

**Explicitly restated (v1.1.0, all former Open Decisions now resolved without requiring new infrastructure):** no new migration, no new model, no new table, and no persistent alert/dismissal infrastructure of any kind is authorized beyond what is listed above. `SafeToSpendService` and `DashboardController` remain the **only** two new domain/controller components this Decision Package authorizes — every other Phase 5 need (investment classification, current-period membership, Attention Center rules, Budget Snapshot ordering, Paid Obligations metric, 14-day Upcoming Payments window) is satisfied by query logic inside those two components against the existing certified schema.

---

## 20b. Implementation Sequence

*(Numbered 20b for the same reason as 19b above.)*

1. Obtain final external GO on this v1.2.3 Decision Package (all financial-math-blocking Open Decisions are now resolved — section 22 lists only the two permanently-deferred, non-blocking Attention Center items).
2. `SafeToSpendService` — implement §9's formulas exactly (BR-028 via per-account `AccountBalanceService::calculate()` loop, BR-029/030 via SQL aggregate with the resolved Investment/Fixed partition, BR-031/032/033 via composition), unit-tested against hand-computed worked examples before any HTTP layer exists.
3. `bootstrap/app.php`'s single-line `redirectUsersTo()` change (§15b/§19b).
4. `DashboardController` + route + thin view, consuming `SafeToSpendService` and the existing `BudgetService`/`AccountBalanceService` — no financial calculation in the controller or view.
5. Attention Center rule evaluation (exactly the three resolved rules — §13).
6. Budget Snapshot / Account Snapshot / Upcoming Payments (`today` through `today+13`, 14-calendar-date window) sections of the view.
7. Full adversarial test matrix (§20).
8. Full regression run — Phase 1–4's 254/720 baseline must remain unchanged and green.
9. Implementation report + external audit bundle + source-code review bundle, following the exact document set and process this engagement has used for every prior phase.

No implementation code is written before step 1 completes (external GO on this Decision Package).

---

## 21. Change History

### v1.0.0 — 2026-08-10
Initial Phase 5 Decision Package, created per the Phase Decision Package governance process. No prior version exists. Historical planning material (if any existed conversationally before this document) was not preserved separately — this is the first and only version at this point.

### v1.1.0 — 2026-08-10 (same day, converged external review correction)

- **External review:** independent ChatGPT and Gemini adversarial review of v1.0.0, both converging on the same set of documentation blockers (per the correction request's own framing — this document did not independently verify reviewer identity or obtain the raw review transcripts beyond the correction instructions themselves).
- **Project Owner resolutions applied:**
  1. **BR-028 clarification:** Current Asset Balance MUST use `AccountBalanceService::calculate()` per asset account, never raw SQL `SUM()` aggregation — §9.1, §11.
  2. **Phase 4 `BudgetService` reuse clarification:** Phase 5 consumes `BudgetService::calculateUtilization()`'s result exactly as certified; no budget-utilization mathematics (including Transfer/`transaction_type` reasoning) are reimplemented in Phase 5 — §9.5.
  3. **Investment classification decision:** `category.category_type === 'INVESTMENT'` partitions obligations into Investment vs. Fixed, mutually exclusively, no migration — §9.3.
  4. **Current-period decision:** inclusive interval `period_start <= today <= period_end`, applied identically to BR-029/030/033 and Budget Snapshot — §15.
  5. **Closed-account decision:** CLOSED asset accounts with a balance are included in Current Asset Balance; liabilities are always excluded regardless of status — §9.1.
  6. **Paid-obligations definition:** count + total planned amount of `status = 'PAID'` obligations in the current period, mandatory and non-mandatory alike — §12.
  7. **Attention Center V1 scope:** exactly three rules (Negative Safe-to-Spend, Overdue Obligation, Budget Overspend); Missing Obligation, Overpayment Difference, Unaccounted Transactions, and Statement Mismatch explicitly deferred, not invented — §13.
  8. **Dismissal decision:** no dismissal mechanism of any kind in Phase 5 — fully derived/read-only, recalculated every request — §13.
  9. **14-day Upcoming Payments window:** fixed, inclusive `today` through `today + 14 days` — §15.
  10. **Budget Snapshot ordering:** deterministic (utilization % descending, category name ascending), no user-configurable setting — §14.
  11. **`/dashboard` redirect:** authenticated users land on `/dashboard`, not `/accounts` — §15b, §19b.
- **Resolution status:** all resolutions above are documentation-only; **none have been implemented**. See section 23.

### v1.2.0 — 2026-08-10 (same day, P1 correction — Upcoming Payments window contradiction)

- **Finding (P1):** v1.1.0 simultaneously stated the Upcoming Payments window as "next 14 calendar days" (prose) and as `due_date >= today AND due_date <= today + 14 days` (formula). The formula, taken literally, spans **15 calendar dates** (day 0 through day 14 inclusive) — a direct contradiction of the "14 calendar days" prose. This was not caught during v1.1.0's own review and was identified as a standalone P1 in a subsequent review pass.
- **Project Owner resolution:** the requirement is frozen as **exactly 14 calendar dates**: `today` through `today + 13 days`, inclusive at both ends (`due_date >= today AND due_date <= today + 13 days`). Exact boundary behavior, now unambiguous: due today (day 0) → included; due `today + 13` → included; due `today + 14` → excluded; due `today + 15` → excluded.
- **Sections corrected:** §15 (Date/Time Semantics — the authoritative formula), §11 (pagination bound description), §19 (Testing Strategy), §20 (Adversarial Test Matrix), §20b (Implementation Sequence step 6). No occurrence of the superseded `today + 14 days` formula remains in any active/current Phase 5 requirement. The v1.1.0 Change History entry (immediately above) intentionally preserves the superseded formula for historical traceability and is not altered by this correction.
- **Explicitly not reopened:** BR-028/`AccountBalanceService` authority, `BudgetService` authority, Investment classification, current-period semantics (the `period_start <= today <= period_end` test for obligations/budgets — a separate, unrelated formula from the Upcoming Payments window), Safe Balance formula, Safe-to-Spend formula, negative Safe-to-Spend behavior, Attention Center V1 scope, dismissal decision, closed-account decision, Paid Obligations definition, Budget Snapshot ordering, login redirect, migration boundary, and Phase 4 technical debt boundary — all unchanged from v1.1.0.
- **Resolution status:** documentation-only; **not implemented**. See section 23.

### v1.2.1 — 2026-08-10 (same day, P1/P2 documentation-only corrections)

v1.2.0 passed financial/architectural adversarial review. Two residual documentation inconsistencies, unrelated to financial mathematics, were identified and corrected:

- **P1 — Implementation Sequence version reference:** §20b step 1 still read "Obtain final external GO on this v1.1.0 Decision Package," stale after v1.2.0 superseded v1.1.0. Corrected to "Obtain final external GO on this v1.2.0 Decision Package."
- **P2 — Change History wording:** the v1.2.0 entry stated "No occurrence of the old `today + 14 days` formula remains anywhere in this document as of v1.2.0," which was technically false — the v1.1.0 Change History entry intentionally preserves the superseded formula for historical traceability and was never altered. Corrected to explicitly distinguish active/current Phase 5 requirements (where the superseded formula does not appear) from the Change History (where it is deliberately preserved as a historical record).
- **Explicitly not reopened:** every substantive decision from v1.1.0/v1.2.0 — BR-028/`AccountBalanceService` authority, `BudgetService` authority, Investment classification, current-period semantics, Safe Balance, Safe-to-Spend, negative Safe-to-Spend, Attention Center scope, dismissal decision, closed-account decision, Paid Obligations definition, Budget Snapshot ordering, the Upcoming Payments `today` through `today+13` inclusive window and `today+14` exclusion, login redirect, migration boundary, Phase 4 technical-debt boundary, testing requirements, and file boundary — all unchanged.
- **Resolution status:** documentation-only; **not implemented**. See section 23.

### v1.2.2 — 2026-08-10 (same day, Gemini-identified stale version reference)

- **Finding:** independent Gemini adversarial review identified that §20b Step 1 still referenced "this v1.2.0 Decision Package," stale after v1.2.1 superseded v1.2.0.
- **Correction:** the active implementation instruction in §20b Step 1 was corrected to reference v1.2.1.
- **No financial, architectural, scope, or implementation decision was changed.**
- **Resolution status:** documentation-only; **not implemented**. Implementation remains unauthorized pending final external review of v1.2.2. See section 23.

### v1.2.3 — 2026-08-10 (same day, Gemini-identified stale version reference)

- **Finding:** independent Gemini adversarial review identified that §20b Step 1 still referenced "this v1.2.1 Decision Package," stale after v1.2.2 superseded v1.2.1.
- **Correction:** the active implementation instruction in §20b Step 1 was corrected to reference v1.2.3.
- **No financial formula, architectural decision, scope boundary, testing requirement, or implementation authorization changed.** This is documentation/governance-only.
- **Resolution status:** documentation-only; **not implemented**. Implementation remains unauthorized pending external review of v1.2.3. See section 23.

---

## 22. Open Decisions / Blockers

**Status as of v1.1.0: every Open Decision that blocked financial mathematics, tenant isolation, historical integrity, or database architecture in v1.0.0 has been resolved by explicit Project Owner decision (section 21).** Two items remain open — both are permanently deferred, non-blocking, out-of-V1-scope Attention Center alert types, not implementation blockers:

1. **[Attention Center, deferred, non-blocking]** "Missing obligation" (`02_PRODUCT_SPECIFICATION.md` §5 example) has no deterministic definition anywhere in the frozen Knowledge Base. Per section 13, this is explicitly **not implemented** in Phase 5 V1 — it is not merely unresolved, it is out of scope until a future Decision Package defines it precisely. No rule is proposed or invented.

2. **[Attention Center, deferred, non-blocking]** "Overpayment difference" (`02_PRODUCT_SPECIFICATION.md` §5 example, grounded in BR-020's ₹41 example) has a real, theoretically computable underlying signal but no existing service exposes it and its exact scope is undefined. Per section 13, this is explicitly **not implemented** in Phase 5 V1, for the same reason as above.

Neither item requires resolution before Phase 5 implementation may proceed — they are scope exclusions, not blockers. If a future phase wishes to implement either, it requires its own Decision Package with a precise, non-invented definition, per the same standard this document has held itself to throughout.

**If any future correction to this document changes a formula in section 9, it must be resubmitted for external review before implementation proceeds — per the governance process this engagement has followed for every prior phase's corrections.**

---

## 23. Final Package Status

PHASE 5 IMPLEMENTATION: NOT AUTHORIZED

AWAITING INDEPENDENT CHATGPT/GEMINI ADVERSARIAL REVIEW
