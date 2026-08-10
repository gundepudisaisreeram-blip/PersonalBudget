# PHASE 4 DECISION PACKAGE

## 1. Document Control

- **Phase:** 4 — Obligations & Monthly Budget (Budgets, Recurring Payment Templates, Monthly Obligation Generation, Payment Obligations, Obligation Allocations)
- **Status:** Corrected per external adversarial review (P1 finding) — see section 24 (Change History) and Governance Status (section 25)
- **Version:** 1.1.0
- **Date:** 2026-08-10 (original), corrected 2026-08-10 (same day — see section 24)
- **Relationship to frozen Knowledge Base:** This document is subordinate to `00_DOMAIN_MODEL.md` through `10_IMPLEMENTATION_CONTRACT.md`. It does not amend, override, or reinterpret any frozen requirement. Where this document states a decision that narrows or operationalizes a frozen rule, the frozen rule is cited explicitly. Where this document records a decision that is not present in the frozen Knowledge Base, it is labeled as a Project Owner decision or a Phase 4 planning-level architectural decision, not as a Knowledge Base requirement.
- **Implementation status:** The scope originally described in v1.0.0 was implemented, tested, and manually verified prior to that version's creation. **This v1.1.0 correction is documentation-only and describes a REQUIRED but NOT YET IMPLEMENTED change** — the currently-implemented code still enforces the three ownership checks named in section 24 at the HTTP controller layer only, not at the domain-service layer. No PHP, Blade, JavaScript, CSS, migration, model, service, controller, request, policy, command, or test file was modified while producing this correction.

This document is the contract governing Phase 4 implementation, recorded after the fact to satisfy the version-control requirement newly introduced in `10_IMPLEMENTATION_CONTRACT.md`. See section 25 for why it is not self-certifying as "approved," and section 24 for the P1 correction cycle this version represents.

---

## 2. Authority Hierarchy

Per `docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md`, the frozen Knowledge Base (`00` through `09`, plus `10` itself for process) remains authoritative over this document at all times. This Decision Package operationalizes frozen requirements into an implementable plan; it does not have authority to change them.

If a contradiction is discovered between this document, the frozen Knowledge Base, or the existing certified architecture:

**STOP. Do not silently reinterpret the requirement. This Decision Package may be amended only after the contradiction has been reviewed and explicitly resolved by the Project Owner.**

No contradiction between this document and the frozen Knowledge Base was found during its preparation. One contradiction-adjacent issue — a procedural sequencing anomaly, not a substantive one — is disclosed in full in section 25 and in the Important Notes of the accompanying creation report, rather than resolved silently.

---

## 3. Phase 4 Objective

Phase 4 delivers the planning and commitment side of the budgeting workflow, built entirely on the certified Phase 1–3 ledger architecture:

- **Budgets** — category-and-period-scoped spending targets with computed utilization (Category A: named in `03_ARCHITECTURE.md` §4 Core Services as `BudgetService`; behavior grounded in BR-025).
- **Recurring Payment Templates** — user-defined monthly obligation blueprints (`frequency`, `due_rule`, category, optional default account, active window).
- **Monthly Obligation Generation** — idempotent, template-driven creation of `PaymentObligation` rows for a target calendar month (Category A: `MonthlyGenerationService` named in `03_ARCHITECTURE.md` §4; BR-018 governs idempotency).
- **Payment Obligations** — both recurring-generated and one-time, with lifecycle states (PENDING/PARTIALLY_PAID/PAID/SKIPPED/CANCELLED).
- **Obligation Allocations** — linking actual EXPENSE/TRANSFER transactions to obligations, with aggregate-limit enforcement.
- **UI/HTTP boundary** — thin controllers, Form Requests, Policies, and Blade views for all of the above, following the exact patterns certified in Phases 1–3.
- **Approved supporting functionality** — a manual HTTP recovery trigger and a Laravel Scheduler console command for monthly generation, sharing one service implementation.

### Explicitly out of scope

- Any Phase 5+ functionality (reconciliation execution, statement import, reporting/forecasting, Safe-to-Spend calculation) — none of it is touched, referenced, or partially built.
- Any modification to the certified Phase 1–3 financial ledger architecture (`Transaction`, `LedgerEntry`, `Money`, `TransactionService`, `TransferService`, `RefundService`, `ReversalService`, `AccountBalanceService`).
- Any database migration (see section 16).
- Hard deletion of Budgets, Recurring Payment Templates, or Payment Obligations (see section 5).
- Weekly/biweekly/yearly/custom recurring frequencies — `frequency` accepts only `MONTHLY` in this phase.
- Multi-currency or non-INR budget semantics — not introduced or assumed.

---

## 4. Existing Certified Architecture Reuse

Phase 4 is additive. It reuses, and does not modify, the following certified Phase 1–3 components:

| Component | Role in Phase 4 |
|---|---|
| `App\Models\Transaction` | Source of `transaction_type`/`transaction_date`/`category_id` that `BudgetService::calculateUtilization()` joins against. Immutability (`ImmutableRecordException` on update/delete) inherited unchanged. |
| `App\Models\LedgerEntry` | Source of `direction`/`amount` for the budget utilization sum. Immutability inherited unchanged. |
| `App\Domain\Money` | `Money::sub()` used for the utilization formula; no floating-point arithmetic introduced anywhere in Phase 4. |
| `App\Domain\Services\OwnershipGuard` | Extended with one new method, `assertBudgetOwnership()`, following the exact one-method-per-entity pattern already established for Accounts/Categories/Transactions/PaymentObligations/RecurringTemplates/ObligationAllocations. |
| `App\Domain\Services\AccountBalanceService` | Not used by any Phase 4 code path (confirmed by inspection — no Phase 4 file references it). Listed here only because it is named in `03_ARCHITECTURE.md` §4 as certified Phase 1–3 architecture that Phase 4 must not modify. |
| `App\Domain\Services\PaymentObligationService` | Reused unmodified as of v1.0.0. `createRecurringOccurrence()` and `createOneTime()` (both pre-existing) are the actual idempotency mechanism `MonthlyGenerationService` and the one-time HTTP path call into. **v1.1.0 correction:** a scoped modification to `createOneTime()` — adding an `OwnershipGuard::assertAccountOwnership()` assertion on `planned_account_id` before any write — is now authorized (not yet implemented; see section 24). This is the one exception to "reused unmodified" in this table, and it is a controlled, reviewed amendment to certified architecture, not an unreviewed one — `10_IMPLEMENTATION_CONTRACT.md`'s Frozen Architecture Rule permits amendment through exactly this kind of external-review-triggered correction cycle. |
| `App\Domain\Services\ObligationAllocationService` | Reused unmodified. Row-locking (`lockForUpdate()` on the obligation), `AllocationException`, and status recalculation are all pre-existing Phase 1–3 behavior that Phase 4's HTTP layer sits on top of. |
| `App\Domain\Services\TransactionService` / `TransferService` / `RefundService` / `ReversalService` | Not modified. Their existing behavior (single/double-entry construction, category inheritance on Refund/Reversal, each transaction's own `transaction_date`) is what makes the Budget Utilization formula in section 8 correct without any type-specific branching in Phase 4 code. |
| Phase 3 HTTP exception architecture (`bootstrap/app.php` `withExceptions()`, the `InvalidTransactionException`/`OwnershipViolationException` `render()` pattern) | Extended, not replaced, with two additional `render()` callbacks (`AllocationException`, `BudgetException`) using the identical `back()->withInput()->withErrors()` shape. |

No modification to certified financial architecture was made or is proposed.

---

## 5. Domain Model and Lifecycle

**Budget lifecycle:** create → edit (repeatedly). No terminal/deleted state exists. A Budget, once created, is a permanent record for V1. This is a Project Owner decision (Category B) grounded in the general historical-financial-protection principle already established in the frozen Knowledge Base (`08_CHANGELOG.md` baseline: "Historical financial protection"), operationalized specifically for Budgets during Phase 4 planning because no `status`/lifecycle column exists on `budgets` in the frozen schema and none was authorized to be added.

**Recurring Payment Template lifecycle:** create → edit (repeatedly) → cancel (`status: ACTIVE → CANCELLED`, terminal). Cancellation stops future generation only; it does not delete the template row or any historical `PaymentObligation` rows already generated from it. No un-cancel path exists in this phase.

**Payment Obligation lifecycle:** PENDING → PARTIALLY_PAID → PAID (driven by allocation totals, computed by the certified `ObligationAllocationService`) or PENDING → SKIPPED / PENDING → CANCELLED (only permitted while zero active allocations exist — enforced by the certified service, not new Phase 4 logic). No lifecycle state is deletable.

**Obligation Allocation lifecycle:** create (via `allocate()`) → optionally remove (`removeAllocation()`, a hard delete of the allocation row only, which is a link record, not a financial ledger record — removing it does not touch `Transaction`/`LedgerEntry`, and the parent obligation's status is recalculated from the remaining allocations).

**Ownership rules:** every entity in this phase carries `user_id` (Budget, RecurringPaymentTemplate, PaymentObligation, ObligationAllocation) and is checked at both the Policy layer (HTTP) and the `OwnershipGuard` layer (domain service), per the dual-layer pattern certified in Phases 1–3.

**Historical protection, explicitly prohibited hard deletion:**
- Budgets: no destroy route, no destroy controller method, no delete Policy ability — Project Owner decision (Category B), explicit and final.
- Recurring Payment Templates: no destroy route/method/ability; only `cancel()` (status mutation) exists.
- Payment Obligations: no destroy route/method/ability; only `skip()`/`cancel()` (status mutation, delegated to the certified `ObligationAllocationService`) exist.
- Obligation Allocations are the one exception: they are link records between an actual transaction and a planned obligation, not themselves a ledger or historical financial record, and removing one is an explicit, approved, ordinary user action (undoing a mis-linked payment) with no historical-protection conflict.

---

## 6. Budget Architecture

A Budget is defined by:
- `category_id` (required, must belong to the authenticated user or be a system category — enforced by `OwnershipGuard::assertCategoryOwnership()`)
- `period_start` / `period_end` (required dates, `period_end >= period_start`)
- `budget_amount` (required decimal(15,2), `>= 0` — matches the DB `CHECK (budget_amount >= 0)`; zero is an explicitly valid budget amount, not `> 0`)
- `is_mandatory_reserve` (boolean flag, default false; recorded and displayed, not used in utilization math)
- `user_id` (owner, never trusted from client input — always taken from the authenticated session)

**Validation:** Form Request rules (`StoreBudgetRequest`/`UpdateBudgetRequest`) enforce field shape and format only. The no-overlapping-period rule is intentionally not validated at the Form Request layer, because it requires row-locking for concurrency safety that a Form Request cannot provide (see section 7).

**Overlap rule:** no two Budgets for the same `user_id` + `category_id` may have overlapping `[period_start, period_end]` ranges, inclusive on both ends (a shared boundary date counts as overlapping). This is a Project Owner decision (Category B) — MySQL cannot enforce an arbitrary date-range exclusion constraint via a unique index, so enforcement is entirely application-level (confirmed live: no such unique constraint exists on `budgets`).

**Update rules:** see section 7 (self-exclusion during the overlap check).

**Historical protection:** see section 5. No delete surface exists at any layer.

---

## 7. Budget Overlap Serialization

This is the exact, frozen, Project-Owner-approved locking protocol (Category B) for both Budget CREATE and Budget UPDATE:

1. A database transaction begins (`DB::transaction()`).
2. The authenticated user's own `users` row is locked with `SELECT ... FOR UPDATE` (`User::query()->lockForUpdate()->findOrFail($user->id)`).
3. Only after this lock is acquired is the overlap query executed (`Budget::query()->where('user_id', ...)->where('category_id', ...)->where('period_start', '<=', $periodEnd)->where('period_end', '>=', $periodStart)->exists()`).
4. If an overlap exists, `BudgetException` is thrown and no write occurs — the transaction rolls back.
5. If no overlap exists, the insert (create) or update (update) is performed.
6. The transaction commits atomically.

**"The user-row lock is the serialization resource for budget overlap."** A brand-new Budget has no existing row of its own to lock before insertion — the phantom-row problem — so the authenticated user's own `users` row is used as the lock anchor instead, reusing the exact `lockForUpdate()` mechanism already certified in `ObligationAllocationService` (which locks the `PaymentObligation` row), applied to a different anchor for a different reason.

**"No overlap check may occur before acquiring this lock."** Both `BudgetService::createBudget()` and `BudgetService::updateBudget()` acquire the lock as the first statement inside the transaction, strictly before the `overlaps()` query.

**Update self-exclusion rule:** `updateBudget()` acquires the identical user-row lock, then calls the same `overlaps()` check with an additional `excludeBudgetId` parameter set to the budget being updated, so a budget never conflicts with its own pre-update period. Only after the exclusion-aware overlap check passes does the update write.

---

## 8. Budget Utilization Mathematics

**Frozen formula (Project Owner decision, Category B — absolutely frozen, not to be reopened):**

```
Net Budget Utilization = SUM(OUTFLOW ledger entries) − SUM(INFLOW ledger entries)
```

restricted to ledger entries whose transaction:
- belongs to the budget's `category_id`, and
- whose own `transaction_date` falls within `[period_start, period_end]` inclusive (BR-059: a transaction's period is derived from its own transaction date, regardless of any parent/original transaction's date).

This is computed as two separate direction-filtered `SUM()` queries combined via a single `Money::sub()` call — **never** via `transaction_type` branching. The `transaction_type IN ('EXPENSE','REFUND','REVERSAL')` restriction present in the implementation is retained per BR-025 ("Transfers and pure adjustments do not count") but is not the mechanism that produces correct Reversal-of-Reversal behavior — that behavior emerges purely from ledger direction, as required.

**Behavior by transaction type, all grounded in the certified Phase 1–3 services' actual ledger construction (section 4):**
- **Expense** (BR-013 area): one OUTFLOW entry → increases utilization by its amount.
- **Refund** (BR-014): one INFLOW entry, own `transaction_date` → reduces utilization in whichever period it falls into (which may differ from the original Expense's period).
- **Reversal** (BR-015): mirrors the parent's ledger entries with every direction inverted, own `transaction_date` → a Reversal of an Expense fully cancels that Expense's utilization contribution within the Reversal's own period.
- **Reversal-of-Reversal:** a Reversal of a Reversal inverts the already-inverted direction back to the original — this emerges automatically from `ReversalService`'s direction-flip logic with zero special-case code in `BudgetService`.
- **Transfer** (BR-013): always contributes one balanced OUTFLOW/INFLOW pair to the same category if categorized, which nets to zero arithmetically regardless of the `transaction_type` filter.
- **Adjustment** (BR-016): excluded by the `transaction_type` filter, per BR-025's "pure adjustments do not count."

**Frozen worked example (Project Owner decision, Category B — exact numbers, not to be reopened):**

```
Expense ₹10,000                        → utilization = ₹10,000
Reversal #1 of the Expense (INFLOW ₹10,000)   → utilization = ₹10,000 − ₹10,000 = ₹0
Reversal #2 of Reversal #1 (OUTFLOW ₹10,000)  → utilization = ₹0 + ₹10,000 = ₹10,000
```

Net utilization after all three transactions = **₹10,000**.

---

## 9. Recurring Obligation Generation

**Grammar (Project Owner decision, Category B, frozen):**
- `frequency` = `MONTHLY` only (no other value is valid in this phase).
- `due_rule` = `"DAY:N"` where `N` is an integer 1–31 (format enforced by `regex:/^DAY:([1-9]|[12][0-9]|3[01])$/` at the Form Request layer).

**Target period:** calendar-month boundaries (`startOfMonth()`/`endOfMonth()`) resolved in the user's own timezone (`$user->timezone ?? config('app.timezone')`), for either the current month (no argument) or an explicit `"Y-m"` target period string.

**`occurrence_key`:** the target period formatted as `"Y-m"`. Protected by the existing, unmodified `UNIQUE(recurring_payment_template_id, occurrence_key)` database constraint (live-confirmed as `payment_obligations_template_occurrence_unique`). Generation idempotency is achieved entirely through the pre-existing `PaymentObligationService::createRecurringOccurrence()`'s `firstOrCreate()` call against this constraint — no new idempotency mechanism was invented for Phase 4.

**Month-end clamping:** `due_date = min(N, days_in_target_month)`, computed via `min((int) $day, $periodStart->daysInMonth)` (Carbon's `daysInMonth`, which is leap-year-correct automatically).

**Clamp-before-boundary ordering:** clamping occurs strictly before `starts_on`/`ends_on` boundary evaluation — the boundary check operates on the already-clamped due date, never on the raw `N`.

**Boundary inclusivity (Project Owner decision, Category B, frozen):** both `starts_on` and `ends_on` are inclusive boundaries.

**Final generation invariant (frozen, verbatim):**

> After month-end clamping, an occurrence is generated only when its due date is on or after starts_on and, when ends_on exists, on or before ends_on.

**Frozen worked examples (all preserved exactly, all with a corresponding passing automated test):**
- `starts_on = 2026-01-20`, `DAY:5` → January suppressed (due date `2026-01-05` < `starts_on`); February generated (`2026-02-05` >= `starts_on`).
- `ends_on = 2026-06-10`, `DAY:15` → June suppressed (`2026-06-15` > `ends_on`).
- `ends_on = 2026-06-15`, `DAY:15` → June generated (`2026-06-15` == `ends_on`, inclusive).
- `ends_on = 2026-06-30`, `DAY:31` → June 30 generated (clamped due date `2026-06-30` == `ends_on`, inclusive).
- `ends_on = 2026-06-29`, `DAY:31` → June suppressed (clamped due date `2026-06-30` > `ends_on`).
- February `DAY:31` → clamps to February 28 (non-leap year) or February 29 (leap year) before boundary evaluation, in both cases.

---

## 10. Generation Architecture

**`MonthlyGenerationService`** (named in `03_ARCHITECTURE.md` §4, Category A) contains the entirety of the generation orchestration logic described in section 9: period resolution, template filtering, due-date clamping, boundary evaluation, and delegation to `PaymentObligationService::createRecurringOccurrence()` for the actual idempotent write.

**Manual trigger:** `PaymentObligationController::generate()` — an authenticated HTTP `POST /obligations/generate` route — calls `MonthlyGenerationService::generate($request->user(), $period)` directly. It contains no generation logic itself.

**Scheduled command:** `App\Console\Commands\GenerateMonthlyObligations` (`obligations:generate-monthly`), registered via a `->withSchedule()` closure in `bootstrap/app.php` (`$schedule->command(GenerateMonthlyObligations::class)->monthly()`) — the minimal mechanism for Laravel 11+'s slim skeleton (no `app/Console/Kernel.php` exists or was added). Its `handle()` method iterates every user (`User::query()->each(...)`) and calls `MonthlyGenerationService::generate($user)` for each. It contains no generation logic of its own.

**Shared service path, no duplication:** both entry points terminate in the identical `MonthlyGenerationService::generate()` → `PaymentObligationService::createRecurringOccurrence()` → `firstOrCreate()` path. Re-running one after the other for the same period is a verified no-op (see section 17/18).

---

## 11. Payment Obligations

**Generation:** recurring obligations are created exclusively through `MonthlyGenerationService`/`PaymentObligationService::createRecurringOccurrence()` (section 9–10). One-time obligations are created through `PaymentObligationService::createOneTime()`, keyed by a server-generated UUID `idempotency_key` (never trusted from client input — any client-supplied `idempotency_key` in the request payload is discarded and replaced).

**Ownership:** `user_id` is always the authenticated user; never accepted from client input (a spoofed `user_id` in the create payload is ignored, verified by test).

**Account/category references:** `category_id` ownership is asserted inside the certified `PaymentObligationService` (both creation paths) — this was already correct and requires no change. `planned_account_id` ownership is **currently** asserted only at the `PaymentObligationController` HTTP layer, not inside `createOneTime()` (a verified characteristic of the certified Phase 1 service as implemented for v1.0.0 — it accepts the ID as a raw nullable int with no internal check).

**CORRECTED as of v1.1.0 (not yet implemented — see section 24):** per the external reviewer's P1 finding, HTTP-layer validation alone is insufficient tenant protection, because it does not apply to any caller that bypasses the controller (direct service invocation, a future console command, a queued job, a test). The authoritative check MUST move to the domain-service boundary: `PaymentObligationService::createOneTime()` must itself call `OwnershipGuard::assertAccountOwnership()` on `planned_account_id` before any write, exactly as it already does for `category_id`. The HTTP-layer check in `PaymentObligationController` may remain in place afterward as early/UX validation, but must not be relied upon as the only protection. See section 13.1 for the full defense-in-depth contract and section 19 for the pending file-boundary update.

**Lifecycle / skip / cancel:** delegated entirely to the certified `ObligationAllocationService::skip()`/`cancel()`, which reject the transition (`AllocationException`) if any active allocation exists.

**Interaction with recurring templates:** `recurring_payment_template_id` + `occurrence_key` for recurring obligations (mutually exclusive with `idempotency_key` at the database level via `chk_payment_obligations_identity`); `idempotency_key` alone for one-time obligations.

**Historical protection:** section 5.

---

## 12. Obligation Allocations

**Allocation rules:** only `EXPENSE`/`TRANSFER` transactions are eligible (`Transaction::TYPES_ELIGIBLE_FOR_ALLOCATION`, certified Phase 1–3 constant, unmodified). Allocated amount must be positive (`Money::isPositive()`). The obligation must be active (not SKIPPED/CANCELLED).

**Ownership:** both the obligation and the transaction are asserted via `OwnershipGuard` inside the certified `ObligationAllocationService::allocate()`. At the HTTP boundary, the `transaction_id` field uses a Form Request closure rule (`$this->user()->transactions()->find($value) === null`) that mirrors the exact pattern already certified for Phase 3's `parent_transaction_id` — a cross-tenant or nonexistent transaction ID produces a `ValidationException` (302 back + field error), not a 403, and is byte-identical between the two cases so no tenant information is disclosed via response shape.

**Planned amount limits:** the aggregate of all active allocations against an obligation may never exceed `planned_amount` — enforced inside the row-locked (`lockForUpdate()` on the `PaymentObligation`) transaction in the certified service, not re-implemented in Phase 4 code.

**Locking/concurrency behavior:** unchanged from the certified Phase 1 mechanism — the obligation row is locked before the aggregate total is computed and before the status is recalculated, inside a single `DB::transaction()`.

**Over-allocation handling:** rejected with `AllocationException` before any write.

**HTTP exception behavior:** section 14.

---

## 13. Tenant Isolation and Authorization

No browser-supplied ID is ever trusted as sufficient authorization on its own. For every user-controlled foreign key introduced or touched in Phase 4:

| Foreign key | Form Request validation | Policy | OwnershipGuard (CURRENT, v1.0.0) | OwnershipGuard (CORRECTED, v1.1.0 — pending) | DB constraint |
|---|---|---|---|---|---|
| `RecurringPaymentTemplate.category_id` | format only (`exists:`) | n/a (checked pre-write) | `assertCategoryOwnership()`, controller only | **Must also be asserted at the domain-service boundary — no such service currently exists (open architectural item, section 23)** | FK to `categories` |
| `RecurringPaymentTemplate.default_account_id` | format only | n/a | `assertAccountOwnership()`, controller only | **Must also be asserted at the domain-service boundary — no such service currently exists (open architectural item, section 23)** | FK to `accounts` |
| `PaymentObligation.category_id` (one-time) | format only | n/a | `assertCategoryOwnership()`, inside certified service | Already compliant — no change needed | FK to `categories` |
| `PaymentObligation.planned_account_id` (one-time) | format only | n/a | `assertAccountOwnership()`, controller only | **Must move into `PaymentObligationService::createOneTime()` (pending, section 11/24)** | FK to `accounts` |
| `ObligationAllocation.transaction_id` | tenant-scoped closure rule | `update` on parent obligation | tenant-scoped controller query + `assertTransactionOwnership()` in certified service | Already compliant — no change needed | FK to `transactions` |
| `ObligationAllocation.payment_obligation_id` | route-model-bound | `update` on obligation (store) / `delete` on allocation (destroy) | `assertPaymentObligationOwnership()` in certified service | Already compliant — no change needed | FK to `payment_obligations` |
| `Budget.category_id` | format only | n/a | `assertCategoryOwnership()`, in `BudgetService` | Already compliant — no change needed | FK to `categories` |
| `Budget` itself (update) | route-model-bound | `update` | `assertBudgetOwnership()`, new method, in `BudgetService` | Already compliant — no change needed | n/a |

Entity-level view/edit/cancel access (as opposed to individual foreign keys) is additionally gated by a Laravel Policy on every controller action that touches an existing row (`RecurringPaymentTemplatePolicy`, `PaymentObligationPolicy`, `ObligationAllocationPolicy`, `BudgetPolicy`), each following the `$entity->user_id === $user->id` shape already certified in Phases 1–3, auto-discovered by Laravel's naming convention (no explicit registration exists or is required, matching the pre-existing pattern). Policies are unaffected by this correction — the P1 finding is specific to the three foreign-key ownership checks above, not to entity-level access.

### 13.1 Defense-in-Depth Contract (added in v1.1.0 P1 correction)

Per the external reviewer's finding, tenant ownership enforcement in this phase must follow a layered model, identical in spirit to the pattern already certified in Phase 1's `ObligationAllocationService`, `TransactionService`, `TransferService`, `RefundService`, `ReversalService`, and Phase 4's own `BudgetService` — every one of which asserts ownership *inside* the service, not only at the HTTP boundary:

- **HTTP layer** (Form Requests, controllers): early validation and user-friendly validation errors. Permitted and useful for UX (fast rejection, a validation-style error instead of a 403), but **not sufficient on its own**.
- **Domain Service layer**: authoritative `OwnershipGuard` enforcement. This is the layer that protects every caller — HTTP controllers, console commands, scheduled jobs, queued jobs, internal services, future integrations, and direct test/service invocation alike.
- **Database**: applicable foreign-key constraints (referential integrity only — FKs do not enforce *tenant* ownership, only that the referenced row exists).
- **Tests**: adversarial proof required at both the HTTP boundary AND the domain-service boundary (section 17).

No single HTTP validation rule may be considered sufficient tenant protection. `RecurringPaymentTemplateController` and (for `planned_account_id`) `PaymentObligationController` currently violate this contract, because the only ownership check for the three foreign keys named in the table above lives in the controller. This is the substance of the P1 finding.

---

## 14. HTTP Exception Contract

Phase 4 extends, and does not replace, the Phase 3 exception architecture in `bootstrap/app.php`'s `withExceptions()` closure.

| Exception | Source | Response | withInput() | Rollback |
|---|---|---|---|---|
| `Illuminate\Validation\ValidationException` | Form Request `rules()`, including closure rules | 302 redirect back, per-field `withErrors()` | Yes | N/A (no write attempted) |
| `App\Domain\Exceptions\AllocationException` | `ObligationAllocationService` (certified, unmodified) | 302 redirect back, `withErrors(['obligation' => ...])` | Yes | Yes (thrown inside `DB::transaction()`) |
| `App\Domain\Exceptions\BudgetException` | `BudgetService` (new, Phase 4) | 302 redirect back, `withErrors(['budget' => ...])` | Yes | Yes (thrown inside `DB::transaction()`) |
| `App\Domain\Exceptions\OwnershipViolationException` | `OwnershipGuard` (certified, unmodified) | Generic 403, no message disclosure | N/A | Yes, if inside a transaction |
| Laravel `AuthorizationException` (Policy `authorize()` returning false) | Policy classes | Laravel's default 403 | N/A | N/A |

No Phase 4 request ever produces an unhandled 500 for a normal (non-malicious, non-malformed-beyond-validation) browser request. Every domain rejection either rolls back cleanly inside its `DB::transaction()` before any partial write, or is rejected before a transaction is opened at all (Form Request validation).

---

## 15. UI/HTTP Scope

**Routes** (all under the existing `auth` middleware group in `routes/web.php`): `recurring-templates.{index,create,store,show,edit,update,cancel}`, `obligations.{index,create,store,generate,show,skip,cancel}`, `obligations.allocations.{create,store,destroy}`, `budgets.{index,create,store,edit,update}`. No delete route exists for any Phase 4 entity except `obligations.allocations.destroy`.

**Controllers:** `RecurringPaymentTemplateController`, `PaymentObligationController`, `ObligationAllocationController`, `BudgetController` — each thin: they resolve route-bound models, call `OwnershipGuard`/Policy checks, delegate all business logic to domain services, and return a view or redirect. No financial calculation occurs in any controller.

**Requests:** `Store`/`UpdateRecurringPaymentTemplateRequest`, `StoreOneTimeObligationRequest`, `StoreObligationAllocationRequest`, `Store`/`UpdateBudgetRequest` — format/shape validation only; domain rules requiring row-locking or cross-record checks are deliberately left to the service layer.

**Policies:** section 13.

**Views:** 13 Blade templates across `resources/views/{recurring-templates,obligations,budgets}/`, extending the existing `layouts.app` (one new nav link added). `budgets/index.blade.php` performs presentation-only arithmetic (`remaining`/`percent` display formatting) on the two already-computed server-side decimal strings — this is not a financial calculation path; the authoritative calculation is `BudgetService::calculateUtilization()`.

**Filtering/pagination:** `obligations.index` supports `status`/`category_id`/`period_start` filters and paginates 20 per page (`->paginate(20)->withQueryString()`).

**No financial calculations in Blade or controllers:** confirmed by inspection — `Money`/ledger arithmetic occurs exclusively inside `BudgetService`, `PaymentObligationService`, and `ObligationAllocationService`.

---

## 16. Database / Migration Boundary

**No database migrations are authorized for Phase 4.** The `budgets`, `recurring_payment_templates`, `payment_obligations`, and `obligation_allocations` tables, along with their columns, foreign keys, unique constraints (`payment_obligations_template_occurrence_unique`, `payment_obligations_idempotency_key_unique`), and CHECK constraints (`chk_budgets_amount_non_negative`, `chk_payment_obligations_identity`) already existed in the original migration batch prior to Phase 4 and required no schema change. This was confirmed live via `php artisan migrate:status` (18 migrations, single batch, all previously run) — no Phase-4-specific migration file exists, and none was created.

---

## 17. Testing Strategy

Functional, authorization, mathematical, lifecycle, concurrency, and adversarial coverage exists across 10 new Phase 4 test files (`tests/Feature/{Budgets,Obligations,RecurringTemplates}/`), all passing as part of the full 252-test/716-assertion regression suite.

**All five required budget overlap tests are present** (`tests/Feature/Budgets/BudgetOverlapTest.php`):
1. `test_overlapping_create_is_rejected`
2. `test_overlapping_update_is_rejected`
3. `test_non_overlapping_update_succeeds`
4. `test_concurrent_create_create_produces_exactly_one_budget`
5. `test_concurrent_create_update_cannot_produce_overlapping_budgets`

(Plus four additional non-required-but-present sequential-correctness tests: self-overlap-on-update, adjacent-periods-succeed, boundary-touching-is-rejected, different-category-never-conflicts.)

**All six `ends_on`/`starts_on` generation boundary cases are present** (`tests/Feature/Obligations/MonthlyGenerationTest.php`): due-date-before-starts_on-suppressed, due-date-equal-to-starts_on-generated, due-date-after-ends_on-suppressed, due-date-equal-to-ends_on-generated, clamped-DAY:31-equal-to-ends_on-generated, clamped-DAY:31-one-day-past-ends_on-suppressed. (Plus month-end clamping across January/February-non-leap/February-leap/April/June, idempotency-on-rerun, cancelled-template-never-generated, and the manual-trigger/console-command equivalence tests.)

**Real HTTP tests exist for HTTP-facing behavior:** ownership tests use `actingAs()`/`assertForbidden()`/`assertSessionHasErrors()` against actual routes, not direct service calls, for every controller action.

**Real concurrent database connections were used for concurrency claims:** both required concurrency tests register a second, independent Laravel database connection (`Config::set('database.connections.locktest', ...)`), hold Connection A's transaction open uncommitted while Connection B attempts the same `lockForUpdate()`, set a 1-second `innodb_lock_wait_timeout` on Connection B, and assert both that Connection B was blocked (`Throwable` caught) and that wall-clock elapsed time was `>= 1.0` seconds — a genuine mid-flight lock-contention proof, not a sequential approximation.

**Added in v1.1.0 (requirement documented, tests NOT YET WRITTEN — see section 24):** per the P1 correction, ownership for `RecurringPaymentTemplate.category_id`/`default_account_id` and `PaymentObligation.planned_account_id` must be proven at both boundaries:

- **HTTP tests** (already exist and pass — `test_a_spoofed_category_id_belonging_to_another_user_is_rejected_with_a_generic_403`, `test_a_spoofed_default_account_id_belonging_to_another_user_is_rejected_with_a_generic_403`, `test_a_spoofed_planned_account_id_on_one_time_creation_is_rejected_with_a_generic_403`): User A cannot submit User B's `category_id`/`default_account_id`/`planned_account_id` through the HTTP layer and receives the approved validation/authorization behavior (generic 403, per the current controller-layer check). These remain valid and must continue passing after the correction — the correction adds a second layer, it does not remove this one.
- **New domain-service tests (required, not yet written):** must prove that User A cannot directly invoke `RecurringPaymentTemplate`'s creation/update path (once section 23's open architectural item is resolved and a service boundary exists) or `PaymentObligationService::createOneTime()` using User B's category/default-account/planned-account, **even when bypassing the HTTP Form Request and controller entirely** — i.e., a test that instantiates the service directly (the same pattern already used throughout the existing test suite, e.g. `new BudgetService(new OwnershipGuard)` in `BudgetOverlapTest`) and asserts an `OwnershipViolationException` is thrown, zero database write occurs, and `OwnershipGuard` is the actual mechanism exercised (not merely that the end-to-end HTTP result happens to be correct). A same-user reference must still succeed in the same direct-service test, proving the check is precise, not merely fail-closed.

---

## 18. Adversarial Test Matrix

| Attack | Test | Result |
|---|---|---|
| Spoofed `category_id` on recurring template create | `test_a_spoofed_category_id_belonging_to_another_user_is_rejected_with_a_generic_403` | 403, no row created |
| Spoofed `default_account_id` on recurring template create | `test_a_spoofed_default_account_id_belonging_to_another_user_is_rejected_with_a_generic_403` | 403, no row created |
| Spoofed `category_id` on one-time obligation create | `test_a_spoofed_category_id_on_one_time_creation_is_rejected_with_a_generic_403` | 403, no row created |
| Spoofed `planned_account_id` on one-time obligation create | `test_a_spoofed_planned_account_id_on_one_time_creation_is_rejected_with_a_generic_403` | 403, no row created |
| Spoofed `user_id` in obligation/budget/template create payload | `test_a_spoofed_user_id_in_the_create_payload_is_ignored` (×2) | authenticated user's ID used, payload ignored |
| Cross-tenant `transaction_id` on allocation | `test_a_cross_tenant_transaction_id_is_a_validation_error_not_a_403` | validation error, not 403, no disclosure |
| Cross-tenant view/edit/cancel/skip/delete on every entity | `*OwnershipTest.php` (×3 files) | 403 on every route |
| Overlapping budget create | `test_overlapping_create_is_rejected` | `BudgetException`, no write |
| Overlapping budget update | `test_overlapping_update_is_rejected` | `BudgetException`, no write |
| Concurrent budget create/create | `test_concurrent_create_create_produces_exactly_one_budget` | genuine lock contention, exactly one row |
| Concurrent budget create/update | `test_concurrent_create_update_cannot_produce_overlapping_budgets` | genuine lock contention, no overlapping row |
| Duplicate generation for the same period | `test_generation_is_idempotent_when_run_twice_for_the_same_period`, `test_re_running_a_suppressed_period_remains_suppressed_and_stable` | no duplicate rows |
| `DAY:31` across every month length / leap year | 5 clamping tests | correct clamp in every case |
| `starts_on` boundary (before/equal) | 2 tests | correct suppress/generate |
| `ends_on` boundary (before/equal/clamped-equal/clamped-after) | 4 tests | correct suppress/generate |
| Cancelled template generation attempt | `test_a_cancelled_template_is_never_generated` | zero rows created |
| Over-allocation (BR-020 scenario) | `test_the_exact_br020_overpayment_scenario_through_http` | `AllocationException`, remaining amount rejected |
| Allocating an INCOME transaction (ineligible type) | `test_allocating_an_income_transaction_redirects_back_with_input_preserved` | rejected, redirect back |
| Skip/cancel with active allocations | `test_skipping_an_obligation_with_active_allocations_redirects_back_with_input_preserved` | rejected, status unchanged |
| Historical deletion attempt on a budget | `test_there_is_no_delete_route_for_budgets` | HTTP 405, row survives |
| Historical deletion attempt on a cancelled template | `test_cancelling_a_template_never_deletes_it_or_its_historical_obligations` | row survives |
| **(v1.1.0, pending)** Direct service-call bypass of `category_id`/`default_account_id` ownership for recurring templates | Not yet written — blocked on section 23's open architectural item | Required: `OwnershipViolationException`, zero write |
| **(v1.1.0, pending)** Direct service-call bypass of `planned_account_id` ownership via `PaymentObligationService::createOneTime()` | Not yet written | Required: `OwnershipViolationException`, zero write |

---

## 19. Exact Implementation File Boundary

Populated by direct inspection of the current repository state (`git status`, live file reads), not invented.

### Files created

```
app/Console/Commands/GenerateMonthlyObligations.php
app/Domain/Exceptions/BudgetException.php
app/Domain/Services/BudgetService.php
app/Domain/Services/MonthlyGenerationService.php
app/Http/Controllers/BudgetController.php
app/Http/Controllers/ObligationAllocationController.php
app/Http/Controllers/PaymentObligationController.php
app/Http/Controllers/RecurringPaymentTemplateController.php
app/Http/Requests/StoreBudgetRequest.php
app/Http/Requests/StoreObligationAllocationRequest.php
app/Http/Requests/StoreOneTimeObligationRequest.php
app/Http/Requests/StoreRecurringPaymentTemplateRequest.php
app/Http/Requests/UpdateBudgetRequest.php
app/Http/Requests/UpdateRecurringPaymentTemplateRequest.php
app/Policies/BudgetPolicy.php
app/Policies/ObligationAllocationPolicy.php
app/Policies/PaymentObligationPolicy.php
app/Policies/RecurringPaymentTemplatePolicy.php
resources/views/budgets/{_form,create,edit,index}.blade.php
resources/views/obligations/{create,index,show}.blade.php
resources/views/obligations/allocations/create.blade.php
resources/views/recurring-templates/{_form,create,edit,index,show}.blade.php
tests/Feature/Budgets/{BudgetCrudTest,BudgetOwnershipTest,BudgetOverlapTest,BudgetUtilizationTest}.php
tests/Feature/Obligations/{MonthlyGenerationTest,ObligationAllocationHttpTest,ObligationCrudTest,ObligationOwnershipTest}.php
tests/Feature/RecurringTemplates/{RecurringTemplateCrudTest,RecurringTemplateOwnershipTest}.php
docs/knowledge_base/phase_decisions/PHASE_4_DECISION_PACKAGE.md (this file)
```

### Files modified

```
app/Domain/Services/OwnershipGuard.php   (+1 method: assertBudgetOwnership)
bootstrap/app.php                         (+withSchedule closure, +2 render() callbacks)
resources/views/layouts/app.blade.php     (+1 nav link)
routes/web.php                            (+13 named routes)
```

### Files explicitly forbidden from modification (and confirmed untouched as of v1.0.0)

```
app/Models/Transaction.php
app/Models/LedgerEntry.php
app/Domain/Money.php
app/Domain/Services/TransactionService.php
app/Domain/Services/TransferService.php
app/Domain/Services/RefundService.php
app/Domain/Services/ReversalService.php
app/Domain/Services/AccountBalanceService.php
app/Domain/Services/PaymentObligationService.php   -- see "Pending correction" below: v1.1.0 authorizes ONE scoped exception to this
app/Domain/Services/ObligationAllocationService.php
All Phase 1–3 migration files
All Phase 1–3 test files (Accounts, Categories, Transactions, Refunds, Reversals, Transfers, Adjustments)
```

### Pending correction (v1.1.0 — authorized, NOT YET IMPLEMENTED)

```
app/Domain/Services/PaymentObligationService.php
    -- add OwnershipGuard::assertAccountOwnership() on planned_account_id
       inside createOneTime(), before any write.
       This is the one authorized, reviewed exception to the "forbidden from
       modification" list above -- see section 4 and section 24.

app/Http/Controllers/RecurringPaymentTemplateController.php
    -- unchanged in file identity, but its existing OwnershipGuard calls become
       the HTTP-layer (non-authoritative) check once the domain-service boundary
       (open architectural item, section 23) exists and becomes authoritative.

[NEW FILE -- NAME AND EXISTENCE NOT YET DECIDED]
    -- a domain service responsible for RecurringPaymentTemplate creation/update
       does not currently exist. One is required to host the authoritative
       OwnershipGuard checks for category_id/default_account_id. This file is
       intentionally not named or created here -- see section 23, Open
       Architectural Item 1. Do not treat this line as authorization to create
       a specific file; it records that one is needed.

New domain-service-boundary adversarial tests (section 17/18) -- not yet written.
```

---

## 20. Acceptance Criteria

Objective, pass/fail only:

- [x] All relevant tests pass — 252/252, 716 assertions, 0 failures (live-executed).
- [x] `vendor/bin/pint --test` passes clean.
- [x] Concurrency tests genuinely exercise separate DB connections — verified by wall-clock `elapsed >= 1.0s` assertions and `Throwable` catch on the second connection, not sequential calls.
- [x] No schema drift — `php artisan migrate:status` shows 18 migrations, single batch, all previously run; no new migration exists.
- [x] No financial ledger regression — `Transaction`/`LedgerEntry`/`Money`/certified transaction services unmodified; full Phase 1–3 regression suite still passes within the same 252-test run.
- [x] No cross-tenant access — every adversarial test in section 18 passes.
- [x] Deterministic generation — idempotency tests pass; the manual-trigger/console-command equivalence test passes.
- [x] Deterministic budget utilization — the frozen Reversal-of-Reversal worked example test passes with the exact frozen numbers.
- [x] HTTP exceptions handled safely — no unhandled 500s; every domain exception maps to a redirect-back-with-errors or generic 403 (section 14).
- [x] No Phase 5+ scope — confirmed by the file boundary in section 19; nothing outside Budgets/Templates/Generation/Obligations/Allocations was touched.

---

## 21. Implementation Sequence

The order actually followed, recorded here rather than prescribed in advance (this document is retrospective — see section 25):

1. `BudgetException` (new domain exception).
2. `OwnershipGuard::assertBudgetOwnership()` (extension of certified service).
3. `BudgetService` (create/update with overlap locking, `calculateUtilization()`).
4. `MonthlyGenerationService` (generation orchestration over the certified `PaymentObligationService`).
5. `GenerateMonthlyObligations` console command + `bootstrap/app.php` `withSchedule()` registration.
6. `bootstrap/app.php` `withExceptions()` extension (`AllocationException`, `BudgetException` render callbacks).
7. Policies (4) and Form Requests (6).
8. Controllers (4), routes, Blade views.
9. Test suite (10 files) covering CRUD, ownership, generation boundaries, overlap/concurrency, and utilization mathematics.
10. Full regression run, Pint, and a manual `migrate:fresh` → `db:seed` ×2 → `migrate:rollback` → `migrate` cycle.
11. Manual HTTP verification pass.
12. Retrospective creation of this Decision Package (current step).

No alternate sequence was recorded as rejected; this is simply the order followed.

---

## 22. Resolved Decisions

1. Budget utilization formula: `SUM(OUTFLOW) − SUM(INFLOW)`, restricted to the budget's category and period, computed via ledger direction only — never `transaction_type` branching (section 8).
2. Reversal treatment: emerges automatically from `ReversalService`'s direction-inversion; the frozen Reversal-of-Reversal worked example (₹10,000 → ₹0 → ₹10,000) is preserved exactly (section 8).
3. Budget overlap is prohibited per category/period, application-enforced (section 6).
4. User-row (`SELECT ... FOR UPDATE` on `users`) is the serialization resource for budget overlap, for both create and update (section 7).
5. Update overlap behavior: identical lock protocol, with self-exclusion by budget ID (section 7).
6. Hard deletion of Budgets, Recurring Payment Templates, and Payment Obligations is prohibited for V1 (section 5).
7. Recurring grammar: `frequency = MONTHLY` only; `due_rule = "DAY:N"`, N 1–31 (section 9).
8. Month-end clamping: `due_date = min(N, days_in_target_month)` (section 9).
9. `starts_on` is an inclusive lower boundary (section 9).
10. `ends_on` is an inclusive upper boundary (section 9).
11. Clamp-before-boundary ordering: clamping always precedes `starts_on`/`ends_on` evaluation (section 9).
12. Occurrence idempotency is achieved via the pre-existing `UNIQUE(recurring_payment_template_id, occurrence_key)` constraint and `firstOrCreate()` — no new mechanism (section 9).
13. Manual HTTP trigger and scheduled console command share exactly one `MonthlyGenerationService` implementation with zero duplicated generation logic (section 10).
14. `AllocationException` and `BudgetException` are mapped in `bootstrap/app.php` using the identical `back()->withInput()->withErrors()` pattern already established for `InvalidTransactionException` (section 14).
15. **(v1.1.0)** Tenant ownership for `RecurringPaymentTemplate.category_id`, `RecurringPaymentTemplate.default_account_id`, and `PaymentObligation.planned_account_id` MUST be authoritatively enforced at the domain-service layer via `OwnershipGuard`, not solely at the HTTP controller layer. HTTP-layer checks remain permitted and required for UX (early rejection, validation-style errors) but are not, by themselves, sufficient tenant protection (section 13.1). This resolves the external reviewer's P1 finding at the decision level; implementation is pending (section 24).

---

## 23. Remaining Open Decisions

With respect to the substantive Phase 4 financial/generation architecture (budget math, overlap serialization, recurring grammar, idempotency, HTTP exception mapping) listed in section 22 items 1–14: **NONE** — all were explicitly resolved by the Project Owner across the planning turns that preceded implementation, and all remain reflected without alteration in the implemented code. This P1 correction does not reopen any of them (per the reviewer's own instruction to preserve them).

Two items are explicitly open, not silently decided:

1. **Open Architectural Item (new, v1.1.0):** no domain service currently exists for `RecurringPaymentTemplate` creation/update — the controller performs plain Eloquent writes directly. Item 15 in section 22 requires an authoritative domain-service-layer ownership check for this entity's `category_id`/`default_account_id`, but this document does not decide how that boundary should be created (a new dedicated service; folding it into an existing one; or some other mechanism) — per explicit instruction not to invent a service structure merely to satisfy an audit. This must be decided before the corresponding part of the P1 correction can be implemented.
2. **Procedural scope-classification question (carried over from v1.0.0, unresolved):** whether the two ownership-hardening fixes originally discovered during implementation (the same three checks this correction now addresses) constituted in-architecture corrections or material scope changes requiring a Decision Package amendment *before* being written, is still not decided here. This v1.1.0 correction resolves the forward-looking architectural question (where the check must live) but does not retroactively rule on that classification question.

---

## 24. Change History

### v1.0.0 — 2026-08-10

**Historical record reconstructed from available project artifacts.** No separate version-controlled Decision Package existed prior to this file. The decisions recorded above were established across a sequence of conversational planning and adversarial-correction turns with the Project Owner, culminating in an explicit "STATUS: GO — PHASE 4 DECISION PACKAGE APPROVED" implementation authorization, after which the code in section 19 was written, tested, and manually verified. Exact timestamps, reviewer identities, and turn-by-turn dates for that planning sequence are not independently available to this document and are not fabricated here. This file itself was created 2026-08-10, after implementation was already complete, as a retrospective formalization requested to satisfy the version-control requirement newly introduced in `10_IMPLEMENTATION_CONTRACT.md`.

### v1.1.0 — 2026-08-10 (same day, P1 correction)

- **External reviewer:** Gemini (per the correction request; this document did not independently verify reviewer identity beyond the request's own attribution).
- **Finding:** P1 — Weakened Domain Boundary / Tenant Isolation Drift. Ownership validation for `RecurringPaymentTemplate.category_id`, `RecurringPaymentTemplate.default_account_id`, and `PaymentObligation.planned_account_id` was described (v1.0.0) as enforced at the HTTP controller layer, which the reviewer identified as inconsistent with the established architecture and `10_IMPLEMENTATION_CONTRACT.md`'s Tenant/Ownership Contract.
- **Affected areas:** sections 4, 11, 13 (+ new 13.1), 17, 18, 19, 22, 23 of this document. No other section was touched.
- **Repository evidence inspected before correcting this document:**
  - `app/Http/Controllers/RecurringPaymentTemplateController.php` — read fresh: `store()`/`update()` call `OwnershipGuard::assertCategoryOwnership()`/`assertAccountOwnership()` directly in the controller, then write via plain Eloquent (`$request->user()->recurringPaymentTemplates()->create(...)`/`->update(...)`). Confirmed: **no domain service exists for this entity's writes at all.**
  - `app/Domain/Services/PaymentObligationService.php` — read fresh: `createOneTime()` asserts `category_id` ownership internally (already compliant) but accepts `planned_account_id` as a raw nullable int with no internal ownership assertion — confirmed the gap is real and exactly as the reviewer described.
  - `app/Http/Controllers/PaymentObligationController.php` — confirmed the `planned_account_id` check currently lives only in the controller, calling `OwnershipGuard::assertAccountOwnership()` before invoking the service.
- **Knowledge Base alignment check (section 2/8 of this task's own instructions):** `03_ARCHITECTURE.md` §4 "Core Services" does not name a service for `RecurringPaymentTemplate` specifically, but explicitly states service names/structure "may change if implementation demonstrates a better coherent structure" — introducing one would not contradict this. More importantly, every *other* domain service in this codebase (`TransactionService`, `TransferService`, `RefundService`, `ReversalService`, `ObligationAllocationService`, `BudgetService`, and `PaymentObligationService` itself for `category_id`) already asserts ownership internally via `OwnershipGuard` — `RecurringPaymentTemplateController`'s controller-only check is the sole outlier in the entire codebase, not the established pattern. **No contradiction with the frozen Knowledge Base or existing certified architecture was found; the finding is consistent with, and strengthens conformance to, the already-established pattern.** The "reuse certified Phase 1–3 services unmodified" constraint from v1.0.0 is not violated by the one scoped `PaymentObligationService` change this correction authorizes — `10_IMPLEMENTATION_CONTRACT.md`'s Frozen Architecture Rule permits amendment through a reviewed correction cycle, which is exactly what this is.
- **Correction:** documented in sections 4, 11, 13/13.1, 17, 18, 19, 22 (item 15), 23 (item 1) above. `PaymentObligation.category_id`, `ObligationAllocation.*`, and `Budget.*` were inspected and found already compliant — no change needed for those.
- **Reason:** HTTP-only enforcement does not protect non-HTTP callers (console commands, scheduled jobs, queued jobs, direct/internal service invocation, tests), which is a genuine tenant-isolation gap regardless of the fact that the only current caller happens to be the HTTP controller.
- **Implementation status:** NOT IMPLEMENTED. **Decision Package corrected — implementation authorization remains pending external re-review.**

---

## 25. Governance Status

**PHASE 4 DECISION PACKAGE — REQUIRES EXTERNAL REVIEW**

This status is chosen deliberately over "FROZEN FOR IMPLEMENTATION," for a specific, disclosed reason rather than by default: the Project Owner's own approval of the substantive decisions in this document is real and already given (the explicit "GO" authorization that preceded implementation). But `10_IMPLEMENTATION_CONTRACT.md`'s newly-introduced process — which this very document is being created to satisfy — describes independent external adversarial review (by ChatGPT/Gemini, outside this engagement) as a required step before a Decision Package may be treated as frozen, and before implementation begins. That specific step has not occurred for Phase 4: an External Audit Bundle was prepared and closed with "NOT CERTIFIED — AWAITING INDEPENDENT CHATGPT/GEMINI ADVERSARIAL REVIEW," and this Decision Package is being written after implementation, not before it. Declaring "FROZEN FOR IMPLEMENTATION" here would be an independent, self-declared claim of architectural approval that this document is explicitly instructed not to make. The correct status is therefore "REQUIRES EXTERNAL REVIEW," with the caveat — stated plainly, not hidden — that the review being awaited is procedural/independent-LLM review, not Project Owner sign-off, which already exists.

**v1.1.0 addendum:** the P1 correction in section 24 was itself produced in response to an external adversarial review ("GO WITH CONDITIONS"), which is evidence the review process is actively underway. This document's status remains "REQUIRES EXTERNAL REVIEW" — now specifically awaiting re-review of the v1.1.0 correction — and does not advance to "FROZEN FOR IMPLEMENTATION" merely because a correction was made; the correction itself has not yet been implemented, let alone re-reviewed.

---

## Governance note on sequencing

This document was authored after implementation rather than before it, which inverts the sequence `10_IMPLEMENTATION_CONTRACT.md`'s newly-added process describes ("Decision Package → external approval → implementation"). This inversion is disclosed here rather than smoothed over, consistent with the instruction not to silently reinterpret or paper over a process discrepancy. It is not resolved by this document; it is a fact recorded for the external reviewer.
