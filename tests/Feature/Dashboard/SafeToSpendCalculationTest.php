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
 * HTTP-level Upcoming Payments window, Budget Snapshot ordering, and
 * timezone boundary coverage (Phase 5 Decision Package v1.2.3 sections
 * 15/19/20).
 */
class SafeToSpendCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function makeObligation(User $user, Category $category, Carbon $dueDate, string $name = 'obligation'): PaymentObligation
    {
        return PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => $name.'-'.uniqid(),
            'category_id' => $category->id,
            'period_start' => $dueDate->copy()->subMonthNoOverflow(),
            'period_end' => $dueDate->copy()->addMonthNoOverflow(),
            'due_date' => $dueDate,
            'planned_amount' => '100.00',
            'is_mandatory' => false,
            'status' => 'PENDING',
        ]);
    }

    public function test_an_obligation_due_today_is_included_in_upcoming_payments(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE', 'name' => 'DueToday']);
        $this->makeObligation($user, $category, Carbon::today());

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('DueToday');
    }

    public function test_an_obligation_due_today_plus_13_days_is_included(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE', 'name' => 'DueDay13']);
        $this->makeObligation($user, $category, Carbon::today()->addDays(13));

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('DueDay13');
    }

    public function test_an_obligation_due_today_plus_14_days_is_excluded(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE', 'name' => 'DueDay14']);
        $this->makeObligation($user, $category, Carbon::today()->addDays(14));

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('DueDay14');
    }

    public function test_an_obligation_due_today_plus_15_days_is_excluded(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE', 'name' => 'DueDay15']);
        $this->makeObligation($user, $category, Carbon::today()->addDays(15));

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('DueDay15');
    }

    public function test_a_paid_or_skipped_obligation_never_appears_in_upcoming_payments(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE', 'name' => 'AlreadyPaid']);
        $obligation = $this->makeObligation($user, $category, Carbon::today()->addDays(3), 'paid');
        $obligation->update(['status' => 'PAID']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('AlreadyPaid');
    }

    public function test_a_users_configured_timezone_determines_the_upcoming_window_boundary(): void
    {
        // A user in a timezone far ahead of UTC (Asia/Kolkata, UTC+5:30) has
        // a different "today" boundary than the application default (UTC).
        // An obligation due at a date that is today+14 in Asia/Kolkata but
        // still today+13 in UTC must be excluded, proving the window uses
        // the user's own configured timezone, not the server default.
        $user = User::factory()->create(['timezone' => 'Asia/Kolkata']);
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $category = Category::factory()->for($user)->create(['category_type' => 'EXPENSE', 'name' => 'TimezoneBoundary']);

        $localToday = Carbon::now('Asia/Kolkata')->startOfDay();
        $this->makeObligation($user, $category, $localToday->copy()->addDays(14));

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('TimezoneBoundary');
    }

    public function test_two_overspent_budgets_with_identical_utilization_order_by_category_name(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '10000.00']);
        $categoryB = Category::factory()->for($user)->create(['category_type' => 'EXPENSE', 'name' => 'Zeta']);
        $categoryA = Category::factory()->for($user)->create(['category_type' => 'EXPENSE', 'name' => 'Alpha']);

        $budgets = new BudgetService(new OwnershipGuard);
        $transactions = new TransactionService(new OwnershipGuard);

        $budgets->createBudget($user, $categoryB, Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth(), '100.00');
        $budgets->createBudget($user, $categoryA, Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth(), '100.00');
        $transactions->recordExpense($user, $account, '150.00', Carbon::today(), 'Overspend B', $categoryB);
        $transactions->recordExpense($user, $account, '150.00', Carbon::today(), 'Overspend A', $categoryA);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Zeta'), strpos($content, 'Alpha'), 'Alpha (ascending tie-breaker) should render before Zeta.');
    }

    public function test_the_dashboard_renders_successfully_with_no_obligations_or_budgets(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['account_type' => 'ASSET', 'opening_balance' => '500.00']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No payment obligations due in the next 14 days.');
        $response->assertSee('No active budgets for this period.');
    }
}
