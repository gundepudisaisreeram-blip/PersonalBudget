<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\AllocationException;
use App\Domain\Money;
use App\Models\AuditLog;
use App\Models\ObligationAllocation;
use App\Models\PaymentObligation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bridges actual transactions to planned obligations.
 *
 * Every write locks the obligation row (FOR UPDATE) inside a database
 * transaction, validates the aggregate allocation limit, and recalculates
 * obligation status from the allocation totals (09 sections 4, 8, 24).
 */
class ObligationAllocationService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function allocate(
        User $user,
        PaymentObligation $obligation,
        Transaction $transaction,
        string $amount,
    ): ObligationAllocation {
        $this->ownership->assertPaymentObligationOwnership($obligation, $user->id);
        $this->ownership->assertTransactionOwnership($transaction, $user->id);

        if (! Money::isPositive($amount)) {
            throw new AllocationException('Allocated amount must be greater than zero.');
        }

        if (! $transaction->isEligibleForAllocation()) {
            throw new AllocationException('Only EXPENSE and TRANSFER transactions may fulfill a payment obligation.');
        }

        return DB::transaction(function () use ($user, $obligation, $transaction, $amount) {
            $locked = PaymentObligation::query()->lockForUpdate()->findOrFail($obligation->id);

            if (! $locked->isActive()) {
                throw new AllocationException('Cannot allocate against a SKIPPED or CANCELLED obligation.');
            }

            $currentTotal = $this->allocatedTotal($locked->id);
            $newTotal = Money::add($currentTotal, $amount);

            if (Money::isGreaterThan($newTotal, $locked->planned_amount)) {
                throw new AllocationException('Allocation total cannot exceed the obligation\'s planned amount.');
            }

            $allocation = ObligationAllocation::create([
                'user_id' => $user->id,
                'payment_obligation_id' => $locked->id,
                'transaction_id' => $transaction->id,
                'allocated_amount' => $amount,
            ]);

            $this->recalculateStatus($locked, $newTotal);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'ALLOCATION_CREATED',
                'entity_type' => ObligationAllocation::class,
                'entity_id' => $allocation->id,
                'new_values' => [
                    'payment_obligation_id' => $locked->id,
                    'transaction_id' => $transaction->id,
                    'allocated_amount' => $amount,
                ],
            ]);

            return $allocation;
        });
    }

    public function removeAllocation(User $user, ObligationAllocation $allocation): void
    {
        if ($allocation->user_id !== $user->id) {
            throw new AllocationException('Allocation does not belong to the authenticated user.');
        }

        DB::transaction(function () use ($user, $allocation) {
            $locked = PaymentObligation::query()->lockForUpdate()->findOrFail($allocation->payment_obligation_id);

            $removed = $allocation->allocated_amount;
            $allocation->delete();

            $remainingTotal = $this->allocatedTotal($locked->id);
            $this->recalculateStatus($locked, $remainingTotal);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'ALLOCATION_REMOVED',
                'entity_type' => ObligationAllocation::class,
                'entity_id' => $allocation->id,
                'old_values' => [
                    'payment_obligation_id' => $locked->id,
                    'allocated_amount' => $removed,
                ],
            ]);
        });
    }

    public function skip(User $user, PaymentObligation $obligation): void
    {
        $this->transitionToTerminalState($user, $obligation, 'SKIPPED');
    }

    public function cancel(User $user, PaymentObligation $obligation): void
    {
        $this->transitionToTerminalState($user, $obligation, 'CANCELLED');
    }

    private function transitionToTerminalState(User $user, PaymentObligation $obligation, string $status): void
    {
        $this->ownership->assertPaymentObligationOwnership($obligation, $user->id);

        DB::transaction(function () use ($user, $obligation, $status) {
            $locked = PaymentObligation::query()->lockForUpdate()->findOrFail($obligation->id);

            $total = $this->allocatedTotal($locked->id);

            if (Money::isPositive($total)) {
                throw new AllocationException("Cannot mark an obligation as {$status} while it has active allocations.");
            }

            $previousStatus = $locked->status;
            $locked->update(['status' => $status]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'OBLIGATION_STATUS_CHANGED',
                'entity_type' => PaymentObligation::class,
                'entity_id' => $locked->id,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => $status],
            ]);
        });
    }

    /**
     * Must be called from within the locking transaction so the total
     * reflects the row locked by SELECT ... FOR UPDATE.
     */
    private function allocatedTotal(int $obligationId): string
    {
        $total = DB::table('obligation_allocations')
            ->where('payment_obligation_id', $obligationId)
            ->selectRaw('COALESCE(SUM(allocated_amount), 0) as total')
            ->value('total');

        return (string) $total;
    }

    private function recalculateStatus(PaymentObligation $obligation, string $allocatedTotal): void
    {
        if (! $obligation->isActive()) {
            return;
        }

        $status = match (true) {
            Money::isZero($allocatedTotal) => 'PENDING',
            Money::isGreaterThanOrEqual($allocatedTotal, $obligation->planned_amount) => 'PAID',
            default => 'PARTIALLY_PAID',
        };

        if ($status !== $obligation->status) {
            $previousStatus = $obligation->status;
            $obligation->update(['status' => $status]);

            AuditLog::create([
                'user_id' => $obligation->user_id,
                'action' => 'OBLIGATION_STATUS_CHANGED',
                'entity_type' => PaymentObligation::class,
                'entity_id' => $obligation->id,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => $status],
            ]);
        }
    }
}
