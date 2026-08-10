<?php

namespace Tests\Feature\Obligations;

use App\Domain\Services\ObligationAllocationService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\TransactionService;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObligationCrudTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactions;

    private ObligationAllocationService $allocations;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = new OwnershipGuard;
        $this->transactions = new TransactionService($guard);
        $this->allocations = new ObligationAllocationService($guard);
    }

    public function test_a_user_can_create_a_one_time_obligation(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('obligations.store'), [
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'is_mandatory' => '1',
        ]);

        $obligation = PaymentObligation::where('category_id', $category->id)->firstOrFail();
        $response->assertRedirect(route('obligations.show', $obligation));
        $this->assertSame($user->id, $obligation->user_id);
        $this->assertSame('PENDING', $obligation->status);
        $this->assertNotNull($obligation->idempotency_key);
        $this->assertNull($obligation->recurring_payment_template_id);
    }

    public function test_creating_a_one_time_obligation_generates_a_server_side_idempotency_key(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user)->post(route('obligations.store'), [
            'idempotency_key' => 'attacker-supplied-key',
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
        ]);

        $obligation = PaymentObligation::where('category_id', $category->id)->firstOrFail();
        $this->assertNotSame('attacker-supplied-key', $obligation->idempotency_key);
    }

    public function test_a_user_can_skip_a_pending_obligation_with_no_allocations(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $obligation = PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-1',
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($user)->patch(route('obligations.skip', $obligation));

        $response->assertRedirect(route('obligations.show', $obligation));
        $this->assertSame('SKIPPED', $obligation->fresh()->status);
    }

    public function test_a_user_can_cancel_a_pending_obligation_with_no_allocations(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $obligation = PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-2',
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($user)->patch(route('obligations.cancel', $obligation));

        $response->assertRedirect(route('obligations.show', $obligation));
        $this->assertSame('CANCELLED', $obligation->fresh()->status);
    }

    public function test_skipping_an_obligation_with_active_allocations_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '5000.00']);
        $category = Category::factory()->for($user)->create();
        $obligation = PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-3',
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);
        $expense = $this->transactions->recordExpense($user, $account, '500.00', '2026-01-05', 'Part payment');
        $this->allocations->allocate($user, $obligation, $expense, '500.00');

        $response = $this->actingAs($user)->patch(route('obligations.skip', $obligation));

        $response->assertRedirect();
        $response->assertSessionHasErrors('obligation');
        $this->assertSame('PARTIALLY_PAID', $obligation->fresh()->status);
    }

    public function test_obligation_detail_shows_allocated_and_remaining_amounts(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '5000.00']);
        $category = Category::factory()->for($user)->create();
        $obligation = PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-4',
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => '1000.00',
            'status' => 'PENDING',
        ]);
        $expense = $this->transactions->recordExpense($user, $account, '400.00', '2026-01-05', 'Part payment');
        $this->allocations->allocate($user, $obligation, $expense, '400.00');

        $response = $this->actingAs($user)->get(route('obligations.show', $obligation));

        $response->assertOk();
        $response->assertSee('400.00');
        $response->assertSee('600.00');
    }
}
