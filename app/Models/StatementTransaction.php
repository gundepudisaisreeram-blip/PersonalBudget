<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id', 'statement_import_id', 'transaction_date', 'value_date', 'description', 'reference',
    'normalized_amount', 'direction', 'statement_balance', 'normalized_hash', 'raw_data',
    'processing_status', 'categorization_status', 'match_status', 'duplicate_status',
    'suggested_category_id', 'confidence_score',
])]
class StatementTransaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'value_date' => 'date',
            'normalized_amount' => 'decimal:2',
            'statement_balance' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(StatementImport::class);
    }

    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    public function reconciliationMatch(): HasOne
    {
        return $this->hasOne(ReconciliationMatch::class);
    }
}
