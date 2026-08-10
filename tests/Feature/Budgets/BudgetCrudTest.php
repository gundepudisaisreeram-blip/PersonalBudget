<?php

namespace Tests\Feature\Budgets;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('budgets.store'), [
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '5000.00',
        ]);

        $budget = Budget::where('category_id', $category->id)->firstOrFail();
        $response->assertRedirect(route('budgets.edit', $budget));
        $this->assertSame($user->id, $budget->user_id);
        $this->assertSame('5000.00', $budget->budget_amount);
    }

    public function test_a_budget_can_accept_a_zero_amount(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('budgets.store'), [
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '0.00',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('budgets', ['category_id' => $category->id, 'budget_amount' => '0.00']);
    }

    public function test_creating_a_budget_rejects_a_negative_amount(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('budgets.store'), [
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '-100.00',
        ]);

        $response->assertSessionHasErrors('budget_amount');
        $this->assertDatabaseMissing('budgets', ['category_id' => $category->id]);
    }

    public function test_a_user_can_edit_their_own_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '5000.00',
            'is_mandatory_reserve' => false,
        ]);

        $response = $this->actingAs($user)->put(route('budgets.update', $budget), [
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '7500.00',
            'is_mandatory_reserve' => '1',
        ]);

        $response->assertRedirect(route('budgets.edit', $budget));
        $budget->refresh();
        $this->assertSame('7500.00', $budget->budget_amount);
        $this->assertTrue($budget->is_mandatory_reserve);
    }

    public function test_there_is_no_delete_route_for_budgets(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'budget_amount' => '5000.00',
            'is_mandatory_reserve' => false,
        ]);

        $response = $this->actingAs($user)->delete('/budgets/'.$budget->id);

        // No DELETE route exists for /budgets/{budget} at all -- Laravel
        // returns 405 (the URI pattern matches PUT, not 404's "no match").
        $response->assertStatus(405);
        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    }
}
