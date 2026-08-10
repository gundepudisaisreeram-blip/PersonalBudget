<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\InvalidTransactionException;
use App\Domain\Money;
use App\Models\Account;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates TRANSFER transactions: exactly one OUTFLOW and one INFLOW entry of
 * the same amount against two distinct user-owned accounts. Transfers are
 * never income or expense (BR-013).
 */
class TransferService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function transfer(
        User $user,
        Account $fromAccount,
        Account $toAccount,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?Category $category = null,
        ?string $reference = null,
        string $source = 'MANUAL',
        ?string $notes = null,
    ): Transaction {
        $this->ownership->assertAccountOwnership($fromAccount, $user->id);
        $this->ownership->assertAccountOwnership($toAccount, $user->id);
        $this->ownership->assertCategoryOwnership($category, $user->id);

        if (! $fromAccount->isActive()) {
            throw new InvalidTransactionException('The source account is closed and cannot accept new transactions.');
        }

        if (! $toAccount->isActive()) {
            throw new InvalidTransactionException('The destination account is closed and cannot accept new transactions.');
        }

        if ($fromAccount->id === $toAccount->id) {
            throw new InvalidTransactionException('A transfer requires two distinct accounts.');
        }

        if (! Money::isPositive($amount)) {
            throw new InvalidTransactionException('Transfer amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $user, $fromAccount, $toAccount, $amount, $transactionDate,
            $description, $category, $reference, $source, $notes,
        ) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_date' => $transactionDate,
                'transaction_type' => 'TRANSFER',
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
                'account_id' => $fromAccount->id,
                'direction' => 'OUTFLOW',
                'amount' => $amount,
            ]);

            LedgerEntry::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'account_id' => $toAccount->id,
                'direction' => 'INFLOW',
                'amount' => $amount,
            ]);

            return $transaction->fresh('ledgerEntries');
        });
    }
}
