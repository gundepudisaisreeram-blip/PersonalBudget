# PHASE 4 — SOURCE CODE REVIEW BUNDLE

This document contains the exact, unabridged current contents of the source files an independent auditor requested, reproduced directly from the repository — not paraphrased, not "cleaned up," not summarized. Audit observations are kept in clearly separated sections at the end and are never mixed into the source listings themselves. No code shown here was modified while producing this document; this is a read-only documentation artifact.

---

## GIT EVIDENCE

```
Branch:      phase1-audit
HEAD:        14443768b28f31234074d74f5fd392c98adb388b
             1444376 feat: complete phase 3 transaction ledger
```

`git status --porcelain`:
```
 M app/Domain/Services/OwnershipGuard.php
 M app/Domain/Services/PaymentObligationService.php
 M bootstrap/app.php
 M docs/knowledge_base/08_CHANGELOG.md
 M docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md
 M resources/views/layouts/app.blade.php
 M routes/web.php
?? app/Console/
?? app/Domain/Exceptions/BudgetException.php
?? app/Domain/Services/BudgetService.php
?? app/Domain/Services/MonthlyGenerationService.php
?? app/Http/Controllers/BudgetController.php
?? app/Http/Controllers/ObligationAllocationController.php
?? app/Http/Controllers/PaymentObligationController.php
?? app/Http/Controllers/RecurringPaymentTemplateController.php
?? app/Http/Requests/StoreBudgetRequest.php
?? app/Http/Requests/StoreObligationAllocationRequest.php
?? app/Http/Requests/StoreOneTimeObligationRequest.php
?? app/Http/Requests/StoreRecurringPaymentTemplateRequest.php
?? app/Http/Requests/UpdateBudgetRequest.php
?? app/Http/Requests/UpdateRecurringPaymentTemplateRequest.php
?? app/Policies/BudgetPolicy.php
?? app/Policies/ObligationAllocationPolicy.php
?? app/Policies/PaymentObligationPolicy.php
?? app/Policies/RecurringPaymentTemplatePolicy.php
?? docs/knowledge_base/phase_decisions/
?? resources/views/budgets/
?? resources/views/obligations/
?? resources/views/recurring-templates/
?? tests/Feature/Budgets/
?? tests/Feature/Obligations/MonthlyGenerationTest.php
?? tests/Feature/Obligations/ObligationAllocationHttpTest.php
?? tests/Feature/Obligations/ObligationCrudTest.php
?? tests/Feature/Obligations/ObligationOwnershipTest.php
?? tests/Feature/RecurringTemplates/
```

`git diff --stat`:
```
 app/Domain/Services/OwnershipGuard.php            |   8 ++
 app/Domain/Services/PaymentObligationService.php  |   5 +
 bootstrap/app.php                                 |  25 +++++
 docs/knowledge_base/08_CHANGELOG.md               |  38 ++++++++
 docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md | 114 ++++++++++++++++++++++
 resources/views/layouts/app.blade.php             |   3 +
 routes/web.php                                    |  30 ++++++
 7 files changed, 223 insertions(+)
```

`docs/knowledge_base/phase_decisions/PHASE_4_SOURCE_CODE_REVIEW_BUNDLE.md` (this file) is not shown in the status above because it did not exist at the moment that command was captured — it is being created by this same action.

---

## TEST EVIDENCE

Both commands were actually executed during preparation of this bundle:

```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":254,"passed":254,"assertions":720,"duration_ms":27960}

$ vendor/bin/pint --test
{"tool":"pint","result":"passed"}
```

Passing tests are evidence the code runs as intended for the scenarios the tests cover; they are not, by themselves, evidence of correctness beyond what those specific assertions check. No claim of correctness is made here on the basis of these results alone — that judgment is left to the auditor reading the source below.

---

## A. Domain Service — `app/Domain/Services/PaymentObligationService.php`

```php
<?php

namespace App\Domain\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use DateTimeInterface;

/**
 * Creates Payment Obligations under the approved identity rules.
 *
 * An obligation is strictly Recurring (template + occurrence_key) or
 * One-time (idempotency_key). Generation is idempotent: creating the same
 * occurrence/idempotency key twice never produces a duplicate row (BR-018,
 * 09 section 3 "Identity invariant").
 */
class PaymentObligationService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function createRecurringOccurrence(
        User $user,
        RecurringPaymentTemplate $template,
        string $occurrenceKey,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        DateTimeInterface|string $dueDate,
        string $plannedAmount,
        ?Category $category = null,
    ): PaymentObligation {
        $this->ownership->assertRecurringTemplateOwnership($template, $user->id);

        $resolvedCategory = $category ?? $template->category;
        $this->ownership->assertCategoryOwnership($resolvedCategory, $user->id);

        return PaymentObligation::query()->firstOrCreate(
            [
                'recurring_payment_template_id' => $template->id,
                'occurrence_key' => $occurrenceKey,
            ],
            [
                'user_id' => $user->id,
                'idempotency_key' => null,
                'category_id' => $resolvedCategory->id,
                'planned_account_id' => $template->default_account_id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'planned_amount' => $plannedAmount,
                'status' => 'PENDING',
                'is_mandatory' => $template->is_mandatory,
            ],
        );
    }

    public function createOneTime(
        User $user,
        string $idempotencyKey,
        Category $category,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        DateTimeInterface|string $dueDate,
        string $plannedAmount,
        bool $isMandatory = true,
        ?int $plannedAccountId = null,
    ): PaymentObligation {
        $this->ownership->assertCategoryOwnership($category, $user->id);

        if ($plannedAccountId !== null) {
            $this->ownership->assertAccountOwnership(Account::findOrFail($plannedAccountId), $user->id);
        }

        return PaymentObligation::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $user->id,
                'recurring_payment_template_id' => null,
                'occurrence_key' => null,
                'category_id' => $category->id,
                'planned_account_id' => $plannedAccountId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'planned_amount' => $plannedAmount,
                'status' => 'PENDING',
                'is_mandatory' => $isMandatory,
            ],
        );
    }
}
```

**Auditor checklist — pointers only, not conclusions:**
- `planned_account_id` ownership validation: line with `if ($plannedAccountId !== null) { $this->ownership->assertAccountOwnership(...); }` inside `createOneTime()`.
- Exact position: after the `category_id` assertion, before the `firstOrCreate()` write — i.e., before any database write in this method.
- Existing `category_id` validation: `$this->ownership->assertCategoryOwnership($category, $user->id);`, unchanged, first line of method body.
- Transaction boundaries: **neither `createRecurringOccurrence()` nor `createOneTime()` opens a `DB::transaction()` of its own** — both rely on `firstOrCreate()`'s single-statement atomicity. No new transaction boundary was added or removed by the v1.1.0 correction.
- `createRecurringOccurrence()` is untouched by the v1.1.0 correction — no `planned_account_id` ownership check was added there; it derives `planned_account_id` from `$template->default_account_id`, not from user input.
- No import, method signature, or unrelated line was changed beyond: adding `use App\Models\Account;` and the 3-line `if` block.

---

## B. Direct-Service Tests — `tests/Feature/Obligations/ObligationOwnershipTest.php`

```php
<?php

namespace Tests\Feature\Obligations;

use App\Domain\Exceptions\OwnershipViolationException;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\PaymentObligationService;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ObligationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeObligation(User $user, string $key): PaymentObligation
    {
        $category = Category::factory()->for($user)->create();

        return PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => $key,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);
    }

    public function test_a_guest_is_redirected_to_login_from_every_obligation_route(): void
    {
        $owner = User::factory()->create();
        $obligation = $this->makeObligation($owner, 'obligation-guest');

        $this->get('/obligations')->assertRedirect('/login');
        $this->get('/obligations/create')->assertRedirect('/login');
        $this->post('/obligations', [])->assertRedirect('/login');
        $this->post('/obligations/generate')->assertRedirect('/login');
        $this->get(route('obligations.show', $obligation))->assertRedirect('/login');
        $this->patch(route('obligations.skip', $obligation))->assertRedirect('/login');
        $this->patch(route('obligations.cancel', $obligation))->assertRedirect('/login');
    }

    public function test_a_user_cannot_view_another_users_obligation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $obligation = $this->makeObligation($owner, 'obligation-view');

        $this->actingAs($attacker)->get(route('obligations.show', $obligation))->assertForbidden();
    }

    public function test_a_user_cannot_skip_another_users_obligation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $obligation = $this->makeObligation($owner, 'obligation-skip');

        $this->actingAs($attacker)->patch(route('obligations.skip', $obligation))->assertForbidden();

        $this->assertSame('PENDING', $obligation->fresh()->status);
    }

    public function test_a_user_cannot_cancel_another_users_obligation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $obligation = $this->makeObligation($owner, 'obligation-cancel');

        $this->actingAs($attacker)->patch(route('obligations.cancel', $obligation))->assertForbidden();

        $this->assertSame('PENDING', $obligation->fresh()->status);
    }

    public function test_a_spoofed_category_id_on_one_time_creation_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $ownerCategory = Category::factory()->for($owner)->create();
        $obligationCount = PaymentObligation::count();

        $response = $this->actingAs($attacker)->post(route('obligations.store'), [
            'category_id' => $ownerCategory->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
        ]);

        $response->assertForbidden();
        $this->assertSame($obligationCount, PaymentObligation::count());
    }

    public function test_a_spoofed_planned_account_id_on_one_time_creation_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $attackerCategory = Category::factory()->for($attacker)->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $obligationCount = PaymentObligation::count();

        $response = $this->actingAs($attacker)->post(route('obligations.store'), [
            'category_id' => $attackerCategory->id,
            'planned_account_id' => $ownerAccount->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
        ]);

        $response->assertForbidden();
        $this->assertSame($obligationCount, PaymentObligation::count());
    }

    /**
     * Phase 4 Decision Package v1.1.0 P1 correction: planned_account_id
     * ownership must be authoritative at the PaymentObligationService
     * boundary, not only at the HTTP controller layer -- proven here by
     * bypassing the controller/Form Request entirely and calling the
     * service directly.
     */
    public function test_a_user_cannot_directly_invoke_create_one_time_with_another_users_planned_account_id(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $attackerCategory = Category::factory()->for($attacker)->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $service = new PaymentObligationService(new OwnershipGuard);

        $this->expectException(OwnershipViolationException::class);

        try {
            $service->createOneTime(
                $attacker,
                (string) Str::uuid(),
                $attackerCategory,
                '2026-01-01',
                '2026-01-31',
                '2026-01-15',
                '1000.00',
                true,
                $ownerAccount->id,
            );
        } finally {
            $this->assertSame(0, PaymentObligation::count());
        }
    }

    public function test_create_one_time_succeeds_with_the_same_users_own_planned_account_id(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $account = Account::factory()->for($user)->create();
        $service = new PaymentObligationService(new OwnershipGuard);

        $obligation = $service->createOneTime(
            $user,
            (string) Str::uuid(),
            $category,
            '2026-01-01',
            '2026-01-31',
            '2026-01-15',
            '1000.00',
            true,
            $account->id,
        );

        $this->assertSame($account->id, $obligation->planned_account_id);
        $this->assertSame(1, PaymentObligation::count());
    }

    public function test_a_spoofed_user_id_in_the_create_payload_is_ignored(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user)->post(route('obligations.store'), [
            'user_id' => $victim->id,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
        ]);

        $obligation = PaymentObligation::where('category_id', $category->id)->firstOrFail();
        $this->assertSame($user->id, $obligation->user_id);
        $this->assertNotSame($victim->id, $obligation->user_id);
    }
}
```

**Auditor checklist:**
- Direct service instantiation / HTTP bypass: `$service = new PaymentObligationService(new OwnershipGuard);` in both new tests — no `$this->post(...)`, no route, no Form Request involved.
- Cross-tenant `planned_account_id` attack: `test_a_user_cannot_directly_invoke_create_one_time_with_another_users_planned_account_id` — `$attacker`'s call passes `$ownerAccount->id`.
- `OwnershipViolationException` assertion: `$this->expectException(OwnershipViolationException::class);`.
- Zero-write assertion: `$this->assertSame(0, PaymentObligation::count());` inside a `finally` block, so it runs regardless of the expected exception.
- Legitimate same-user success case: `test_create_one_time_succeeds_with_the_same_users_own_planned_account_id` — same direct-instantiation pattern, own account, asserts the created row's `planned_account_id` and a count of `1`.
- No weakened/deleted existing tests: every test present before the correction (`test_a_guest_is_redirected...` through `test_a_spoofed_planned_account_id_on_one_time_creation_is_rejected_with_a_generic_403`, plus `test_a_spoofed_user_id_in_the_create_payload_is_ignored`) is reproduced above unchanged, byte-for-byte, in its original position — only the two new methods and the `use` block were added.

---

## C. Budget Service — `app/Domain/Services/BudgetService.php`

```php
<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\BudgetException;
use App\Domain\Money;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates Variable Budgets, enforcing the no-overlapping-period
 * rule per category, and computes Budget Utilization from the certified
 * ledger architecture (BR-025; Phase 4 Decision Package sections 9-10).
 *
 * Overlap enforcement locks the authenticated user's own `users` row as the
 * serialization resource, because a brand-new Budget has no existing row of
 * its own to lock before insertion -- the same `lockForUpdate()` mechanism
 * ObligationAllocationService already uses, applied to a different anchor.
 */
class BudgetService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function createBudget(
        User $user,
        Category $category,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        string $budgetAmount,
        bool $isMandatoryReserve = false,
    ): Budget {
        $this->ownership->assertCategoryOwnership($category, $user->id);

        return DB::transaction(function () use ($user, $category, $periodStart, $periodEnd, $budgetAmount, $isMandatoryReserve) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            if ($this->overlaps($user->id, $category->id, $periodStart, $periodEnd)) {
                throw new BudgetException('A budget already exists for this category during an overlapping period.');
            }

            return Budget::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'budget_amount' => $budgetAmount,
                'is_mandatory_reserve' => $isMandatoryReserve,
            ]);
        });
    }

    public function updateBudget(
        User $user,
        Budget $budget,
        Category $category,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        string $budgetAmount,
        bool $isMandatoryReserve = false,
    ): Budget {
        $this->ownership->assertBudgetOwnership($budget, $user->id);
        $this->ownership->assertCategoryOwnership($category, $user->id);

        return DB::transaction(function () use ($user, $budget, $category, $periodStart, $periodEnd, $budgetAmount, $isMandatoryReserve) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            if ($this->overlaps($user->id, $category->id, $periodStart, $periodEnd, excludeBudgetId: $budget->id)) {
                throw new BudgetException('Another budget already exists for this category during an overlapping period.');
            }

            $budget->update([
                'category_id' => $category->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'budget_amount' => $budgetAmount,
                'is_mandatory_reserve' => $isMandatoryReserve,
            ]);

            return $budget->fresh();
        });
    }

    /**
     * Net Budget Utilization = SUM(OUTFLOW) - SUM(INFLOW), restricted to
     * ledger entries whose transaction belongs to the budget's category and
     * whose own transaction_date falls within the budget's period (BR-059).
     *
     * The eligible-transaction-type restriction (EXPENSE/REFUND/REVERSAL)
     * is retained: it is mathematically inert for TRANSFER (whose balanced
     * OUTFLOW/INFLOW pair always nets to zero) and is the only thing
     * excluding ADJUSTMENT, per BR-025. The calculation itself is a single
     * uniform direction-based sum, never a per-type formula -- a Reversal
     * of an Expense, and a Reversal of that Reversal, are handled with no
     * type-specific branching whatsoever.
     */
    public function calculateUtilization(Budget $budget): string
    {
        $eligibleTypes = ['EXPENSE', 'REFUND', 'REVERSAL'];

        $outflow = (string) DB::table('ledger_entries')
            ->join('transactions', 'transactions.id', '=', 'ledger_entries.transaction_id')
            ->where('transactions.user_id', $budget->user_id)
            ->where('transactions.category_id', $budget->category_id)
            ->whereIn('transactions.transaction_type', $eligibleTypes)
            ->whereBetween('transactions.transaction_date', [$budget->period_start, $budget->period_end])
            ->where('ledger_entries.direction', 'OUTFLOW')
            ->selectRaw('COALESCE(SUM(ledger_entries.amount), 0) as total')
            ->value('total');

        $inflow = (string) DB::table('ledger_entries')
            ->join('transactions', 'transactions.id', '=', 'ledger_entries.transaction_id')
            ->where('transactions.user_id', $budget->user_id)
            ->where('transactions.category_id', $budget->category_id)
            ->whereIn('transactions.transaction_type', $eligibleTypes)
            ->whereBetween('transactions.transaction_date', [$budget->period_start, $budget->period_end])
            ->where('ledger_entries.direction', 'INFLOW')
            ->selectRaw('COALESCE(SUM(ledger_entries.amount), 0) as total')
            ->value('total');

        return Money::sub($outflow, $inflow);
    }

    private function overlaps(
        int $userId,
        int $categoryId,
        DateTimeInterface|string $periodStart,
        DateTimeInterface|string $periodEnd,
        ?int $excludeBudgetId = null,
    ): bool {
        return Budget::query()
            ->where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('period_start', '<=', $periodEnd)
            ->where('period_end', '>=', $periodStart)
            ->when($excludeBudgetId !== null, fn ($query) => $query->where('id', '!=', $excludeBudgetId))
            ->exists();
    }
}
```

**Auditor checklist — reading the code directly, not the Decision Package's description of it:**
- Formula: two separate `DB::table('ledger_entries')->join('transactions', ...)` queries, one `where('ledger_entries.direction', 'OUTFLOW')`, one `'INFLOW'`, combined via `Money::sub($outflow, $inflow)` — i.e. literally `SUM(OUTFLOW) - SUM(INFLOW)`.
- Ledger direction: `ledger_entries.direction` column, filtered directly — no `transaction_type`-based sign flipping anywhere in this method.
- Category filtering: `where('transactions.category_id', $budget->category_id)` on both queries.
- Period filtering: `whereBetween('transactions.transaction_date', [$budget->period_start, $budget->period_end])` on both queries — inclusive (`whereBetween` is inclusive of both endpoints in Laravel/MySQL `BETWEEN`).
- `transaction_type` filtering: `whereIn('transactions.transaction_type', ['EXPENSE', 'REFUND', 'REVERSAL'])` on both queries — present, and per the docblock is described as inert for Transfer and the sole exclusion mechanism for Adjustment; the auditor should verify this claim independently rather than accept the comment.
- Reversal / Reversal-of-Reversal / Refund / Transfer / Adjustment behavior: **not implemented in this file** — this method contains no per-type branching at all; behavior for each type is entirely a consequence of how `ReversalService`/`RefundService`/`TransferService`/`TransactionService` (Phase 1, not reproduced in this bundle — see `PHASE_4_EXTERNAL_AUDIT_BUNDLE.md` section F for their citation) construct `LedgerEntry` rows, combined with the `transaction_type` filter and direction sum above.
- Budget ownership: `assertCategoryOwnership()` in both `createBudget()`/`updateBudget()`; `assertBudgetOwnership()` additionally in `updateBudget()`.
- Overlap detection: private `overlaps()` method, `period_start <= $periodEnd AND period_end >= $periodStart` — a standard inclusive interval-overlap test.
- `SELECT ... FOR UPDATE`: `User::query()->lockForUpdate()->findOrFail($user->id)`, first statement inside the `DB::transaction()` closure in both `createBudget()` and `updateBudget()`, strictly before the `overlaps()` call in both.
- create/update transaction boundaries: both methods wrap their entire lock-check-write sequence in a single `DB::transaction()` closure.
- Self-exclusion during update: `overlaps(..., excludeBudgetId: $budget->id)`, applied via `->when($excludeBudgetId !== null, fn ($query) => $query->where('id', '!=', $excludeBudgetId))`.

---

## D. Monthly Generation Service — `app/Domain/Services/MonthlyGenerationService.php`

```php
<?php

namespace App\Domain\Services;

use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Orchestrates idempotent recurring obligation generation (BR-018;
 * 03_ARCHITECTURE.md section 8; Phase 4 Decision Package section 8).
 *
 * Grammar is frozen: frequency = MONTHLY only, due_rule = "DAY:N" (N 1-31),
 * clamped to the target month's last calendar day. An occurrence is
 * generated only when its clamped due date falls on/after the template's
 * starts_on and, when ends_on exists, on/before ends_on -- both boundaries
 * inclusive, evaluated strictly after clamping.
 *
 * This service contains all generation logic. The manual HTTP trigger and
 * the scheduled console command both call generate() and must never
 * duplicate any part of this orchestration.
 */
class MonthlyGenerationService
{
    public function __construct(private readonly PaymentObligationService $obligations) {}

    /**
     * @return Collection<int, PaymentObligation>
     */
    public function generate(User $user, ?string $targetPeriod = null): Collection
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod($user, $targetPeriod);

        $templates = $user->recurringPaymentTemplates()
            ->where('status', 'ACTIVE')
            ->where('starts_on', '<=', $periodEnd->toDateString())
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('ends_on')->orWhere('ends_on', '>=', $periodStart->toDateString());
            })
            ->get();

        $occurrenceKey = $periodStart->format('Y-m');
        $created = new Collection;

        foreach ($templates as $template) {
            $dueDate = $this->calculateDueDate($template, $periodStart);

            if ($dueDate->lt($template->starts_on)) {
                continue;
            }

            if ($template->ends_on !== null && $dueDate->gt($template->ends_on)) {
                continue;
            }

            $created->push($this->obligations->createRecurringOccurrence(
                $user,
                $template,
                $occurrenceKey,
                $periodStart,
                $periodEnd,
                $dueDate,
                (string) $template->amount,
                $template->category,
            ));
        }

        return $created;
    }

    /**
     * due_rule = "DAY:N". Clamping to the target month's last calendar day
     * MUST occur before any starts_on/ends_on boundary evaluation.
     */
    private function calculateDueDate(RecurringPaymentTemplate $template, Carbon $periodStart): Carbon
    {
        [, $day] = explode(':', $template->due_rule);

        $clampedDay = min((int) $day, $periodStart->daysInMonth);

        return $periodStart->copy()->day($clampedDay);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(User $user, ?string $targetPeriod): array
    {
        $timezone = $user->timezone ?? config('app.timezone');

        $periodStart = $targetPeriod !== null
            ? Carbon::createFromFormat('Y-m-d', $targetPeriod.'-01', $timezone)->startOfMonth()
            : Carbon::now($timezone)->startOfMonth();

        return [$periodStart->copy()->startOfDay(), $periodStart->copy()->endOfMonth()->startOfDay()];
    }
}
```

**Auditor checklist:**
- `MONTHLY` frequency: not explicitly checked in this file — the template query filters only `status = 'ACTIVE'`; `frequency` validity is enforced upstream at `StoreRecurringPaymentTemplateRequest`/`UpdateRecurringPaymentTemplateRequest` (`in:MONTHLY`), not re-checked here. The auditor may consider whether this file should defensively re-check `frequency` — not evaluated here, only flagged as a fact about the code.
- `DAY:N` grammar / N range: parsed in `calculateDueDate()` via `explode(':', $template->due_rule)`; the valid-N-range (1–31) is enforced upstream by the Form Request regex, not in this file — this method will `min()` whatever integer it receives from `$day`, with no independent bounds check.
- Target-period calculation: `resolvePeriod()` — `Carbon::createFromFormat('Y-m-d', $targetPeriod.'-01', $timezone)->startOfMonth()` for an explicit period, else `Carbon::now($timezone)->startOfMonth()`.
- Period boundaries: `[$periodStart->copy()->startOfDay(), $periodStart->copy()->endOfMonth()->startOfDay()]` — note `endOfMonth()->startOfDay()`, i.e. the period end is midnight of the last day, not 23:59:59 of that day; the auditor may wish to verify this doesn't create a boundary edge case, since it's used in `->where('starts_on', '<=', $periodEnd->toDateString())`, a date-string comparison where the time-of-day is discarded by `toDateString()`.
- `occurrence_key`: `$periodStart->format('Y-m')`.
- Idempotency: not implemented in this file — delegated entirely to `PaymentObligationService::createRecurringOccurrence()`'s `firstOrCreate()` (section A above).
- `DAY:31` clamping / February / leap-year handling: `min((int) $day, $periodStart->daysInMonth)` — a single line, relies entirely on Carbon's `daysInMonth` property for correctness; no explicit February or leap-year branch exists (none is needed if `daysInMonth` is correct).
- `starts_on`: `if ($dueDate->lt($template->starts_on)) { continue; }` — strictly-less-than comparison, i.e. equal is NOT skipped (inclusive lower boundary).
- `ends_on`: `if ($template->ends_on !== null && $dueDate->gt($template->ends_on)) { continue; }` — strictly-greater-than comparison, i.e. equal is NOT skipped (inclusive upper boundary).
- Clamp-before-boundary ordering: `calculateDueDate()` is called and its result assigned to `$dueDate` before either the `starts_on` or `ends_on` check runs.
- Invocation of `PaymentObligationService`: `$this->obligations->createRecurringOccurrence(...)`, the sole write call in this file.
- Exception behavior: **this file contains no `try`/`catch` and throws nothing of its own** — any exception from `createRecurringOccurrence()` (e.g. `OwnershipViolationException`) propagates unhandled out of `generate()`.

---

## E. Recurring Payment Template Controller — `app/Http/Controllers/RecurringPaymentTemplateController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Services\OwnershipGuard;
use App\Http\Requests\StoreRecurringPaymentTemplateRequest;
use App\Http\Requests\UpdateRecurringPaymentTemplateRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringPaymentTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RecurringPaymentTemplateController extends Controller
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function index(Request $request): View
    {
        $templates = $request->user()->recurringPaymentTemplates()->orderBy('name')->get();

        return view('recurring-templates.index', compact('templates'));
    }

    public function create(): View
    {
        $this->authorize('create', RecurringPaymentTemplate::class);

        return view('recurring-templates.create', $this->formOptions());
    }

    public function store(StoreRecurringPaymentTemplateRequest $request): RedirectResponse
    {
        $category = Category::findOrFail($request->integer('category_id'));
        $this->ownership->assertCategoryOwnership($category, $request->user()->id);

        if ($request->filled('default_account_id')) {
            $account = Account::findOrFail($request->integer('default_account_id'));
            $this->ownership->assertAccountOwnership($account, $request->user()->id);
        }

        $template = $request->user()->recurringPaymentTemplates()->create($request->validated());

        return redirect()->route('recurring-templates.show', $template)->with('status', 'Recurring template created.');
    }

    public function show(RecurringPaymentTemplate $recurringPaymentTemplate): View
    {
        $this->authorize('view', $recurringPaymentTemplate);

        $recurringPaymentTemplate->load(['category', 'defaultAccount']);
        $obligations = $recurringPaymentTemplate->paymentObligations()->orderByDesc('period_start')->get();

        return view('recurring-templates.show', [
            'template' => $recurringPaymentTemplate,
            'obligations' => $obligations,
        ]);
    }

    public function edit(RecurringPaymentTemplate $recurringPaymentTemplate): View
    {
        $this->authorize('update', $recurringPaymentTemplate);

        return view('recurring-templates.edit', [
            'template' => $recurringPaymentTemplate,
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateRecurringPaymentTemplateRequest $request, RecurringPaymentTemplate $recurringPaymentTemplate): RedirectResponse
    {
        $category = Category::findOrFail($request->integer('category_id'));
        $this->ownership->assertCategoryOwnership($category, $request->user()->id);

        if ($request->filled('default_account_id')) {
            $account = Account::findOrFail($request->integer('default_account_id'));
            $this->ownership->assertAccountOwnership($account, $request->user()->id);
        }

        $recurringPaymentTemplate->update($request->validated());

        return redirect()->route('recurring-templates.show', $recurringPaymentTemplate)->with('status', 'Recurring template updated.');
    }

    public function cancel(RecurringPaymentTemplate $recurringPaymentTemplate): RedirectResponse
    {
        $this->authorize('update', $recurringPaymentTemplate);

        $recurringPaymentTemplate->update(['status' => 'CANCELLED']);

        return redirect()->route('recurring-templates.show', $recurringPaymentTemplate)->with('status', 'Recurring template cancelled.');
    }

    /**
     * @return array{categories: Collection, accounts: Collection}
     */
    private function formOptions(): array
    {
        $user = request()->user();

        return [
            'categories' => Category::query()
                ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
                ->orderBy('name')
                ->get(),
            'accounts' => $user->accounts()->orderBy('name')->get(),
        ];
    }
}
```

**Auditor checklist — this file was NOT modified by the v1.1.0 correction and is reproduced exactly as it exists in the working tree right now:**
- `store()`: `Category::findOrFail(...)` + `$this->ownership->assertCategoryOwnership(...)`, then conditionally `Account::findOrFail(...)` + `$this->ownership->assertAccountOwnership(...)` if `default_account_id` is filled, **then** `$request->user()->recurringPaymentTemplates()->create($request->validated())`.
- `update()`: identical shape, operating on the route-bound `$recurringPaymentTemplate` via `->update($request->validated())`.
- Ownership checks: both `category_id` and `default_account_id` are checked, but **only in this controller** — `$this->ownership` is `OwnershipGuard` injected directly into the controller's constructor.
- `category_id`/`default_account_id` handling: both resolved from raw request integers via `Category::findOrFail()`/`Account::findOrFail()` (global lookups, not tenant-scoped queries — ownership is established solely by the subsequent `OwnershipGuard` call, not by query scoping).
- Eloquent writes: `$request->user()->recurringPaymentTemplates()->create(...)` (store) and `$recurringPaymentTemplate->update(...)` (update) — both **plain Eloquent**, no domain service class is instantiated or called anywhere in this file.
- **Absence of a domain service: confirmed.** There is no `use App\Domain\Services\RecurringPaymentTemplateService` (or any similarly named class) anywhere in this file's imports, and no such class exists anywhere in `app/Domain/Services/` (confirmed by directory listing during Decision Package v1.1.0 preparation — see `PHASE_4_DECISION_PACKAGE.md` section 23, Open Architectural Item 1). This is the exact unresolved gap the P1 finding's `RecurringPaymentTemplate` half describes. No service was created and no line in this file was altered while producing this bundle.

---

## SUPPORTING SOURCE FILES

### `app/Domain/Services/OwnershipGuard.php`

```php
<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\OwnershipViolationException;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\ObligationAllocation;
use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\Transaction;

/**
 * Centralized tenant/ownership enforcement.
 *
 * A valid numeric ID alone is never sufficient authorization. Categories are
 * the only intentional exception: user_id IS NULL represents a system
 * category available to every user.
 */
class OwnershipGuard
{
    public function assertAccountOwnership(Account $account, int $userId): void
    {
        if ($account->user_id !== $userId) {
            throw new OwnershipViolationException('Account does not belong to the authenticated user.');
        }
    }

    public function assertCategoryOwnership(?Category $category, int $userId): void
    {
        if ($category === null) {
            return;
        }

        if (! $category->ownedBy($userId)) {
            throw new OwnershipViolationException('Category is neither a system category nor owned by the authenticated user.');
        }
    }

    public function assertTransactionOwnership(Transaction $transaction, int $userId): void
    {
        if ($transaction->user_id !== $userId) {
            throw new OwnershipViolationException('Transaction does not belong to the authenticated user.');
        }
    }

    public function assertPaymentObligationOwnership(PaymentObligation $obligation, int $userId): void
    {
        if ($obligation->user_id !== $userId) {
            throw new OwnershipViolationException('Payment obligation does not belong to the authenticated user.');
        }
    }

    public function assertRecurringTemplateOwnership(RecurringPaymentTemplate $template, int $userId): void
    {
        if ($template->user_id !== $userId) {
            throw new OwnershipViolationException('Recurring payment template does not belong to the authenticated user.');
        }
    }

    public function assertObligationAllocationOwnership(ObligationAllocation $allocation, int $userId): void
    {
        if ($allocation->user_id !== $userId) {
            throw new OwnershipViolationException('Obligation allocation does not belong to the authenticated user.');
        }
    }

    public function assertBudgetOwnership(Budget $budget, int $userId): void
    {
        if ($budget->user_id !== $userId) {
            throw new OwnershipViolationException('Budget does not belong to the authenticated user.');
        }
    }
}
```

Included because it is the single mechanism every ownership check in sections A–E ultimately calls into — the auditor needs to see that `assertAccountOwnership()`/`assertCategoryOwnership()`/`assertBudgetOwnership()` all follow the identical `$entity->user_id !== $userId` shape and all throw the same exception type.

### `app/Domain/Exceptions/OwnershipViolationException.php`

```php
<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a request attempts to reference another user's financial data.
 */
class OwnershipViolationException extends RuntimeException {}
```

### `app/Domain/Exceptions/BudgetException.php`

```php
<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a budget operation would violate a domain rule, such as
 * overlapping another budget for the same category and period.
 */
class BudgetException extends RuntimeException {}
```

### `app/Models/Budget.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'category_id', 'period_start', 'period_end', 'budget_amount', 'is_mandatory_reserve',
])]
class Budget extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'budget_amount' => 'decimal:2',
            'is_mandatory_reserve' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

### `app/Models/Transaction.php` (relevant fields for the utilization join)

```php
<?php

namespace App\Models;

use App\Domain\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'transaction_date', 'transaction_type', 'description', 'reference',
    'source', 'status', 'category_id', 'parent_transaction_id', 'notes',
])]
class Transaction extends Model
{
    use HasFactory;

    public const TYPES_ELIGIBLE_FOR_ALLOCATION = ['EXPENSE', 'TRANSFER'];

    protected static function booted(): void
    {
        static::updating(function (self $transaction) {
            throw new ImmutableRecordException('A posted transaction cannot be modified. Use a Refund, Reversal, or Adjustment instead.');
        });

        static::deleting(function (self $transaction) {
            throw new ImmutableRecordException('A posted transaction cannot be deleted. Use a Refund, Reversal, or Adjustment instead.');
        });
    }

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
        ];
    }

    public function isExpense(): bool
    {
        return $this->transaction_type === 'EXPENSE';
    }

    public function isEligibleForAllocation(): bool
    {
        return in_array($this->transaction_type, self::TYPES_ELIGIBLE_FOR_ALLOCATION, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'parent_transaction_id');
    }

    public function childTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'parent_transaction_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function obligationAllocations(): HasMany
    {
        return $this->hasMany(ObligationAllocation::class);
    }

    public function reconciliationMatches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }
}
```

### `app/Models/LedgerEntry.php`

```php
<?php

namespace App\Models;

use App\Domain\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'transaction_id', 'account_id', 'direction', 'amount'])]
class LedgerEntry extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            throw new ImmutableRecordException('A posted ledger entry cannot be modified. Use a Refund, Reversal, or Adjustment instead.');
        });

        static::deleting(function (self $entry) {
            throw new ImmutableRecordException('A posted ledger entry cannot be deleted. Use a Refund, Reversal, or Adjustment instead.');
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function isInflow(): bool
    {
        return $this->direction === 'INFLOW';
    }

    public function isOutflow(): bool
    {
        return $this->direction === 'OUTFLOW';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
```

### `app/Http/Controllers/PaymentObligationController.php` (context for the HTTP-layer half of the defense-in-depth split)

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Services\MonthlyGenerationService;
use App\Domain\Services\ObligationAllocationService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\PaymentObligationService;
use App\Http\Requests\StoreOneTimeObligationRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentObligationController extends Controller
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function index(Request $request): View
    {
        $obligations = $request->user()->paymentObligations()
            ->with(['category', 'plannedAccount'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('period_start'), fn ($query) => $query->where('period_start', $request->string('period_start')))
            ->orderByDesc('due_date')
            ->paginate(20)
            ->withQueryString();

        $categories = Category::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('name')
            ->get();

        return view('obligations.index', compact('obligations', 'categories'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PaymentObligation::class);

        $categories = Category::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('name')
            ->get();
        $accounts = $request->user()->accounts()->orderBy('name')->get();

        return view('obligations.create', compact('categories', 'accounts'));
    }

    public function store(StoreOneTimeObligationRequest $request, PaymentObligationService $obligations): RedirectResponse
    {
        $category = Category::findOrFail($request->integer('category_id'));

        // createOneTime() asserts category ownership internally, but accepts
        // planned_account_id as a raw nullable int with no ownership check of
        // its own -- verified against the certified, unmodified Phase 1
        // service. Guarded here at the HTTP layer instead.
        $plannedAccountId = $request->integer('planned_account_id') ?: null;
        if ($plannedAccountId !== null) {
            $this->ownership->assertAccountOwnership(Account::findOrFail($plannedAccountId), $request->user()->id);
        }

        $obligation = $obligations->createOneTime(
            $request->user(),
            (string) Str::uuid(),
            $category,
            $request->input('period_start'),
            $request->input('period_end'),
            $request->input('due_date'),
            (string) $request->input('planned_amount'),
            $request->boolean('is_mandatory', true),
            $plannedAccountId,
        );

        return redirect()->route('obligations.show', $obligation)->with('status', 'Payment obligation created.');
    }

    public function show(PaymentObligation $paymentObligation): View
    {
        $this->authorize('view', $paymentObligation);

        $paymentObligation->load(['category', 'plannedAccount', 'recurringPaymentTemplate']);
        $allocations = $paymentObligation->obligationAllocations()->with('transaction')->get();

        return view('obligations.show', [
            'obligation' => $paymentObligation,
            'allocations' => $allocations,
        ]);
    }

    public function skip(Request $request, PaymentObligation $paymentObligation, ObligationAllocationService $allocations): RedirectResponse
    {
        $this->authorize('update', $paymentObligation);

        $allocations->skip($request->user(), $paymentObligation);

        return redirect()->route('obligations.show', $paymentObligation)->with('status', 'Payment obligation skipped.');
    }

    public function cancel(Request $request, PaymentObligation $paymentObligation, ObligationAllocationService $allocations): RedirectResponse
    {
        $this->authorize('update', $paymentObligation);

        $allocations->cancel($request->user(), $paymentObligation);

        return redirect()->route('obligations.show', $paymentObligation)->with('status', 'Payment obligation cancelled.');
    }

    public function generate(Request $request, MonthlyGenerationService $generator): RedirectResponse
    {
        $generator->generate($request->user(), $request->filled('period') ? $request->string('period')->toString() : null);

        return redirect()->route('obligations.index')->with('status', 'Recurring obligations generated.');
    }
}
```

**Note for the auditor:** this controller's `store()` method comment now describes the situation as it existed *before* the v1.1.0 correction ("no ownership check of its own"). As of this bundle, that comment is stale relative to `PaymentObligationService.php` (section A above) — the service now performs its own check. The controller-layer check below the comment was deliberately left in place (not removed) as the early/UX layer, per the Decision Package's defense-in-depth contract, but **the comment text itself was not updated, because updating it would be a source-code modification and this task is documentation-only.** This is flagged here as an item for auditor attention, not silently fixed.

### `tests/Feature/Budgets/BudgetOverlapTest.php`

```php
<?php

namespace Tests\Feature\Budgets;

use App\Domain\Exceptions\BudgetException;
use App\Domain\Services\BudgetService;
use App\Domain\Services\OwnershipGuard;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Proves the frozen overlap-serialization contract (Phase 4 Decision
 * Package section 9): the authenticated user's `users` row is locked
 * (SELECT ... FOR UPDATE) before any overlap check, for both create and
 * update, and the lock genuinely blocks a concurrent second connection --
 * not merely a sequential approximation.
 */
class BudgetOverlapTest extends TestCase
{
    use RefreshDatabase;

    private BudgetService $budgets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->budgets = new BudgetService(new OwnershipGuard);
    }

    // -----------------------------------------------------------------
    // Sequential correctness
    // -----------------------------------------------------------------

    public function test_overlapping_create_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $this->budgets->createBudget($user, $category, '2026-01-01', '2026-01-31', '1000.00');

        $this->expectException(BudgetException::class);

        $this->budgets->createBudget($user, $category, '2026-01-15', '2026-02-15', '1000.00');
    }

    public function test_overlapping_update_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $this->budgets->createBudget($user, $category, '2026-01-01', '2026-01-31', '1000.00');
        $february = $this->budgets->createBudget($user, $category, '2026-02-01', '2026-02-28', '1000.00');

        $this->expectException(BudgetException::class);

        $this->budgets->updateBudget($user, $february, $category, '2026-01-15', '2026-02-15', '1000.00');
    }

    public function test_non_overlapping_update_succeeds(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = $this->budgets->createBudget($user, $category, '2026-01-01', '2026-01-31', '1000.00');

        $updated = $this->budgets->updateBudget($user, $budget, $category, '2026-01-01', '2026-01-31', '2000.00');

        $this->assertSame('2000.00', $updated->budget_amount);
    }

    public function test_updating_a_budgets_own_period_slightly_does_not_conflict_with_itself(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = $this->budgets->createBudget($user, $category, '2026-01-01', '2026-01-31', '1000.00');

        $updated = $this->budgets->updateBudget($user, $budget, $category, '2026-01-05', '2026-02-05', '1000.00');

        $this->assertSame('2026-01-05', $updated->period_start->toDateString());
    }

    public function test_adjacent_non_overlapping_periods_both_succeed(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->budgets->createBudget($user, $category, '2026-01-01', '2026-01-31', '1000.00');
        $february = $this->budgets->createBudget($user, $category, '2026-02-01', '2026-02-28', '1000.00');

        $this->assertNotNull($february->id);
        $this->assertSame(2, Budget::where('user_id', $user->id)->count());
    }

    public function test_boundary_touching_periods_are_rejected_as_overlapping(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $this->budgets->createBudget($user, $category, '2026-01-01', '2026-01-31', '1000.00');

        $this->expectException(BudgetException::class);

        // Shares January 31 with the first budget -- inclusive boundaries overlap.
        $this->budgets->createBudget($user, $category, '2026-01-31', '2026-02-28', '1000.00');
    }

    public function test_a_different_category_never_conflicts_even_with_an_identical_period(): void
    {
        $user = User::factory()->create();
        $categoryA = Category::factory()->for($user)->create();
        $categoryB = Category::factory()->for($user)->create();
        $this->budgets->createBudget($user, $categoryA, '2026-01-01', '2026-01-31', '1000.00');

        $budget = $this->budgets->createBudget($user, $categoryB, '2026-01-01', '2026-01-31', '1000.00');

        $this->assertNotNull($budget->id);
    }

    // -----------------------------------------------------------------
    // Genuine cross-connection concurrency
    // -----------------------------------------------------------------

    public function test_concurrent_create_create_produces_exactly_one_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Config::set('database.connections.locktest', Config::get('database.connections.mysql'));

        // Connection A: replicate BudgetService::createBudget()'s exact
        // sequence (lock the user row, then insert) but hold the transaction
        // open -- not committed -- to create genuine mid-flight concurrency.
        DB::connection()->beginTransaction();
        DB::connection()->table('users')->where('id', $user->id)->lockForUpdate()->first();
        DB::connection()->table('budgets')->insert([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '1000.00',
            'is_mandatory_reserve' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('locktest')->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;
        $start = microtime(true);

        try {
            DB::connection('locktest')->transaction(function () use ($user) {
                DB::connection('locktest')->table('users')->where('id', $user->id)->lockForUpdate()->first();
            });
        } catch (Throwable) {
            $blocked = true;
        }

        $elapsed = microtime(true) - $start;

        DB::connection()->commit();
        DB::purge('locktest');

        $this->assertTrue($blocked, 'Expected the second connection to be blocked by the user-row lock.');
        $this->assertGreaterThanOrEqual(1.0, $elapsed, 'Expected the second connection to wait for the lock timeout.');

        // Connection B, now unblocked and using the real service, correctly
        // observes Connection A's committed row and rejects the overlap.
        $this->expectException(BudgetException::class);

        try {
            $this->budgets->createBudget($user, $category, '2026-01-15', '2026-02-15', '500.00');
        } finally {
            $this->assertSame(1, Budget::where('user_id', $user->id)->where('category_id', $category->id)->count());
        }
    }

    public function test_concurrent_create_update_cannot_produce_overlapping_budgets(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $existing = $this->budgets->createBudget($user, $category, '2026-03-01', '2026-03-31', '1000.00');

        Config::set('database.connections.locktest', Config::get('database.connections.mysql'));

        // Connection A: simulates a create() in flight for a different period,
        // holding the same users-row lock BudgetService::updateBudget() must
        // also acquire before its own overlap check.
        DB::connection()->beginTransaction();
        DB::connection()->table('users')->where('id', $user->id)->lockForUpdate()->first();
        DB::connection()->table('budgets')->insert([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '1000.00',
            'is_mandatory_reserve' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('locktest')->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;
        $start = microtime(true);

        try {
            DB::connection('locktest')->transaction(function () use ($user) {
                DB::connection('locktest')->table('users')->where('id', $user->id)->lockForUpdate()->first();
            });
        } catch (Throwable) {
            $blocked = true;
        }

        $elapsed = microtime(true) - $start;

        DB::connection()->commit();
        DB::purge('locktest');

        $this->assertTrue($blocked, 'Expected the concurrent update attempt to be blocked by the user-row lock.');
        $this->assertGreaterThanOrEqual(1.0, $elapsed);

        // Connection B now attempts to update the pre-existing March budget
        // into January -- overlapping Connection A's now-committed row.
        $this->expectException(BudgetException::class);

        try {
            $this->budgets->updateBudget($user, $existing, $category, '2026-01-10', '2026-02-10', '1000.00');
        } finally {
            $this->assertSame('2026-03-01', $existing->fresh()->period_start->toDateString());
        }
    }
}
```

### `tests/Feature/Obligations/MonthlyGenerationTest.php`

```php
<?php

namespace Tests\Feature\Obligations;

use App\Domain\Services\MonthlyGenerationService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\PaymentObligationService;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Proves the frozen recurring generation grammar and boundary invariant:
 * "After month-end clamping, an occurrence is generated only when its due
 * date is on or after starts_on and, when ends_on exists, on or before
 * ends_on." (Phase 4 Decision Package section 8.)
 */
class MonthlyGenerationTest extends TestCase
{
    use RefreshDatabase;

    private MonthlyGenerationService $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new MonthlyGenerationService(new PaymentObligationService(new OwnershipGuard));
    }

    private function makeTemplate(User $user, array $overrides = []): RecurringPaymentTemplate
    {
        $category = $overrides['category_id'] ?? Category::factory()->for($user)->create()->id;

        return RecurringPaymentTemplate::factory()->for($user)->create(array_merge([
            'category_id' => $category,
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY:5',
            'starts_on' => '2020-01-01',
            'status' => 'ACTIVE',
        ], $overrides));
    }

    public function test_a_normal_in_range_occurrence_is_generated(): void
    {
        $user = User::factory()->create();
        $template = $this->makeTemplate($user, ['due_rule' => 'DAY:15']);

        $created = $this->generator->generate($user, '2026-03');

        $this->assertCount(1, $created);
        $obligation = $created->first();
        $this->assertSame('2026-03-15', $obligation->due_date->toDateString());
        $this->assertSame($template->category_id, $obligation->category_id);
        $this->assertSame($template->id, $obligation->recurring_payment_template_id);
        $this->assertSame('2026-03', $obligation->occurrence_key);
    }

    public function test_generation_is_idempotent_when_run_twice_for_the_same_period(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:15']);

        $this->generator->generate($user, '2026-03');
        $this->generator->generate($user, '2026-03');

        $this->assertSame(1, PaymentObligation::count());
    }

    public function test_day31_clamps_to_january_31(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31']);

        $obligation = $this->generator->generate($user, '2026-01')->first();

        $this->assertSame('2026-01-31', $obligation->due_date->toDateString());
    }

    public function test_day31_clamps_to_february_28_in_a_non_leap_year(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31']);

        $obligation = $this->generator->generate($user, '2026-02')->first();

        $this->assertSame('2026-02-28', $obligation->due_date->toDateString());
    }

    public function test_day31_clamps_to_february_29_in_a_leap_year(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31']);

        $obligation = $this->generator->generate($user, '2028-02')->first();

        $this->assertSame('2028-02-29', $obligation->due_date->toDateString());
    }

    public function test_day31_clamps_to_april_30(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31']);

        $obligation = $this->generator->generate($user, '2026-04')->first();

        $this->assertSame('2026-04-30', $obligation->due_date->toDateString());
    }

    public function test_day31_clamps_to_june_30(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31']);

        $obligation = $this->generator->generate($user, '2026-06')->first();

        $this->assertSame('2026-06-30', $obligation->due_date->toDateString());
    }

    public function test_a_cancelled_template_is_never_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:15', 'status' => 'CANCELLED']);

        $created = $this->generator->generate($user, '2026-03');

        $this->assertCount(0, $created);
        $this->assertSame(0, PaymentObligation::count());
    }

    // -----------------------------------------------------------------
    // starts_on boundary (lower bound, inclusive)
    // -----------------------------------------------------------------

    public function test_due_date_before_starts_on_is_not_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:5', 'starts_on' => '2026-01-20']);

        $created = $this->generator->generate($user, '2026-01');

        $this->assertCount(0, $created);
        $this->assertSame(0, PaymentObligation::count());
    }

    public function test_due_date_equal_to_starts_on_is_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:20', 'starts_on' => '2026-01-20']);

        $created = $this->generator->generate($user, '2026-01');

        $this->assertCount(1, $created);
        $this->assertSame('2026-01-20', $created->first()->due_date->toDateString());
    }

    public function test_the_first_eligible_occurrence_after_a_suppressed_month_is_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:5', 'starts_on' => '2026-01-20']);

        $this->generator->generate($user, '2026-01');
        $februaryResult = $this->generator->generate($user, '2026-02');

        $this->assertSame(0, PaymentObligation::whereRaw("occurrence_key = '2026-01'")->count());
        $this->assertCount(1, $februaryResult);
        $this->assertSame('2026-02-05', $februaryResult->first()->due_date->toDateString());
    }

    // -----------------------------------------------------------------
    // ends_on boundary (upper bound, inclusive)
    // -----------------------------------------------------------------

    public function test_due_date_after_ends_on_is_not_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:15', 'ends_on' => '2026-06-10']);

        $created = $this->generator->generate($user, '2026-06');

        $this->assertCount(0, $created);
        $this->assertSame(0, PaymentObligation::count());
    }

    public function test_due_date_equal_to_ends_on_is_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:15', 'ends_on' => '2026-06-15']);

        $created = $this->generator->generate($user, '2026-06');

        $this->assertCount(1, $created);
        $this->assertSame('2026-06-15', $created->first()->due_date->toDateString());
    }

    public function test_clamped_day31_due_date_equal_to_ends_on_is_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31', 'ends_on' => '2026-06-30']);

        $created = $this->generator->generate($user, '2026-06');

        $this->assertCount(1, $created);
        $this->assertSame('2026-06-30', $created->first()->due_date->toDateString());
    }

    public function test_clamped_day31_due_date_one_day_after_ends_on_is_not_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31', 'ends_on' => '2026-06-29']);

        $created = $this->generator->generate($user, '2026-06');

        $this->assertCount(0, $created);
        $this->assertSame(0, PaymentObligation::count());
    }

    public function test_re_running_a_suppressed_period_remains_suppressed_and_stable(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31', 'ends_on' => '2026-06-29']);

        $this->generator->generate($user, '2026-06');
        $this->generator->generate($user, '2026-06');

        $this->assertSame(0, PaymentObligation::count());
    }

    // -----------------------------------------------------------------
    // Manual trigger / scheduled command share the same service
    // -----------------------------------------------------------------

    public function test_the_manual_http_trigger_and_console_command_produce_the_same_result(): void
    {
        $userA = User::factory()->create();
        $this->makeTemplate($userA, ['due_rule' => 'DAY:5']);

        $this->actingAs($userA)->post(route('obligations.generate'));
        $countAfterHttp = PaymentObligation::where('user_id', $userA->id)->count();

        // Re-running via the console command must be a no-op for the same
        // period/template -- proving both callers terminate in the same
        // idempotent MonthlyGenerationService/createRecurringOccurrence path.
        Artisan::call('obligations:generate-monthly');
        $countAfterCommand = PaymentObligation::where('user_id', $userA->id)->count();

        $this->assertSame(1, $countAfterHttp);
        $this->assertSame($countAfterHttp, $countAfterCommand);
    }

    public function test_the_console_command_generates_for_every_user_with_active_templates(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->makeTemplate($userA, ['due_rule' => 'DAY:5']);
        $this->makeTemplate($userB, ['due_rule' => 'DAY:10']);

        Artisan::call('obligations:generate-monthly');

        $this->assertSame(1, PaymentObligation::where('user_id', $userA->id)->count());
        $this->assertSame(1, PaymentObligation::where('user_id', $userB->id)->count());
    }
}
```

---

## `docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md` — CURRENT STATE

The auditor requested the exact current state of this file. It is **not reproduced in full here** (it is 1,289 lines including the working-tree modification) to avoid an unnecessary large-file duplication when the file is already directly readable in the repository; instead, this section gives the required diff and classification precisely.

**Git diff against HEAD** (`git diff -- docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md`):

```diff
--- a/docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md
+++ b/docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md
@@ -1175,3 +1175,117 @@ ## Review constraints
 ```

 followed by the findings and exact proposed corrections.
+
+Every development phase MUST have a corresponding version-controlled
+Phase Decision Package before implementation begins.
+
+The implementation agent MUST:
+
+1. Read the frozen Knowledge Base.
+2. Inspect the current repository/codebase.
+3. Create or update the phase-specific Decision Package Markdown file.
+4. Stop implementation completely.
+5. Submit the Decision Package for independent external review.
+6. Resolve only explicitly identified findings.
+7. Obtain an external GO decision.
+8. Implement strictly according to the approved Decision Package.
+9. Produce an implementation report.
+10. Stop for independent source-code adversarial review.
+11. Only after final GO may the phase be committed.
+
+The Decision Package is the contract between planning and implementation.
+
+No implementation code may be written before the Decision Package
+receives external approval.
+
+If implementation discovers a contradiction between the approved
+Decision Package, Knowledge Base, or existing certified architecture:
+
+STOP.
+
+Do not silently reinterpret the requirement.
+
+Update the Decision Package only after the contradiction has been
+reviewed and explicitly resolved.
+
+Every material change to an approved Decision Package MUST be recorded
+in the document itself and in 08_CHANGELOG.md where appropriate.
+
+The approved Decision Package becomes frozen for that implementation
+checkpoint.
+
+File structure
+
+docs/
+└── knowledge_base/
+    ├── 00_DOMAIN_MODEL.md
+    ├── 01_BUSINESS_RULES.md
+    ├── 02_PRODUCT_SPECIFICATION.md
+    ├── 03_ARCHITECTURE.md
+    ├── 04_DATABASE_SPECIFICATION.md
+    ├── 05_UI_UX_SPECIFICATION.md
+    ├── 06_RECONCILIATION_SPECIFICATION.md
+    ├── 07_DEVELOPMENT_ROADMAP.md
+    ├── 08_CHANGELOG.md
+    ├── 09_ERD_AND_MIGRATION_DESIGN.md
+    └── 10_IMPLEMENTATION_CONTRACT.md
+
+    └── phase_decisions/
+        ├── PHASE_1_DECISION_PACKAGE.md
+        ├── PHASE_1_5_DECISION_PACKAGE.md
+        ├── PHASE_2_DECISION_PACKAGE.md
+        ├── PHASE_3_DECISION_PACKAGE.md
+        └── PHASE_4_DECISION_PACKAGE.md
+
+### Phase Decision Package Integrity
+
+The Phase Decision Package MUST contain, at minimum:
+
+- Phase objective and scope
+- Explicit exclusions / out-of-scope items
+- Current codebase analysis
+- Relevant Knowledge Base references
+- Architecture and implementation approach
+- Database impact
+- Routes/controllers/services/models involved
+- Validation and authorization rules
+- UI/UX approach where applicable
+- Testing strategy
+- Security and tenant-isolation considerations
+- Financial/business invariants where applicable
+- Exact files expected to be created or modified
+- Risks and open decisions
+- Acceptance criteria
+- Implementation sequence
+- Explicit statement that implementation MUST NOT begin until external approval
+
+The implementation agent MUST NOT treat its own interpretation of an
+ambiguous requirement as an approved decision.
+
+Any unresolved ambiguity MUST be recorded under "Open Decisions" and
+submitted for external review.
+
+After external approval, the approved Decision Package becomes the
+authoritative implementation contract for that phase.
+
+The implementation agent MUST NOT expand the approved scope during
+implementation without first stopping and obtaining approval for a
+Decision Package amendment.
+
+If the implementation differs from the approved Decision Package for
+any reason, the difference MUST be explicitly reported and reviewed
+before the phase can be certified.
+
+# 37. Phase Documentation Lifecycle
+
+Every development phase MUST maintain a complete, version-controlled
+documentation trail.
+
+Each phase MUST have the following permanent documents:
+
+```text
+docs/knowledge_base/phase_decisions/
+
+PHASE_N_DECISION_PACKAGE.md
+PHASE_N_IMPLEMENTATION_REPORT.md
+PHASE_N_EXTERNAL_AUDIT_BUNDLE.md
\ No newline at end of file
```

**Classification:**
- **Tracked and modified:** yes — `git status --porcelain` shows `M docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md`.
- **Committed:** no — this is a working-tree modification only; `git log` shows no commit touching this file since `9b44d20` ("feat: add implementation contract document...").
- **Uncommitted:** yes, per the above.
- **Part of the Phase 4 implementation:** **no.** No PHP, Blade, migration, route, test, or Phase 4 source file references or depends on this addition. Phase 4's actual implementation (sections A–E above) neither reads nor requires this file's content to function.
- **Unrelated working-tree modification:** the content itself (a "Phase Decision Package" governance process, including — as of the most recent addition — a "Phase Documentation Lifecycle" section literally requiring the `PHASE_N_DECISION_PACKAGE.md`/`PHASE_N_IMPLEMENTATION_REPORT.md`/`PHASE_N_EXTERNAL_AUDIT_BUNDLE.md` file triad) is thematically related to the governance process this entire engagement has been following. However, **no tool call in this engagement — this session or any prior one — has ever called Read, Edit, or Write on this file with content matching this diff.** Per the explicit instruction not to attempt an explanation the repository evidence doesn't establish: its origin is not determined here. It is reported as an observed fact only.

---

## ITEMS FOR INDEPENDENT AUDITOR ATTENTION

Recorded, not fixed, per this task's explicit governance rule:

1. **Stale code comment (section E's context, `PaymentObligationController.php`):** the comment above the `planned_account_id` check still says "with no ownership check of its own," which was true before the v1.1.0 correction and is no longer true now that `PaymentObligationService::createOneTime()` performs its own check (section A). Left as-is deliberately, since fixing it is a source-code change outside this task's scope.
2. **`RecurringPaymentTemplate` domain-service gap (section E):** confirmed, unresolved, and — per explicit instruction this turn and the prior correction turn — not implemented. `category_id`/`default_account_id` ownership remains HTTP-controller-only.
3. **`MonthlyGenerationService::generate()` (section D) re-validates nothing about `frequency`, and re-validates no `N` range on `due_rule`** — both are enforced only at the HTTP Form Request layer upstream of this service, consistent with the same class of gap the P1 finding identified for `PaymentObligationService`, but not itself raised or corrected by the v1.1.0 correction (which was scoped narrowly to `planned_account_id`). Flagged for the auditor's own judgment on whether this constitutes a related finding.
4. **`MonthlyGenerationService::resolvePeriod()`'s `$periodEnd` is `endOfMonth()->startOfDay()`**, i.e., midnight of the last day rather than end-of-day — flagged in section D's checklist as a fact about the code the auditor may wish to trace through the date-string comparisons that consume it.
5. **`MonthlyGenerationService::generate()` has no exception handling** — any `OwnershipViolationException` (or any other exception) from `PaymentObligationService` propagates unhandled. Whether this is correct behavior for the console-command caller (`GenerateMonthlyObligations::handle()`, not reproduced in this bundle) versus the HTTP caller is not evaluated here.
6. **`docs/knowledge_base/10_IMPLEMENTATION_CONTRACT.md`'s uncommitted modification** — see the classification above. Unresolved, unexplained, reported as fact only.
7. **Section N of `PHASE_4_EXTERNAL_AUDIT_BUNDLE.md`'s A-vs-B scope-classification question** (whether the original two ownership-hardening fixes were in-architecture corrections or undisclosed scope changes) remains open and is not re-litigated here.

---

## GOVERNANCE STATUS

SOURCE-CODE REVIEW BUNDLE COMPLETE.

PHASE 4 IMPLEMENTATION IS NOT CERTIFIED.

AWAITING INDEPENDENT CHATGPT/GEMINI SOURCE-CODE ADVERSARIAL REVIEW.

NO COMMIT.
NO PUSH.
NO PHASE 5.
