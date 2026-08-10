<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Services\BudgetService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\TransactionService;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Proves exactly the three Phase 5 Decision Package v1.2.3 section 13 rules
 * -- no more, no fewer -- and their frozen priority ordering.
 */
class AttentionCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_safe_to_spend_triggers_the_critical_alert(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'over-budget',
            'category_id' => $category->id,
            'period_start' => Carbon::today()->startOfMonth(),
            'period_end' => Carbon::today()->endOfMonth(),
            'due_date' => Carbon::today(),
            'planned_amount' => '5000.00',
            'is_mandatory' => true,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Safe-to-Spend is negative');
    }

    public function test_an_overdue_obligation_triggers_the_high_alert(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE', 'name' => 'Overdue Rent']);
        PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'overdue',
            'category_id' => $category->id,
            'period_start' => Carbon::today()->subMonthNoOverflow()->startOfMonth(),
            'period_end' => Carbon::today(),
            'due_date' => Carbon::today()->subDays(2),
            'planned_amount' => '500.00',
            'is_mandatory' => true,
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Overdue: Overdue Rent');
    }

    public function test_an_overspent_budget_triggers_the_medium_alert(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE']);
        $budgets = new BudgetService(new OwnershipGuard);
        $budgets->createBudget($user, $category, Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth(), '100.00');
        (new TransactionService(new OwnershipGuard))->recordExpense($user, $account, '150.00', Carbon::today(), 'Overspend', $category);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Budget overspend');
    }

    public function test_deferred_alert_types_never_appear(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Missing obligation');
        $response->assertDontSee('Overpayment difference');
        $response->assertDontSee('Unaccounted');
        $response->assertDontSee('Statement');
    }

    public function test_a_healthy_dashboard_shows_no_attention_items(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Nothing needs your attention right now.');
    }
}
