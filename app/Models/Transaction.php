<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'transaction_date', 'transaction_type', 'description', 'reference',
    'source', 'status', 'category_id', 'parent_transaction_id', 'notes',
])]
class Transaction extends Model
{
    use HasFactory;

    public const TYPES_ELIGIBLE_FOR_ALLOCATION = ['EXPENSE', 'TRANSFER'];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
        ];
    }

    public function isExpense(): bool
    {
        return $this->transaction_type === 'EXPENSE';
    }

    public function isEligibleForAllocation(): bool
    {
        return in_array($this->transaction_type, self::TYPES_ELIGIBLE_FOR_ALLOCATION, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'parent_transaction_id');
    }

    public function childTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'parent_transaction_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function obligationAllocations(): HasMany
    {
        return $this->hasMany(ObligationAllocation::class);
    }

    public function reconciliationMatches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }
}
