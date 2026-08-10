<?php

namespace Tests\Feature\Dashboard;

use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login_from_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_an_authenticated_user_visiting_login_is_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/dashboard');
    }

    public function test_a_user_with_no_accounts_sees_the_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Set up your first account to begin.');
    }

    public function test_a_users_dashboard_never_includes_another_users_financial_data(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        Account::factory()->for($owner)->create(['account_type' => 'ASSET', 'opening_balance' => '99999.00']);
        $ownerCategory = Category::factory()->for($owner)->create(['category_type' => 'EXPENSE']);
        PaymentObligation::create([
            'user_id' => $owner->id,
            'idempotency_key' => 'owner-obligation',
            'category_id' => $ownerCategory->id,
            'period_start' => Carbon::today()->startOfMonth(),
            'period_end' => Carbon::today()->endOfMonth(),
            'due_date' => Carbon::today(),
            'planned_amount' => '5000.00',
            'is_mandatory' => true,
            'status' => 'PENDING',
        ]);

        Account::factory()->for($attacker)->create(['account_type' => 'ASSET', 'opening_balance' => '10.00']);

        $response = $this->actingAs($attacker)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('99999.00');
        $response->assertSee('10.00');
    }
}
