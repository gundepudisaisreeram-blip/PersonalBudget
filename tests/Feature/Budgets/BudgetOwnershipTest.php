<?php

namespace Tests\Feature\Budgets;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeBudget(User $user): Budget
    {
        $category = Category::factory()->for($user)->create();

        return Budget::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '5000.00',
            'is_mandatory_reserve' => false,
        ]);
    }

    public function test_a_guest_is_redirected_to_login_from_every_budget_route(): void
    {
        $budget = $this->makeBudget(User::factory()->create());

        $this->get('/budgets')->assertRedirect('/login');
        $this->get('/budgets/create')->assertRedirect('/login');
        $this->post('/budgets', [])->assertRedirect('/login');
        $this->get(route('budgets.edit', $budget))->assertRedirect('/login');
        $this->put(route('budgets.update', $budget), [])->assertRedirect('/login');
    }

    public function test_a_user_cannot_edit_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $this->actingAs($attacker)->get(route('budgets.edit', $budget))->assertForbidden();

        $this->actingAs($attacker)->put(route('budgets.update', $budget), [
            'category_id' => $budget->category_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '9999.00',
        ])->assertForbidden();

        $this->assertSame('5000.00', $budget->fresh()->budget_amount);
    }

    public function test_a_spoofed_category_id_on_create_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $ownerCategory = Category::factory()->for($owner)->create();
        $budgetCount = Budget::count();

        $response = $this->actingAs($attacker)->post(route('budgets.store'), [
            'category_id' => $ownerCategory->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '1000.00',
        ]);

        $response->assertForbidden();
        $this->assertSame($budgetCount, Budget::count());
    }

    public function test_a_spoofed_category_id_on_update_is_rejected_with_a_generic_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $ownerCategory = Category::factory()->for($owner)->create();
        $budget = $this->makeBudget($attacker);

        $response = $this->actingAs($attacker)->put(route('budgets.update', $budget), [
            'category_id' => $ownerCategory->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '1000.00',
        ]);

        $response->assertForbidden();
        $this->assertNotSame($ownerCategory->id, $budget->fresh()->category_id);
    }

    public function test_a_spoofed_user_id_in_the_create_payload_is_ignored(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user)->post(route('budgets.store'), [
            'user_id' => $victim->id,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '1000.00',
        ]);

        $budget = Budget::where('category_id', $category->id)->firstOrFail();
        $this->assertSame($user->id, $budget->user_id);
        $this->assertNotSame($victim->id, $budget->user_id);
    }
}
