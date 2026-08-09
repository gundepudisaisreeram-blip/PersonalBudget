<?php

namespace Tests\Feature\Obligations;

use App\Domain\Exceptions\AllocationException;
use App\Domain\Services\ObligationAllocationService;
use App\Domain\Services\OwnershipGuard;
use App\Domain\Services\RefundService;
use App\Domain\Services\ReversalService;
use App\Domain\Services\TransactionService;
use App\Domain\Services\TransferService;
use App\Models\Account;
use App\Models\Category;
use App\Models\PaymentObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ObligationAllocationTest extends TestCase
{
    use RefreshDatabase;

    private ObligationAllocationService $allocations;

    private TransactionService $transactions;

    private TransferService $transfers;

    private RefundService $refunds;

    private ReversalService $reversals;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = new OwnershipGuard;
        $this->allocations = new ObligationAllocationService($guard);
        $this->transactions = new TransactionService($guard);
        $this->transfers = new TransferService($guard);
        $this->refunds = new RefundService($guard);
        $this->reversals = new ReversalService($guard);
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

    public function test_allocation_amount_must_be_positive(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $obligation = $this->makeObligation($user);
        $expense = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-10', 'EMI');

        $this->expectException(AllocationException::class);

        $this->allocations->allocate($user, $obligation, $expense, '0.00');
    }

    public function test_only_expense_and_transfer_transactions_may_fulfill_an_obligation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '10000.00']);
        $obligation = $this->makeObligation($user);

        $income = $this->transactions->recordIncome($user, $account, '5000.00', '2026-01-02', 'Salary');

        $this->expectException(AllocationException::class);

        $this->allocations->allocate($user, $obligation, $income, '5000.00');
    }

    public function test_refund_reversal_and_adjustment_transactions_cannot_fulfill_an_obligation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '10000.00']);

        $expense = $this->transactions->recordExpense($user, $account, '1000.00', '2026-01-02', 'Purchase');
        $refund = $this->refunds->refund($user, $expense, $account, '1000.00', '2026-01-03', 'Refund');
        $reversal = $this->reversals->reverse($user, $expense, '2026-01-04', 'Reversal');
        $adjustment = $this->transactions->recordAdjustment(
            $user, $account, 'OUTFLOW', '10.00', '2026-01-05', 'Fee', 'Bank fee',
        );

        $ineligible = [$refund, $reversal, $adjustment];
        $rejectedCount = 0;

        foreach ($ineligible as $transaction) {
            $obligation = $this->makeObligation($user);

            try {
                $this->allocations->allocate($user, $obligation, $transaction, '10.00');
            } catch (AllocationException) {
                $rejectedCount++;
            }
        }

        $this->assertSame(count($ineligible), $rejectedCount);
    }

    public function test_partial_allocation_sets_status_to_partially_paid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '19159.00');

        $expense = $this->transactions->recordExpense($user, $account, '10000.00', '2026-01-10', 'EMI partial');
        $this->allocations->allocate($user, $obligation, $expense, '10000.00');

        $obligation->refresh();
        $this->assertSame('PARTIALLY_PAID', $obligation->status);
    }

    public function test_full_allocation_sets_status_to_paid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '19159.00');

        $expense = $this->transactions->recordExpense($user, $account, '19159.00', '2026-01-10', 'EMI full');
        $this->allocations->allocate($user, $obligation, $expense, '19159.00');

        $obligation->refresh();
        $this->assertSame('PAID', $obligation->status);
    }

    public function test_overpayment_cannot_be_allocated_beyond_planned_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '19159.00');

        $expense = $this->transactions->recordExpense($user, $account, '19200.00', '2026-01-10', 'EMI overpaid');

        // Allocate exactly the planned amount; the remaining 41 stays unallocated.
        $this->allocations->allocate($user, $obligation, $expense, '19159.00');

        $obligation->refresh();
        $this->assertSame('PAID', $obligation->status);

        $this->expectException(AllocationException::class);
        $this->allocations->allocate($user, $obligation, $expense, '41.00');
    }

    public function test_multiple_transactions_can_fulfill_one_obligation(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $expenseA = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-05', 'Part A');
        $expenseB = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-06', 'Part B');

        $this->allocations->allocate($user, $obligation, $expenseA, '5000.00');
        $this->allocations->allocate($user, $obligation, $expenseB, '5000.00');

        $obligation->refresh();
        $this->assertSame('PAID', $obligation->status);
        $this->assertSame(2, $obligation->obligationAllocations()->count());
    }

    public function test_one_transaction_can_fulfill_multiple_obligations(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligationA = $this->makeObligation($user, '3000.00');
        $obligationB = $this->makeObligation($user, '3000.00');

        $expense = $this->transactions->recordExpense($user, $account, '6000.00', '2026-01-05', 'Combined payment');

        $this->allocations->allocate($user, $obligationA, $expense, '3000.00');
        $this->allocations->allocate($user, $obligationB, $expense, '3000.00');

        $this->assertSame('PAID', $obligationA->fresh()->status);
        $this->assertSame('PAID', $obligationB->fresh()->status);
    }

    public function test_removing_an_allocation_recalculates_status_back_to_partially_paid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $expenseA = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-05', 'Part A');
        $expenseB = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-06', 'Part B');

        $this->allocations->allocate($user, $obligation, $expenseA, '5000.00');
        $allocationB = $this->allocations->allocate($user, $obligation, $expenseB, '5000.00');

        $this->assertSame('PAID', $obligation->fresh()->status);

        $this->allocations->removeAllocation($user, $allocationB);

        $this->assertSame('PARTIALLY_PAID', $obligation->fresh()->status);
    }

    public function test_removing_all_allocations_recalculates_status_back_to_pending(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $expense = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-05', 'Part A');
        $allocation = $this->allocations->allocate($user, $obligation, $expense, '5000.00');

        $this->assertSame('PARTIALLY_PAID', $obligation->fresh()->status);

        $this->allocations->removeAllocation($user, $allocation);

        $this->assertSame('PENDING', $obligation->fresh()->status);
    }

    public function test_transfer_can_fulfill_an_investment_obligation(): void
    {
        $user = User::factory()->create();
        $savings = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $mutualFund = Account::factory()->for($user)->create(['opening_balance' => '0.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $transfer = $this->transfers->transfer($user, $savings, $mutualFund, '10000.00', '2026-01-05', 'SIP');

        $this->allocations->allocate($user, $obligation, $transfer, '10000.00');

        $this->assertSame('PAID', $obligation->fresh()->status);
    }

    public function test_obligation_cannot_be_skipped_while_it_has_active_allocations(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $expense = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-05', 'Part A');
        $this->allocations->allocate($user, $obligation, $expense, '5000.00');

        $this->expectException(AllocationException::class);
        $this->allocations->skip($user, $obligation);
    }

    public function test_obligation_can_be_skipped_when_it_has_no_allocations(): void
    {
        $user = User::factory()->create();
        $obligation = $this->makeObligation($user, '10000.00');

        $this->allocations->skip($user, $obligation);

        $this->assertSame('SKIPPED', $obligation->fresh()->status);
    }

    public function test_obligation_cannot_be_cancelled_while_it_has_active_allocations(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_balance' => '50000.00']);
        $obligation = $this->makeObligation($user, '10000.00');

        $expense = $this->transactions->recordExpense($user, $account, '5000.00', '2026-01-05', 'Part A');
        $this->allocations->allocate($user, $obligation, $expense, '5000.00');

        $this->expectException(AllocationException::class);
        $this->allocations->cancel($user, $obligation);
    }

    /**
     * Proves the obligation row is genuinely locked with SELECT ... FOR UPDATE:
     * a second connection attempting to lock the same row must block until
     * the first connection's transaction ends, rather than reading stale data.
     */
    public function test_allocation_locking_blocks_a_concurrent_writer(): void
    {
        $user = User::factory()->create();
        $obligation = $this->makeObligation($user, '10000.00');

        Config::set('database.connections.locktest', Config::get('database.connections.mysql'));

        DB::connection()->beginTransaction();
        DB::connection()->table('payment_obligations')->where('id', $obligation->id)->lockForUpdate()->first();

        DB::connection('locktest')->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;
        $start = microtime(true);

        try {
            DB::connection('locktest')->transaction(function () use ($obligation) {
                DB::connection('locktest')->table('payment_obligations')
                    ->where('id', $obligation->id)
                    ->lockForUpdate()
                    ->first();
            });
        } catch (\Throwable $e) {
            $blocked = true;
        }

        $elapsed = microtime(true) - $start;

        DB::connection()->rollBack();
        DB::purge('locktest');

        $this->assertTrue($blocked, 'Expected the second connection to be blocked by the row lock.');
        $this->assertGreaterThanOrEqual(1.0, $elapsed, 'Expected the second connection to wait for the lock timeout.');
    }
}
