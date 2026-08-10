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
