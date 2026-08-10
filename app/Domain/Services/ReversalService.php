<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\InvalidTransactionException;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates REVERSAL transactions: an exact mirror of the parent transaction's
 * complete ledger structure, same accounts and amounts, every direction
 * inverted (BR-015, 09 section 21).
 */
class ReversalService
{
    public function __construct(private readonly OwnershipGuard $ownership) {}

    public function reverse(
        User $user,
        Transaction $parent,
        DateTimeInterface|string $transactionDate,
        string $description,
        ?string $reference = null,
        ?string $notes = null,
    ): Transaction {
        $this->ownership->assertTransactionOwnership($parent, $user->id);

        $parentEntries = $parent->ledgerEntries()->get();

        if ($parentEntries->isEmpty()) {
            throw new InvalidTransactionException('Parent transaction has no ledger entries to reverse.');
        }

        $inheritedAccounts = Account::query()
            ->whereIn('id', $parentEntries->pluck('account_id')->unique())
            ->get();

        foreach ($inheritedAccounts as $inheritedAccount) {
            if (! $inheritedAccount->isActive()) {
                throw new InvalidTransactionException(
                    'A reversal cannot be created because one of the parent transaction\'s accounts is closed.'
                );
            }
        }

        return DB::transaction(function () use (
            $user, $parent, $parentEntries, $transactionDate, $description, $reference, $notes,
        ) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_date' => $transactionDate,
                'transaction_type' => 'REVERSAL',
                'description' => $description,
                'reference' => $reference,
                'source' => 'MANUAL',
                'status' => 'POSTED',
                'category_id' => $parent->category_id,
                'parent_transaction_id' => $parent->id,
                'notes' => $notes,
            ]);

            foreach ($parentEntries as $entry) {
                LedgerEntry::create([
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'account_id' => $entry->account_id,
                    'direction' => $entry->direction === 'INFLOW' ? 'OUTFLOW' : 'INFLOW',
                    'amount' => $entry->amount,
                ]);
            }

            return $transaction->fresh('ledgerEntries');
        });
    }
}
