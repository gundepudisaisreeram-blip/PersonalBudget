<?php

namespace Tests\Feature\Reports;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE_6_DECISION_PACKAGE.md section 14 Adversarial Test Matrix -- the
 * scenarios not already covered by DateContractTest/ReportOwnershipTest.
 */
class ReportAdversarialTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_period_renders_a_stable_empty_state_with_no_error(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);

        $response = $this->actingAs($user)->get(route('reports.category-spending', [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
        ]));

        $response->assertOk();
        $response->assertSee('No transactions for this period.');
    }

    public function test_repeated_identical_account_id_values_do_not_double_count(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '250.00']);

        $response = $this->actingAs($user)->get(route('reports.cash-flow', [
            'account_id' => [$account->id, $account->id],
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
        ]));

        $response->assertOk();
        $response->assertSee('250.00');
    }

    public function test_no_write_route_exists_on_any_report_url(): void
    {
        $user = User::factory()->create();

        foreach (['reports.cash-flow', 'reports.category-spending', 'reports.budget', 'reports.obligations', 'reports.trends'] as $route) {
            $this->actingAs($user)->post(route($route))->assertStatus(405);
        }
    }

    public function test_a_leap_year_february_range_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.cash-flow', [
            'start_date' => '2024-02-01',
            'end_date' => '2024-02-29',
        ]))->assertOk();
    }
}
