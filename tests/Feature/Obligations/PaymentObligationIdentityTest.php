<?php

namespace Tests\Feature\Obligations;

use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\PaymentObligationService;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\RecurringPaymentTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentObligationIdentityTest extends TestCase
{
    use RefreshDatabase;

    private PaymentObligationService $obligations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obligations = new PaymentObligationService(new OwnershipGuard);
    }

    public function test_recurring_identity_is_accepted(): void
    {
        $user = User::factory()->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create();

        $obligation = PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => $template->id,
            'occurrence_key' => '2026-01',
            'idempotency_key' => null,
            'category_id' => $template->category_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
            'is_mandatory' => true,
        ]);

        $this->assertTrue($obligation->isRecurring());
        $this->assertFalse($obligation->isOneTime());
    }

    public function test_one_time_identity_is_accepted(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $obligation = PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => null,
            'occurrence_key' => null,
            'idempotency_key' => 'one-time-google-2026-08-11',
            'category_id' => $category->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'due_date' => '2026-08-11',
            'planned_amount' => '1950.00',
            'status' => 'PENDING',
            'is_mandatory' => false,
        ]);

        $this->assertFalse($obligation->isRecurring());
        $this->assertTrue($obligation->isOneTime());
    }

    public function test_identity_check_rejects_both_recurring_and_one_time_identifiers(): void
    {
        $user = User::factory()->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => $template->id,
            'occurrence_key' => '2026-01',
            'idempotency_key' => 'conflicting-key',
            'category_id' => $template->category_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
            'is_mandatory' => true,
        ]);
    }

    public function test_identity_check_rejects_neither_recurring_nor_one_time_identifiers(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->expectException(QueryException::class);

        PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => null,
            'occurrence_key' => null,
            'idempotency_key' => null,
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
            'is_mandatory' => true,
        ]);
    }

    public function test_duplicate_recurring_occurrence_violates_unique_constraint(): void
    {
        $user = User::factory()->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create();

        PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => $template->id,
            'occurrence_key' => '2026-01',
            'category_id' => $template->category_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);

        $this->expectException(QueryException::class);

        PaymentObligation::create([
            'user_id' => $user->id,
            'recurring_payment_template_id' => $template->id,
            'occurrence_key' => '2026-01',
            'category_id' => $template->category_id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);
    }

    public function test_duplicate_idempotency_key_violates_unique_constraint(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'duplicate-key',
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);

        $this->expectException(QueryException::class);

        PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'duplicate-key',
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'planned_amount' => '500.00',
            'status' => 'PENDING',
        ]);
    }

    public function test_recurring_generation_is_idempotent_via_service(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $template = RecurringPaymentTemplate::factory()->for($user)->create([
            'amount' => '65000.00',
            'category_id' => $category->id,
        ]);

        $first = $this->obligations->createRecurringOccurrence(
            $user, $template, '2026-08', '2026-08-01', '2026-08-31', '2026-08-05', '65000.00',
        );

        $second = $this->obligations->createRecurringOccurrence(
            $user, $template, '2026-08', '2026-08-01', '2026-08-31', '2026-08-05', '65000.00',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentObligation::where('recurring_payment_template_id', $template->id)->count());
    }

    public function test_one_time_generation_is_idempotent_via_service(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $first = $this->obligations->createOneTime(
            $user, 'google-subscription-2026-08-11', $category, '2026-08-01', '2026-08-31', '2026-08-11', '1950.00',
        );

        $second = $this->obligations->createOneTime(
            $user, 'google-subscription-2026-08-11', $category, '2026-08-01', '2026-08-31', '2026-08-11', '1950.00',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentObligation::where('idempotency_key', 'google-subscription-2026-08-11')->count());
    }
}
