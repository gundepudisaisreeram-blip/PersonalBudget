<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\InvalidTransactionException;
use App\Domain\Money;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates REFUND transactions: exactly one INFLOW entry linked to a parent
 * transaction that must be an EXPENSE (BR-014, 09 section 21).
 */
class RefundService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function refund(
        User $user,
        Transaction $parent,
        Account $account,
        string $amount,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?string $reference = null,
        ?string $notes = null,
    ): Transaction {
        $this->ownership->assertTransactionOwnership($parent, $user->id);
        $this->ownership->assertAccountOwnership($account, $user->id);

        if ($parent->transaction_type !== 'EXPENSE') {
            throw new InvalidTransactionException('Refund parent transaction must be an EXPENSE.');
        }

        if (! Money::isPositive($amount)) {
            throw new InvalidTransactionException('Refund amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $user, $parent, $account, $amount, $transactionDate, $description, $reference, $notes,
        ) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_date' => $transactionDate,
                'transaction_type' => 'REFUND',
                'description' => $description,
                'reference' => $reference,
                'source' => 'MANUAL',
                'status' => 'POSTED',
                'category_id' => $parent->category_id,
                'parent_transaction_id' => $parent->id,
                'notes' => $notes,
            ]);

            LedgerEntry::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'account_id' => $account->id,
                'direction' => 'INFLOW',
                'amount' => $amount,
            ]);

            return $transaction->fresh('ledgerEntries');
        });
    }
}
