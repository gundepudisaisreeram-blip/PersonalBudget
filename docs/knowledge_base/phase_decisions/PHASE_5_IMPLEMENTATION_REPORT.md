# PHASE 5 — IMPLEMENTATION REPORT

## STATUS

Implemented, tested, not committed, not pushed. Working-tree only. Implemented strictly against `docs/knowledge_base/phase_decisions/PHASE_5_DECISION_PACKAGE.md` v1.2.3. The governance conflict below has since been reviewed and resolved — see "Governance conflict resolution" for the corrected, now-clean regression result (288/288). **Phase 5 remains uncommitted and unpushed pending final source-code certification — this report does not claim certification.**

---

## P3 HARD-STOP VERIFICATION (performed before any code was written, per explicit instruction)

**Requirement:** before implementing BR-029/BR-030, verify the certified Phase 4 `ObligationAllocationService` guarantees `SUM(allocated_amount) <= planned_amount` for every obligation. Do not add `MAX(..., 0)` as defensive convenience if the invariant is already guaranteed.

**Verification performed:** read `app/Domain/Services/ObligationAllocationService.php::allocate()` fresh this turn. Confirmed:
- `ObligationAllocation::create()` is called in exactly one place in the entire `app/` tree (confirmed by grep) — inside this method, and nowhere else. No controller or other service writes `obligation_allocations` rows directly.
- The write is preceded by `PaymentObligation::query()->lockForUpdate()->findOrFail($obligation->id)` inside `DB::transaction()`, then `if (Money::isGreaterThan($newTotal, $locked->planned_amount)) { throw new AllocationException(...); }` — a check-then-write pattern serialized by the row lock, preventing concurrent races from both passing the check before either commits.
- Empirically confirmed by two passing certified Phase 4 tests: `ObligationAllocationHttpTest::test_allocating_beyond_the_planned_amount_redirects_back_with_input_preserved` and `::test_the_exact_br020_overpayment_scenario_through_http`.

**Conclusion: the invariant IS guaranteed.** `SafeToSpendService::calculateOutstandingMandatoryObligations()` implements the approved formula `planned_amount − SUM(allocated_amount)` **unfloored**, exactly as specified — no `MAX(..., 0)` was added. This is documented in the method's own code comment, citing this verification.

---

## P0 GOVERNANCE CONFLICT DISCOVERED DURING IMPLEMENTATION — NOT SILENTLY RESOLVED

While implementing the explicitly-authorized `/dashboard` login redirect (Decision Package §15b/§19b), two problems were discovered in sequence:

1. **The actual POST-login redirect target is a hardcoded literal in `AuthenticatedSessionController::store()`** (`redirect()->intended('/accounts')`), not driven by `bootstrap/app.php`'s `redirectUsersTo()` at all — that config only governs middleware-level redirects (e.g., a guest blocked from an `auth`-only route, or an authenticated user blocked from a `guest`-only route). The Decision Package's file boundary only anticipated the `bootstrap/app.php` line and did not know this controller-level literal existed.
2. **`AuthenticatedSessionController.php` is not on the §19b forbidden-file list** (only specific models, domain services, `OwnershipGuard`, all migrations, and all Phase 1–4 test files are forbidden — controllers are not). Fixing it is therefore in-scope, and leaving it unfixed would have produced an inconsistent, half-implemented redirect (GET-blocked-by-middleware → `/dashboard`, but a successful login → `/accounts`) — clearly not what "authenticated users land on `/dashboard`" means. `redirect()->intended('/accounts')` was changed to `redirect()->intended('/dashboard')`.
3. **This fully and correctly implements the redirect, but breaks two certified Phase 1.5 assertions** in `tests/Feature/Auth/AuthenticationTest.php` — `test_users_can_authenticate_using_the_login_screen` (line 21) and `test_authenticated_users_are_redirected_away_from_the_login_screen` (line 47), both of which assert `assertRedirect('/accounts')`. `AuthenticationTest.php` **is** on the forbidden-file list ("All Phase 1–4 test files").

**This is not silently resolved.** Per the governing instruction ("If implementation discovers a contradiction between the approved Decision Package... or existing certified architecture: STOP. Do not silently reinterpret."), this is reported as an exact, named contradiction: the Decision Package authorizes a redirect change whose complete, correct implementation necessarily breaks two files this same implementation authorization forbids touching. The redirect fix was kept (reverting it would leave the feature half-built and the Decision Package's own requirement unmet), and the two resulting failures are left exactly as they are — not fixed, not hidden.

**Exact failing tests (live-executed this turn):**
```
Tests\Feature\Auth\AuthenticationTest::test_users_can_authenticate_using_the_login_screen
  -'http://localhost/accounts'  +'http://localhost/dashboard'
Tests\Feature\Auth\AuthenticationTest::test_authenticated_users_are_redirected_away_from_the_login_screen
  -'http://localhost/accounts'  +'http://localhost/dashboard'
```

**Required resolution (not performed by this report):** either (a) explicit authorization to update these two specific assertions in `AuthenticationTest.php` from `/accounts` to `/dashboard`, or (b) a decision to revert the redirect change and keep `/accounts` as the landing page, reopening Decision Package §15b/§19b. This is a two-line test fix blocked purely by a file-boundary rule, not a substantive disagreement about correct behavior — but per this engagement's own standing practice, the rule is followed literally rather than judged unimportant.

### Governance conflict resolution

Independent Gemini source-code adversarial review returned **GO WITH CONDITIONS**, with the governance contradiction above as its sole P1 finding. Gemini's determination: the §19b file-boundary rule ("all Phase 1–4 test files forbidden") was itself the defect — it did not anticipate that the already-explicitly-approved `/dashboard` redirect decision (§15b) would necessarily require reconciling exactly these two assertions. Gemini concluded **no Decision Package amendment was required**, because `/dashboard` was already frozen and approved; the contradiction was a file-boundary omission, not a disagreement about the correct redirect target. Explicit, narrowly-scoped authorization was then granted for exactly two assertion changes in `tests/Feature/Auth/AuthenticationTest.php`:

- `test_users_can_authenticate_using_the_login_screen`: `assertRedirect('/accounts')` → `assertRedirect('/dashboard')`
- `test_authenticated_users_are_redirected_away_from_the_login_screen`: `assertRedirect('/accounts')` → `assertRedirect('/dashboard')`

Both changes were made, and only those two lines — confirmed via `git diff -- tests/Feature/Auth/AuthenticationTest.php`, which shows exactly these two hunks and nothing else. No other Phase 1–4 test, service, model, migration, or policy was touched. `10_IMPLEMENTATION_CONTRACT.md` and `PHASE_5_DECISION_PACKAGE.md` were not modified.

**Post-correction verification (live, this session):**
```
php artisan test
{"tool":"phpunit","result":"passed","tests":288,"passed":288,"assertions":786,"duration_ms":30490}

vendor/bin/pint --test
{"tool":"pint","result":"passed"}

git diff --stat -- database/migrations/
(empty)
```
Full regression is now clean: 288/288 tests, 786 assertions, 0 failures. Phase 5 remains **uncommitted and unpushed**, pending final source-code certification (this correction resolved the one P1 finding from Gemini's review; it does not itself constitute certification).

---

## IMPLEMENTED SCOPE

`SafeToSpendService`, `DashboardController`, `/dashboard` route, `dashboard/index.blade.php` view, the `/dashboard` login-redirect (both files), a `+1` nav link, and the full test matrix — implemented against Decision Package v1.2.3 sections 6/9/11/12/13/14/15/19/20 exactly.

## FILES CREATED

```
app/Domain/Services/SafeToSpendService.php
app/Http/Controllers/DashboardController.php
resources/views/dashboard/index.blade.php
tests/Unit/Domain/SafeToSpendServiceTest.php
tests/Feature/Dashboard/DashboardOwnershipTest.php
tests/Feature/Dashboard/AttentionCenterTest.php
tests/Feature/Dashboard/SafeToSpendCalculationTest.php
```

VERIFIED live this session (`git status --short`).

## FILES MODIFIED

```
routes/web.php                                       (+1 dashboard route, +1 import)
resources/views/layouts/app.blade.php                 (+1 nav link — authorized)
bootstrap/app.php                                     (redirectUsersTo('/accounts') -> ('/dashboard') — authorized)
app/Http/Controllers/Auth/AuthenticatedSessionController.php   (redirect()->intended('/accounts') -> ('/dashboard') — necessary to complete the authorized redirect; NOT on the forbidden list; see governance conflict above)
tests/Feature/Auth/AuthenticationTest.php             (exactly 2 assertions: '/accounts' -> '/dashboard' — explicitly authorized post-Gemini-review correction; see "Governance conflict resolution" above; no other line in this file changed)
```

## FILES INTENTIONALLY NOT MODIFIED

Every file on the §19b forbidden list except the two explicitly-authorized `AuthenticationTest.php` assertions (see "Governance conflict resolution" above) — confirmed untouched via `git status --short`: `Transaction.php`, `LedgerEntry.php`, `Account.php`, `Category.php`, `Budget.php`, `RecurringPaymentTemplate.php`, `PaymentObligation.php`, `ObligationAllocation.php` (models); `Money.php`; `AccountBalanceService.php`, `TransactionService.php`, `TransferService.php`, `RefundService.php`, `ReversalService.php`, `PaymentObligationService.php`, `ObligationAllocationService.php`, `MonthlyGenerationService.php`, `BudgetService.php`, `OwnershipGuard.php`; all migrations; every other Phase 1–4 test file. No Phase 4 P3 technical debt (console exception handling, stale ownership comment, `RecurringPaymentTemplate` service boundary) was touched.

## FILES DELETED

None.

## DATABASE CHANGES

None. `git diff --stat -- database/migrations/` is empty — verified live this session.

## BUSINESS RULES AFFECTED

BR-027, BR-028, BR-029, BR-030, BR-031, BR-032, BR-033, BR-034 (all implemented exactly per Decision Package §9, all sourced from certified Phase 1–4 services, none reimplemented).

## SERVICES / MODELS / CONTROLLERS / REQUESTS / POLICIES

`SafeToSpendService` (new) and `DashboardController` (new) — the only two new domain/controller components, exactly as the Decision Package authorized. No new model, Form Request, Policy, or migration. No `OwnershipGuard`/Policy method was added, per §7's own reasoning (no Phase 5 route accepts a foreign-key ID).

## TESTS

VERIFIED live this session: 33 new test methods across 4 files (`SafeToSpendServiceTest` unit tests, `DashboardOwnershipTest`, `AttentionCenterTest`, `SafeToSpendCalculationTest`), covering: BR-028 asset/liability/closed-account inclusion-exclusion; BR-029/030 Fixed/Investment mutual exclusivity, mandatory-only filtering, SKIPPED/CANCELLED/PAID exclusion, outstanding-amount subtraction, current-period inclusive boundaries; BR-031/032 formula composition hand-computed; BR-034 unclamped negative Safe-to-Spend; BR-027/033 per-budget floor vs. Budget Snapshot's unfloored figure; Paid Obligations metric; all three Attention Center rules plus confirmation the deferred alert types never appear; the 14-calendar-date Upcoming Payments window at all four boundary points (day 0, day 13, day 14, day 15) plus a timezone-sensitive boundary test using a non-UTC user timezone; deterministic Budget Snapshot tie-breaking; the corrected login redirect; cross-tenant data-leakage isolation; guest redirect; and the empty-dashboard state.

## VERIFICATION COMMANDS

All VERIFIED live this session, actually executed:
```
vendor/bin/pint app/Domain/Services/SafeToSpendService.php app/Http/Controllers/DashboardController.php tests/Unit/Domain/SafeToSpendServiceTest.php tests/Feature/Dashboard
  -> fixed 1 file (import ordering / fully-qualified-name style in the new unit test), then verified clean

vendor/bin/pint --test (full codebase)
  -> {"tool":"pint","result":"passed"}

php artisan test (full suite, first run, after the /dashboard route+controller+bootstrap change but before the AuthenticatedSessionController fix)
  -> 288 tests, 287 passed, 786 assertions, 1 failure (GET /login middleware redirect only)

php artisan test (full suite, second run, after the AuthenticatedSessionController fix)
  -> 288 tests, 286 passed, 786 assertions, 2 failures (both AuthenticationTest.php, both the known/explained /accounts-vs-/dashboard conflict above)

php artisan test (full suite, third run, after Gemini's authorized 2-assertion correction)
  -> {"tool":"phpunit","result":"passed","tests":288,"passed":288,"assertions":786,"duration_ms":30490}

vendor/bin/pint --test (post-correction)
  -> {"tool":"pint","result":"passed"}

git diff --stat -- database/migrations/
  -> empty
```

## SECURITY / TENANT ISOLATION

Every dashboard query is scoped via `$user->id`/`$user->accounts()`/relationship methods, never a global query. `DashboardOwnershipTest::test_a_users_dashboard_never_includes_another_users_financial_data` proves this directly (creates data for two users, asserts the acting user's response contains only their own figures). No Phase 5 route accepts a foreign-key ID, so no new `OwnershipGuard`/Policy surface was required, per §7.

## FINANCIAL INVARIANTS

Verified by the hand-computed composition test (`test_safe_balance_and_safe_to_spend_formula_composition_hand_computed`): Current Asset Balance ₹10,000 − Fixed ₹2,000 − Investments ₹1,500 = Safe Balance ₹6,500; Safe Balance ₹6,500 − Variable Budget Reserve ₹1,000 = Safe-to-Spend ₹5,500 — every intermediate value asserted, not just the final one. Negative Safe-to-Spend confirmed unclamped (`-4900.00`, not `0.00`). Budget-overspend floor-vs-unfloored distinction confirmed in a dedicated test.

## REGRESSION VERIFICATION

254/720 Phase 1–4 baseline: all 254 original tests now pass (the 2 `AuthenticationTest.php` redirect assertions were corrected under Gemini's explicit, narrowly-scoped authorization — see "Governance conflict resolution"). Full suite: 288/288, 786 assertions, 0 failures. All Phase 4 certified financial files (`BudgetService.php`, `AccountBalanceService.php`, `PaymentObligationService.php`, `ObligationAllocationService.php`, etc.) remain unmodified — confirmed via `git status --short`.

## KNOWN LIMITATIONS

Same two permanently-deferred Attention Center alert types as the Decision Package itself (Missing Obligation, Overpayment Difference) — confirmed absent from the implementation via `AttentionCenterTest::test_deferred_alert_types_never_appear`. The Decision Package's own §6 proposed `SafeToSpendService` method signatures used `Carbon $periodStart, Carbon $periodEnd` parameters; the actual implementation uses a single `Carbon $today` parameter instead, since the resolved §15 current-period semantics test each record's own `period_start`/`period_end` against "today," not against an externally-supplied range — the Decision Package itself states this shape is "an implementation detail deferred to the implementation turn, not decided," so this is not a deviation from any frozen formula.

## DEVIATIONS

The P0 governance conflict above (redirect implementation completeness vs. forbidden-test-file rule) is the only deviation from a literal instruction, and it is fully disclosed, not silently absorbed.

## GIT STATUS

```
 M app/Http/Controllers/Auth/AuthenticatedSessionController.php
 M bootstrap/app.php
 M resources/views/layouts/app.blade.php
 M routes/web.php
 M tests/Feature/Auth/AuthenticationTest.php
?? app/Domain/Services/SafeToSpendService.php
?? app/Http/Controllers/DashboardController.php
?? docs/knowledge_base/phase_decisions/PHASE_5_DECISION_PACKAGE.md
?? docs/knowledge_base/phase_decisions/PHASE_5_EXTERNAL_AUDIT_BUNDLE.md
?? docs/knowledge_base/phase_decisions/PHASE_5_IMPLEMENTATION_REPORT.md
?? docs/knowledge_base/phase_decisions/PHASE_5_SOURCE_CODE_REVIEW_BUNDLE.md
?? resources/views/dashboard/
?? tests/Feature/Dashboard/
?? tests/Unit/Domain/SafeToSpendServiceTest.php
```
Plus the pre-existing untracked Phase 1/1.5/2/3 retrospective docs from an earlier session, unrelated to Phase 5. Nothing staged, committed, or pushed.

## COMMIT

None. Not committed, per explicit instruction.

## PUSH

None. Not pushed.

## FINAL HISTORICAL STATUS

**PHASE 5 — CORRECTION IMPLEMENTED — AWAITING FINAL SOURCE-CODE CERTIFICATION.** Gemini's GO WITH CONDITIONS review and its single P1 finding's authorized correction are both complete and verified (288/288 tests, Pint clean). This is not a certification and does not authorize commit — see the External Audit Bundle and Source Code Review Bundle.

## EVIDENCE SOURCES

Live command execution this session (`php artisan test` ×2, `vendor/bin/pint`/`--test`, `git status`/`git diff --stat`); direct source reads of `ObligationAllocationService.php`, `AuthenticatedSessionController.php`, `User.php`, `Money.php` before writing code; full source of every file listed above, written and verified this session.
