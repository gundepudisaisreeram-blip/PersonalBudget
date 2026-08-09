<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'name', 'institution', 'account_type', 'subtype', 'currency',
    'opening_balance', 'opening_balance_date', 'status', 'notes',
])]
class Account extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
        ];
    }

    public function isAsset(): bool
    {
        return $this->account_type === 'ASSET';
    }

    public function isLiability(): bool
    {
        return $this->account_type === 'LIABILITY';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function statementImports(): HasMany
    {
        return $this->hasMany(StatementImport::class);
    }

    public function accountReconciliations(): HasMany
    {
        return $this->hasMany(AccountReconciliation::class);
    }
}
