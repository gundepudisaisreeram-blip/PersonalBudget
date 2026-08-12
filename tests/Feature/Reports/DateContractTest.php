<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Canonical Date-Range Contract (PHASE_6_DECISION_PACKAGE.md section 4.0,
 * Open Decision 4): both dates optional; exactly one supplied is rejected;
 * maximum range one year; future report dates rejected; end date inclusive.
 */
class DateContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_omitting_both_dates_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.cash-flow'))->assertOk();
    }

    public function test_supplying_both_valid_dates_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.cash-flow', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-07',
        ]))->assertOk();
    }

    public function test_supplying_only_a_start_date_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.cash-flow', [
            'start_date' => '2026-08-01',
        ]))->assertSessionHasErrors('end_date');
    }

    public function test_supplying_only_an_end_date_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.cash-flow', [
            'end_date' => '2026-08-07',
        ]))->assertSessionHasErrors('start_date');
    }

    public function test_a_range_exceeding_one_year_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.cash-flow', [
            'start_date' => '2024-01-01',
            'end_date' => '2026-01-02',
        ]))->assertSessionHasErrors('end_date');
    }

    public function test_a_future_end_date_is_rejected(): void
    {
        $user = User::factory()->create();
        $future = Carbon::now(config('app.timezone'))->addDays(5)->toDateString();

        $this->actingAs($user)->get(route('reports.cash-flow', [
            'start_date' => '2026-08-01',
            'end_date' => $future,
        ]))->assertSessionHasErrors('end_date');
    }

    public function test_end_before_start_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.cash-flow', [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-01',
        ]))->assertSessionHasErrors('end_date');
    }

    public function test_a_same_day_range_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.cash-flow', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
        ]))->assertOk();
    }

    public function test_a_future_month_on_the_monthly_period_reports_is_rejected(): void
    {
        $user = User::factory()->create();
        $futureMonth = Carbon::now(config('app.timezone'))->addMonths(2)->format('Y-m');

        $this->actingAs($user)->get(route('reports.month-review', [
            'month' => $futureMonth,
        ]))->assertSessionHasErrors('month');
    }
}
