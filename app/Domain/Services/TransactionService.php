<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\InvalidTransactionException;
use App\Domain\Money;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates single-ledger-entry actual transactions: EXPENSE, INCOME, ADJUSTMENT.
 *
 * A Transaction and its Ledger Entries are always created atomically, so a
 * transaction never becomes visible without its required ledger footprint.
 */
class TransactionService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function recordExpense(
        User $user,
        Account $account,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?Category $category = null,
        ?string $reference = null,
        string $source = 'MANUAL',
        ?string $notes = null,
    ): Transaction {
        return $this->createSingleEntryTransaction(
            $user, $account, 'EXPENSE', 'OUTFLOW', $amount, $transactionDate,
            $description, $category, $reference, $source, $notes,
        );
    }

    public function recordIncome(
        User $user,
        Account $account,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?Category $category = null,
        ?string $reference = null,
        string $source = 'MANUAL',
        ?string $notes = null,
    ): Transaction {
        return $this->createSingleEntryTransaction(
            $user, $account, 'INCOME', 'INFLOW', $amount, $transactionDate,
            $description, $category, $reference, $source, $notes,
        );
    }

    /**
     * Adjustments require an explicit reason for auditability (BR-016).
     */
    public function recordAdjustment(
        User $user,
        Account $account,
        string $direction,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        string $reason,
        ?Category $category = null,
        ?string $reference = null,
    ): Transaction {
        if (! in_array($direction, ['INFLOW', 'OUTFLOW'], true)) {
            throw new InvalidTransactionException('Adjustment direction must be INFLOW or OUTFLOW.');
        }

        $transaction = $this->createSingleEntryTransaction(
            $user, $account, 'ADJUSTMENT', $direction, $amount, $transactionDate,
            $description, $category, $reference, 'ADJUSTMENT', $reason,
        );

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'ADJUSTMENT_CREATED',
            'entity_type' => Transaction::class,
            'entity_id' => $transaction->id,
            'new_values' => [
                'account_id' => $account->id,
                'direction' => $direction,
                'amount' => $amount,
            ],
            'metadata' => ['reason' => $reason],
        ]);

        return $transaction;
    }

    private function createSingleEntryTransaction(
        User $user,
        Account $account,
        string $transactionType,
        string $direction,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?Category $category,
        ?string $reference,
        string $source,
        ?string $notes,
    ): Transaction {
        $this->ownership->assertAccountOwnership($account, $user->id);
        $this->ownership->assertCategoryOwnership($category, $user->id);

        if (! Money::isPositive($amount)) {
            throw new InvalidTransactionException('Transaction amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $user, $account, $transactionType, $direction, $amount,
            $transactionDate, $description, $category, $reference, $source, $notes,
        ) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_date' => $transactionDate,
                'transaction_type' => $transactionType,
                'description' => $description,
                'reference' => $reference,
                'source' => $source,
                'status' => 'POSTED',
                'category_id' => $category?->id,
                'notes' => $notes,
            ]);

            LedgerEntry::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'account_id' => $account->id,
                'direction' => $direction,
                'amount' => $amount,
            ]);

            return $transaction->fresh('ledgerEntries');
        });
    }
}
