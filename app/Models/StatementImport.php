<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'account_id', 'bank', 'original_filename', 'storage_path', 'file_hash', 'file_type',
    'period_from', 'period_to', 'opening_balance', 'closing_balance', 'transaction_count',
    'status', 'parser_version', 'error_message',
])]
class StatementImport extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function statementTransactions(): HasMany
    {
        return $this->hasMany(StatementTransaction::class);
    }

    public function accountReconciliations(): HasMany
    {
        return $this->hasMany(AccountReconciliation::class);
    }
}
