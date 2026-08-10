<?php

namespace Tests\Feature\Obligations;

use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\TransactionService;
use App\Models\Account;
use App\Models\Category;
use App\Models\ObligationAllocation;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObligationAllocationHttpTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactions = new TransactionService(new OwnershipGuard);
    }

    private function makeObligation(User $user, string $plannedAmount = '19159.00'): PaymentObligation
    {
        $category = Category::factory()->for($user)->create();

        return PaymentObligation::create([
            'user_id' => $user->id,
            'idempotency_key' => 'obligation-'.uniqid(),
            'category_id' => $category->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-15',
            'planned_amount' => $plannedAmount,
            'status' => 'PENDING',
        ]);
    }

    public function test_a_user_can_link_an_eligible_transaction_and_fully_pay_an_obligation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '1000.00');
        $expense = $this->transactions->recordExpense($user, $account, '1000.00', '2026-01-05', 'Payment');

        $response = $this->actingAs($user)->post(route('obligations.allocations.store', $obligation), [
            'transaction_id' => $expense->id,
            'amount' => '1000.00',
        ]);

        $response->assertRedirect(route('obligations.show', $obligation));
        $this->assertSame('PAID', $obligation->fresh()->status);
    }

    public function test_the_exact_br020_overpayment_scenario_through_http(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '19159.00');
        $expense = $this->transactions->recordExpense($user, $account, '19200.00', '2026-01-05', 'Rent payment');

        $response = $this->actingAs($user)->post(route('obligations.allocations.store', $obligation), [
            'transaction_id' => $expense->id,
            'amount' => '19159.00',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('PAID', $obligation->fresh()->status);

        $allocation = ObligationAllocation::where('payment_obligation_id', $obligation->id)->firstOrFail();
        $this->assertSame('19159.00', $allocation->allocated_amount);

        // Attempting to allocate the remaining 41 pushes the total over planned_amount.
        $overpay = $this->actingAs($user)->post(route('obligations.allocations.store', $obligation), [
            'transaction_id' => $expense->id,
            'amount' => '41.00',
        ]);
        $overpay->assertSessionHasErrors('obligation');
    }

    public function test_allocating_an_income_transaction_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user);
        $income = $this->transactions->recordIncome($user, $account, '19159.00', '2026-01-05', 'Salary');
        $allocationCount = ObligationAllocation::count();

        $response = $this->actingAs($user)->post(route('obligations.allocations.store', $obligation), [
            'transaction_id' => $income->id,
            'amount' => '19159.00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('obligation');
        $this->assertSame($allocationCount, ObligationAllocation::count());
        $this->assertSame('PENDING', $obligation->fresh()->status);
    }

    public function test_allocating_beyond_the_planned_amount_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '1000.00');
        $expense = $this->transactions->recordExpense($user, $account, '2000.00', '2026-01-05', 'Payment');

        $response = $this->actingAs($user)->post(route('obligations.allocations.store', $obligation), [
            'transaction_id' => $expense->id,
            'amount' => '2000.00',
        ]);

        $response->assertSessionHasErrors('obligation');
        $this->assertSame('PENDING', $obligation->fresh()->status);
    }

    public function test_allocating_against_a_skipped_obligation_redirects_back_with_input_preserved(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '1000.00');
        $obligation->update(['status' => 'SKIPPED']);
        $expense = $this->transactions->recordExpense($user, $account, '1000.00', '2026-01-05', 'Payment');

        $response = $this->actingAs($user)->post(route('obligations.allocations.store', $obligation), [
            'transaction_id' => $expense->id,
            'amount' => '1000.00',
        ]);

        $response->assertSessionHasErrors('obligation');
    }

    public function test_a_cross_tenant_transaction_id_is_a_validation_error_not_a_403(): void
    {
        $attacker = User::factory()->create();
        $owner = User::factory()->create();
        $ownerAccount = Account::factory()->for($owner)->create();
        $ownerExpense = $this->transactions->recordExpense($owner, $ownerAccount, '100.00', '2026-01-05', 'Owner expense');
        $obligation = $this->makeObligation($attacker, '1000.00');

        $response = $this->actingAs($attacker)->post(route('obligations.allocations.store', $obligation), [
            'transaction_id' => $ownerExpense->id,
            'amount' => '100.00',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('transaction_id');
        $this->assertDatabaseCount('obligation_allocations', 0);
    }

    public function test_a_user_can_remove_their_own_allocation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '1000.00');
        $expense = $this->transactions->recordExpense($user, $account, '1000.00', '2026-01-05', 'Payment');
        $this->actingAs($user)->post(route('obligations.allocations.store', $obligation), [
            'transaction_id' => $expense->id,
            'amount' => '1000.00',
        ]);
        $allocation = ObligationAllocation::where('payment_obligation_id', $obligation->id)->firstOrFail();

        $response = $this->actingAs($user)->delete(route('obligations.allocations.destroy', [$obligation, $allocation]));

        $response->assertRedirect(route('obligations.show', $obligation));
        $this->assertDatabaseMissing('obligation_allocations', ['id' => $allocation->id]);
        $this->assertSame('PENDING', $obligation->fresh()->status);
    }

    public function test_a_user_cannot_remove_another_users_allocation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $account = Account::factory()->for($owner)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($owner, '1000.00');
        $expense = $this->transactions->recordExpense($owner, $account, '1000.00', '2026-01-05', 'Payment');
        $this->actingAs($owner)->post(route('obligations.allocations.store', $obligation), [
            'transaction_id' => $expense->id,
            'amount' => '1000.00',
        ]);
        $allocation = ObligationAllocation::where('payment_obligation_id', $obligation->id)->firstOrFail();

        $this->actingAs($attacker)->delete(route('obligations.allocations.destroy', [$obligation, $allocation]))->assertForbidden();

        $this->assertDatabaseHas('obligation_allocations', ['id' => $allocation->id]);
    }
}
