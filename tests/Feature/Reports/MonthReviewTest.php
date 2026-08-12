<?php

namespace Tests\Feature\Reports;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Month Review (PHASE_6_DECISION_PACKAGE.md section 4.6, Open Decision 1):
 * strictly read-only, no close-state, no mutation, no state-changing route.
 */
class MonthReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_review_renders_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.month-review'))->assertOk();
    }

    public function test_month_review_defaults_to_the_current_month(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.month-review'));

        $response->assertOk();
        $response->assertSee(now()->format('F Y'));
    }

    public function test_no_state_changing_route_exists_for_month_review(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('reports.month-review'))->assertStatus(405);
        $this->actingAs($user)->put(route('reports.month-review'))->assertStatus(405);
        $this->actingAs($user)->delete(route('reports.month-review'))->assertStatus(405);
    }

    public function test_viewing_month_review_does_not_mutate_any_financial_data(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '1000.00']);

        $before = Transaction::query()->count();

        $this->actingAs($user)->get(route('reports.month-review'))->assertOk();

        $this->assertSame($before, Transaction::query()->count());
    }

    public function test_no_month_close_route_or_close_state_table_is_introduced(): void
    {
        $this->assertFalse(Route::has('reports.month-close'));
        $this->assertFalse(Schema::hasTable('month_closes'));
    }
}
