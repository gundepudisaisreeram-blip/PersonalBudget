<?php

namespace Tests\Feature\Reports;

use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\TransactionService;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tenant isolation (PHASE_6_DECISION_PACKAGE.md section 6): a guest is
 * redirected; a report never includes another tenant's data; a spoofed
 * account/category filter ID is rejected, never silently excluded.
 */
class ReportOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function reportRoutes(): array
    {
        return [
            'reports.cash-flow',
            'reports.category-spending',
            'reports.budget',
            'reports.obligations',
            'reports.trends',
            'reports.monthly-summary',
            'reports.month-review',
        ];
    }

    public function test_a_guest_is_redirected_to_login_from_every_report(): void
    {
        foreach ($this->reportRoutes() as $route) {
            $this->get(route($route))->assertRedirect('/login');
        }
    }

    public function test_cash_flow_report_never_includes_another_tenants_data(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        $ownerAccount = Account::factory()->for($owner)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);
        $attackerAccount = Account::factory()->for($attacker)->create(['account_type' => 'ASSET', 'opening_balance' => '0.00']);

        $transactions = new TransactionService(new OwnershipGuard);
        $transactions->recordIncome($owner, $ownerAccount, '99999.00', $today, 'Owner salary');
        $transactions->recordIncome($attacker, $attackerAccount, '10.00', $today, 'Attacker salary');

        $response = $this->actingAs($attacker)->get(route('reports.cash-flow', [
            'start_date' => $today->toDateString(),
            'end_date' => $today->toDateString(),
        ]));

        $response->assertOk();
        $response->assertDontSee('99999.00');
        $response->assertSee('10.00');
    }

    public function test_a_spoofed_account_id_filter_is_rejected_not_silently_dropped(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $foreignAccount = Account::factory()->for($owner)->create(['account_type' => 'ASSET']);

        $response = $this->actingAs($attacker)->get(route('reports.cash-flow', [
            'account_id' => [$foreignAccount->id],
        ]));

        $response->assertNotFound();
    }

    public function test_a_mixed_valid_and_foreign_account_id_selection_is_rejected(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $foreignAccount = Account::factory()->for($owner)->create(['account_type' => 'ASSET']);
        $ownAccount = Account::factory()->for($attacker)->create(['account_type' => 'ASSET']);

        $response = $this->actingAs($attacker)->get(route('reports.cash-flow', [
            'account_id' => [$ownAccount->id, $foreignAccount->id],
        ]));

        $response->assertNotFound();
    }

    public function test_a_spoofed_category_id_filter_is_rejected_on_the_budget_report(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $foreignCategory = Category::factory()->for($owner)->create(['category_type' => 'EXPENSE']);

        $response = $this->actingAs($attacker)->get(route('reports.budget', [
            'category_id' => $foreignCategory->id,
        ]));

        $response->assertForbidden();
    }

    public function test_a_spoofed_category_id_filter_is_rejected_on_the_obligations_report(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $foreignCategory = Category::factory()->for($owner)->create(['category_type' => 'EXPENSE']);

        $response = $this->actingAs($attacker)->get(route('reports.obligations', [
            'category_id' => $foreignCategory->id,
        ]));

        $response->assertForbidden();
    }

    public function test_a_system_category_is_permitted_for_every_authenticated_user(): void
    {
        $user = User::factory()->create();
        $systemCategory = Category::factory()->create(['user_id' => null, 'category_type' => 'EXPENSE']);

        $response = $this->actingAs($user)->get(route('reports.budget', [
            'category_id' => $systemCategory->id,
        ]));

        $response->assertOk();
    }
}
