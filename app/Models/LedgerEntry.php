<?php

namespace App\Models;

use App\Domain\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'transaction_id', 'account_id', 'direction', 'amount'])]
class LedgerEntry extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            throw new ImmutableRecordException('A posted ledger entry cannot be modified. Use a Refund, Reversal, or Adjustment instead.');
        });

        static::deleting(function (self $entry) {
            throw new ImmutableRecordException('A posted ledger entry cannot be deleted. Use a Refund, Reversal, or Adjustment instead.');
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function isInflow(): bool
    {
        return $this->direction === 'INFLOW';
    }

    public function isOutflow(): bool
    {
        return $this->direction === 'OUTFLOW';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
