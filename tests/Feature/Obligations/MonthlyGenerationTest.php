<?php

namespace Tests\Feature\Obligations;

use App\Domain\Services\MonthlyGenerationService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\PaymentObligationService;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Proves the frozen recurring generation grammar and boundary invariant:
 * "After month-end clamping, an occurrence is generated only when its due
 * date is on or after starts_on and, when ends_on exists, on or before
 * ends_on." (Phase 4 Decision Package section 8.)
 */
class MonthlyGenerationTest extends TestCase
{
    use RefreshDatabase;

    private MonthlyGenerationService $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new MonthlyGenerationService(new PaymentObligationService(new OwnershipGuard));
    }

    private function makeTemplate(User $user, array $overrides = []): RecurringPaymentTemplate
    {
        $category = $overrides['category_id'] ?? Category::factory()->for($user)->create()->id;

        return RecurringPaymentTemplate::factory()->for($user)->create(array_merge([
            'category_id' => $category,
            'frequency' => 'MONTHLY',
            'due_rule' => 'DAY:5',
            'starts_on' => '2020-01-01',
            'status' => 'ACTIVE',
        ], $overrides));
    }

    public function test_a_normal_in_range_occurrence_is_generated(): void
    {
        $user = User::factory()->create();
        $template = $this->makeTemplate($user, ['due_rule' => 'DAY:15']);

        $created = $this->generator->generate($user, '2026-03');

        $this->assertCount(1, $created);
        $obligation = $created->first();
        $this->assertSame('2026-03-15', $obligation->due_date->toDateString());
        $this->assertSame($template->category_id, $obligation->category_id);
        $this->assertSame($template->id, $obligation->recurring_payment_template_id);
        $this->assertSame('2026-03', $obligation->occurrence_key);
    }

    public function test_generation_is_idempotent_when_run_twice_for_the_same_period(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:15']);

        $this->generator->generate($user, '2026-03');
        $this->generator->generate($user, '2026-03');

        $this->assertSame(1, PaymentObligation::count());
    }

    public function test_day31_clamps_to_january_31(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31']);

        $obligation = $this->generator->generate($user, '2026-01')->first();

        $this->assertSame('2026-01-31', $obligation->due_date->toDateString());
    }

    public function test_day31_clamps_to_february_28_in_a_non_leap_year(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31']);

        $obligation = $this->generator->generate($user, '2026-02')->first();

        $this->assertSame('2026-02-28', $obligation->due_date->toDateString());
    }

    public function test_day31_clamps_to_february_29_in_a_leap_year(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31']);

        $obligation = $this->generator->generate($user, '2028-02')->first();

        $this->assertSame('2028-02-29', $obligation->due_date->toDateString());
    }

    public function test_day31_clamps_to_april_30(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31']);

        $obligation = $this->generator->generate($user, '2026-04')->first();

        $this->assertSame('2026-04-30', $obligation->due_date->toDateString());
    }

    public function test_day31_clamps_to_june_30(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31']);

        $obligation = $this->generator->generate($user, '2026-06')->first();

        $this->assertSame('2026-06-30', $obligation->due_date->toDateString());
    }

    public function test_a_cancelled_template_is_never_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:15', 'status' => 'CANCELLED']);

        $created = $this->generator->generate($user, '2026-03');

        $this->assertCount(0, $created);
        $this->assertSame(0, PaymentObligation::count());
    }

    // -----------------------------------------------------------------
    // starts_on boundary (lower bound, inclusive)
    // -----------------------------------------------------------------

    public function test_due_date_before_starts_on_is_not_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:5', 'starts_on' => '2026-01-20']);

        $created = $this->generator->generate($user, '2026-01');

        $this->assertCount(0, $created);
        $this->assertSame(0, PaymentObligation::count());
    }

    public function test_due_date_equal_to_starts_on_is_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:20', 'starts_on' => '2026-01-20']);

        $created = $this->generator->generate($user, '2026-01');

        $this->assertCount(1, $created);
        $this->assertSame('2026-01-20', $created->first()->due_date->toDateString());
    }

    public function test_the_first_eligible_occurrence_after_a_suppressed_month_is_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:5', 'starts_on' => '2026-01-20']);

        $this->generator->generate($user, '2026-01');
        $februaryResult = $this->generator->generate($user, '2026-02');

        $this->assertSame(0, PaymentObligation::whereRaw("occurrence_key = '2026-01'")->count());
        $this->assertCount(1, $februaryResult);
        $this->assertSame('2026-02-05', $februaryResult->first()->due_date->toDateString());
    }

    // -----------------------------------------------------------------
    // ends_on boundary (upper bound, inclusive)
    // -----------------------------------------------------------------

    public function test_due_date_after_ends_on_is_not_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:15', 'ends_on' => '2026-06-10']);

        $created = $this->generator->generate($user, '2026-06');

        $this->assertCount(0, $created);
        $this->assertSame(0, PaymentObligation::count());
    }

    public function test_due_date_equal_to_ends_on_is_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:15', 'ends_on' => '2026-06-15']);

        $created = $this->generator->generate($user, '2026-06');

        $this->assertCount(1, $created);
        $this->assertSame('2026-06-15', $created->first()->due_date->toDateString());
    }

    public function test_clamped_day31_due_date_equal_to_ends_on_is_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31', 'ends_on' => '2026-06-30']);

        $created = $this->generator->generate($user, '2026-06');

        $this->assertCount(1, $created);
        $this->assertSame('2026-06-30', $created->first()->due_date->toDateString());
    }

    public function test_clamped_day31_due_date_one_day_after_ends_on_is_not_generated(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31', 'ends_on' => '2026-06-29']);

        $created = $this->generator->generate($user, '2026-06');

        $this->assertCount(0, $created);
        $this->assertSame(0, PaymentObligation::count());
    }

    public function test_re_running_a_suppressed_period_remains_suppressed_and_stable(): void
    {
        $user = User::factory()->create();
        $this->makeTemplate($user, ['due_rule' => 'DAY:31', 'ends_on' => '2026-06-29']);

        $this->generator->generate($user, '2026-06');
        $this->generator->generate($user, '2026-06');

        $this->assertSame(0, PaymentObligation::count());
    }

    // -----------------------------------------------------------------
    // Manual trigger / scheduled command share the same service
    // -----------------------------------------------------------------

    public function test_the_manual_http_trigger_and_console_command_produce_the_same_result(): void
    {
        $userA = User::factory()->create();
        $this->makeTemplate($userA, ['due_rule' => 'DAY:5']);

        $this->actingAs($userA)->post(route('obligations.generate'));
        $countAfterHttp = PaymentObligation::where('user_id', $userA->id)->count();

        // Re-running via the console command must be a no-op for the same
        // period/template -- proving both callers terminate in the same
        // idempotent MonthlyGenerationService/createRecurringOccurrence path.
        Artisan::call('obligations:generate-monthly');
        $countAfterCommand = PaymentObligation::where('user_id', $userA->id)->count();

        $this->assertSame(1, $countAfterHttp);
        $this->assertSame($countAfterHttp, $countAfterCommand);
    }

    public function test_the_console_command_generates_for_every_user_with_active_templates(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->makeTemplate($userA, ['due_rule' => 'DAY:5']);
        $this->makeTemplate($userB, ['due_rule' => 'DAY:10']);

        Artisan::call('obligations:generate-monthly');

        $this->assertSame(1, PaymentObligation::where('user_id', $userA->id)->count());
        $this->assertSame(1, PaymentObligation::where('user_id', $userB->id)->count());
    }
}
