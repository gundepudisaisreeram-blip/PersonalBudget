# PHASE 4 — IMPLEMENTATION REPORT

## RETROSPECTIVE DOCUMENTATION

This report was written after Phase 4 implementation was already complete, formalizing findings that were verified live in an earlier conversation turn within this same engagement (git diffs, a live `php artisan test` run, a live `vendor/bin/pint --test` run, a live `php artisan migrate:status` run, and a live `information_schema` schema query) into a permanent repository file for the first time. Where this report cites "VERIFIED live, this session," that means an actual command was executed and its output captured earlier in this conversation, not merely asserted.

---

## v1.1.0 CORRECTION — SERVICE-LAYER TENANT ISOLATION (P1)

This section was added after Gemini's re-review returned **GO — DECISION PACKAGE APPROVED** for `PHASE_4_DECISION_PACKAGE.md` v1.1.0, authorizing implementation of the approved correction. Everything below this section describes the original v1.0.0 implementation; this section documents what changed on top of it.

**v1.0.0 implementation status:** unchanged — implemented, tested, not committed (see STATUS below). Nothing in v1.0.0's scope was reopened.

**v1.1.0 approved correction implemented:** `App\Domain\Services\PaymentObligationService::createOneTime()` now asserts `OwnershipGuard::assertAccountOwnership()` on `planned_account_id` before any write, whenever a non-null `planned_account_id` is supplied. The pre-existing `category_id` ownership assertion is untouched. This makes the domain-service layer the authoritative check, per Decision Package v1.1.0 section 13.1's Defense-in-Depth Contract — the HTTP-layer check in `PaymentObligationController` was left in place (not removed) and now functions as the early/UX layer the contract calls for, not the sole protection.

**Files modified for the correction:**
```
app/Domain/Services/PaymentObligationService.php   (+5: import Account, ownership assertion before write)
```
VERIFIED live this session (`git diff --stat`).

**Tests added for the correction** (`tests/Feature/Obligations/ObligationOwnershipTest.php`, +2 methods):
- `test_a_user_cannot_directly_invoke_create_one_time_with_another_users_planned_account_id` — instantiates `PaymentObligationService` directly (bypassing the controller/Form Request entirely), calls `createOneTime()` with an attacker user and the owner's `Account` id, asserts `OwnershipViolationException` is thrown and `PaymentObligation::count()` is `0` afterward.
- `test_create_one_time_succeeds_with_the_same_users_own_planned_account_id` — same direct-service pattern, legitimate same-user account id, asserts the obligation is created successfully with the correct `planned_account_id` — proving the check is precise (fails closed on a mismatch, succeeds on a match), not merely fail-closed on everything.

The pre-existing HTTP-layer tests (`test_a_spoofed_planned_account_id_on_one_time_creation_is_rejected_with_a_generic_403` and every other test in this file) were **not modified or removed** — confirmed by diff: the only change to this file is the 2 new methods plus their required `use` imports.

**Test results (all VERIFIED live this session, actually executed):**
```
php artisan test tests/Feature/Obligations
{"tool":"phpunit","result":"passed","tests":64,"passed":64,"assertions":144,"duration_ms":18221}

php artisan test
{"tool":"phpunit","result":"passed","tests":254,"passed":254,"assertions":720,"duration_ms":27637}

vendor/bin/pint --test
{"tool":"pint","result":"passed"}
```
254 tests / 720 assertions is 252/716 (the v1.0.0 baseline) plus exactly the 2 new tests and their 4 assertions — 0 failures, 0 regressions.

**Files intentionally NOT modified for this correction** (per explicit instruction):
- No migration file (`git diff --stat -- database/migrations/` is empty).
- `app/Models/Transaction.php`, `app/Models/LedgerEntry.php`, `app/Domain/Money.php` — untouched.
- `app/Domain/Services/TransactionService.php`, `TransferService.php`, `RefundService.php`, `ReversalService.php`, `AccountBalanceService.php`, `ObligationAllocationService.php` — untouched.
- `app/Http/Controllers/PaymentObligationController.php` — untouched; its existing HTTP-layer check was left in place deliberately, not removed, per the Decision Package's defense-in-depth contract.
- No RecurringPaymentTemplate service was created — see the next paragraph.

**Unresolved RecurringPaymentTemplate service-boundary decision — implementation STOPPED here, not silently resolved:** Decision Package v1.1.0 section 23, Open Architectural Item 1, explicitly leaves open whether/how a domain-service boundary should be introduced for `RecurringPaymentTemplate.category_id`/`default_account_id` ownership (no such service currently exists — `RecurringPaymentTemplateController` writes directly via Eloquent). Per this turn's explicit instruction ("Do NOT invent or create a new RecurringPaymentTemplate domain service... STOP and report that §23 requires an explicit architectural decision"), **this part of the P1 finding was not implemented.** `RecurringPaymentTemplateController`'s existing controller-layer `OwnershipGuard` checks remain exactly as they were in v1.0.0 — unchanged, still HTTP-layer-only, still not authoritative at a domain-service boundary. No domain-service-level test was written for this entity, per the same instruction ("Do not create domain-service tests until the open service-boundary architecture is explicitly resolved").

**Remaining implementation limitation:** Phase 4's tenant-isolation posture is now split — `PaymentObligation.planned_account_id` has the required two-layer defense-in-depth; `RecurringPaymentTemplate.category_id`/`default_account_id` still has HTTP-layer-only protection, pending the open architectural decision. This is a genuine, disclosed partial-completion state, not an oversight.

---

## STATUS

Implemented, tested, not yet committed. Working-tree only. See `docs/knowledge_base/phase_decisions/PHASE_4_DECISION_PACKAGE.md` section 25 for the full governance-status reasoning (`REQUIRES EXTERNAL REVIEW`, not `FROZEN FOR IMPLEMENTATION`). The v1.1.0 correction above is likewise implemented-but-not-committed and awaiting independent source-code adversarial review, not a fresh GO/NO-GO from this report.

## IMPLEMENTED SCOPE

Budgets, Recurring Payment Templates, Monthly Obligation Generation, Payment Obligations, Obligation Allocations — full HTTP/domain/test stack. VERIFIED FROM REPOSITORY (current `git status`, this session).

## DECISION PACKAGE VERSION

`docs/knowledge_base/phase_decisions/PHASE_4_DECISION_PACKAGE.md`, version **1.1.0** (its own Document Control section) — corrected from v1.0.0 per the Gemini P1 finding and re-reviewed with a **GO — DECISION PACKAGE APPROVED** verdict before this correction was implemented. This is the one phase in this engagement with a genuine, currently-existing, version-controlled Decision Package.

## FILES CREATED

VERIFIED FROM REPOSITORY (live `git status --porcelain`, this session):
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
```
plus, from the immediately preceding documentation turn: `docs/knowledge_base/phase_decisions/PHASE_4_DECISION_PACKAGE.md`.

## FILES MODIFIED

VERIFIED FROM REPOSITORY (live `git diff --stat`, this session):
```
app/Domain/Services/OwnershipGuard.php            (+8, one new method: assertBudgetOwnership)
app/Domain/Services/PaymentObligationService.php  (+5, v1.1.0 correction: planned_account_id ownership check)
bootstrap/app.php                                  (+25: withSchedule closure, +2 render() callbacks)
resources/views/layouts/app.blade.php              (+3: one nav link)
routes/web.php                                     (+30: 13 new named routes)
docs/knowledge_base/08_CHANGELOG.md                (+38: one new entry, from the immediately preceding documentation turn)
```
`docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md` also shows as modified in `git status`, but — flagged, not attributed here — no tool call in this engagement produced that change; see Known Limitations/Deviations below and `PHASE_4_EXTERNAL_AUDIT_BUNDLE.md` section P.

## FILES DELETED

None.

## DATABASE CHANGES

None. VERIFIED live this session: `php artisan migrate:status` shows 18 migrations, single batch, all previously `Ran`; no Phase-4-specific migration file exists.

## BUSINESS RULES AFFECTED

BR-013 through BR-016 (Transfer/Refund/Reversal/Adjustment, reused unmodified), BR-018 (recurring generation idempotency), BR-025 (budget utilization), BR-059 (transaction period placement) — all per `01_BUSINESS_RULES.md`, cited and cross-checked against frozen text during the immediately preceding Decision Package creation turn.

## SERVICES / MODELS / CONTROLLERS / REQUESTS / POLICIES

Full detail in `PHASE_4_DECISION_PACKAGE.md` sections 4/8/13 and in the file lists above. Summary: 2 new services (`BudgetService`, `MonthlyGenerationService`), 1 extended service (`OwnershipGuard`, +1 method), 4 new controllers, 6 new Form Requests, 4 new Policies, 1 new console command, 0 new models (all four entity models — `Budget`, `RecurringPaymentTemplate`, `PaymentObligation`, `ObligationAllocation` — pre-existed since Phase 1).

## TESTS

VERIFIED live this session (`php artisan test`, executed with the project's correct PHP 8.3.16 binary after discovering the shell's default PHP was a mismatched 8.2.12): v1.0.0 baseline `{"tool":"phpunit","result":"passed","tests":252,"passed":252,"assertions":716,"duration_ms":27071}`; after the v1.1.0 correction, `{"tool":"phpunit","result":"passed","tests":254,"passed":254,"assertions":720,"duration_ms":27637}`. Of these, 10 test files / approximately 83 methods are new to Phase 4 (the remainder are the full Phase 1–3 regression suite, all still passing).

## VERIFICATION COMMANDS

All VERIFIED live this session, actually executed (not assumed):
- `php artisan migrate:status` — 18/18 migrations `Ran`, single batch.
- `php artisan test` — 252/716, 0 failures.
- `vendor/bin/pint --test` — `{"tool":"pint","result":"passed"}`.
- Live `information_schema` queries against `budgets`, `recurring_payment_templates`, `payment_obligations` — columns, indexes, foreign keys, and CHECK constraints (`chk_budgets_amount_non_negative`, `chk_payment_obligations_identity`) all confirmed present and correctly worded.
- `git status` / `git diff --stat` / targeted `git diff` on `10_IMPLEMENTATION_CONTRACT.md` and `layouts/app.blade.php`.

No destructive command (`migrate:fresh`, `migrate:rollback`) was run in the sessions that produced this report or the preceding audit-bundle turn — explicitly avoided per those turns' own instructions. An earlier implementation turn (per this engagement's own prior summary, not independently re-verified in this documentation pass) reported having run a full `migrate:fresh` → `db:seed` ×2 → `migrate:rollback` → `migrate` cycle; that specific claim is RECONSTRUCTED from conversation history rather than re-verified against a saved artifact in this retrospective pass, and is disclosed as such rather than presented as independently confirmed here.

## SECURITY / TENANT ISOLATION

Full FK-by-FK trace in `PHASE_4_DECISION_PACKAGE.md` section 13 (as corrected in v1.1.0). Two ownership-hardening fixes were originally made during v1.0.0 implementation beyond the entities named in the original decision list (recurring-template controller `category_id`/`default_account_id` checks; one-time-obligation controller `planned_account_id` check) — the A-vs-B scope-classification question from v1.0.0 remains open (Decision Package section 23, item 2). Of the two, `planned_account_id` now additionally has an authoritative domain-service-layer check as of this v1.1.0 correction (see the correction section above); the recurring-template checks remain HTTP-layer-only, pending the still-open service-boundary decision (Decision Package section 23, item 1).

## FINANCIAL INVARIANTS

Budget Utilization formula and the frozen Reversal-of-Reversal worked example — VERIFIED live this session by direct inspection of `BudgetService::calculateUtilization()`'s actual code (a single uniform `SUM(OUTFLOW) − SUM(INFLOW)` via `Money::sub()`, no `transaction_type` branching) and by the passing `test_the_frozen_reversal_of_reversal_worked_example` test.

## REGRESSION VERIFICATION

VERIFIED live this session: the 252-test run includes every Phase 1–3 test file unchanged; 0 failures across the full suite confirms no regression.

## KNOWN LIMITATIONS

`recurring_payment_templates.due_rule` has no database-level CHECK constraint — format enforcement is HTTP-validation-only (VERIFIED live this session via `information_schema.CHECK_CONSTRAINTS`, which returned no rows for this table). `RecurringPaymentTemplate.category_id`/`default_account_id` ownership remains HTTP-layer-only pending the open service-boundary architectural decision (see the v1.1.0 correction section above).

## DEVIATIONS

`docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md` carries an uncommitted, unexplained ~100-line addition that no tool call in this engagement produced. This is disclosed here as a known, unresolved anomaly affecting the working tree during Phase 4 — not attributed to Phase 4 implementation work, and not silently omitted from this report's file-change accounting either. Full detail in `PHASE_4_EXTERNAL_AUDIT_BUNDLE.md` section P.

## GIT STATUS

VERIFIED live this session: branch `phase1-audit`, HEAD `1444376`, nothing committed or pushed; all Phase 4 work (and the current documentation files) remain in the working tree, matching the `git status --porcelain` output reproduced in "Files Created"/"Files Modified" above.

## COMMIT

None. Not committed, per every relevant turn's explicit instruction not to commit.

## PUSH

None. Not pushed.

## FINAL HISTORICAL STATUS

Not certified. `PHASE_4_DECISION_PACKAGE.md` section 25: `REQUIRES EXTERNAL REVIEW`. No GO/NO-GO is issued by this report.

## EVIDENCE SOURCES

Live command execution this session (`php artisan test`, `vendor/bin/pint --test`, `php artisan migrate:status`, `information_schema` queries, `git status`/`git diff`); direct source reads of every Phase 4 file this session; `docs/knowledge_base/phase_decisions/PHASE_4_DECISION_PACKAGE.md` v1.1.0 (existing document, read/cited). For the v1.1.0 correction specifically: fresh reads of `PaymentObligationService.php`/`OwnershipGuard.php`/`ObligationOwnershipTest.php` before editing; live `php artisan test tests/Feature/Obligations`, `php artisan test`, `vendor/bin/pint --test`, and `git status`/`git diff --stat` after editing — all executed in this turn.

## RETROSPECTIVE DOCUMENTATION DISCLAIMER

This document was reconstructed retrospectively after implementation. Unlike the Phase 1/2/3 reports in this same batch, its underlying evidence was gathered live within this same overall engagement (not reconstructed from cold Git/file archaeology), but it is still being written to a permanent file for the first time here, after the fact, rather than during implementation itself.
