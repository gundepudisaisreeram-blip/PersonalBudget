<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'account_id', 'statement_import_id', 'reconciliation_date', 'ledger_balance',
    'statement_balance', 'difference', 'status', 'resolution_note', 'adjustment_transaction_id',
])]
class AccountReconciliation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reconciliation_date' => 'date',
            'ledger_balance' => 'decimal:2',
            'statement_balance' => 'decimal:2',
            'difference' => 'decimal:2',
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

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(StatementImport::class);
    }

    public function adjustmentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'adjustment_transaction_id');
    }
}
