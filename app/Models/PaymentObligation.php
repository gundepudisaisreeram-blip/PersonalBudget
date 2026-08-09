<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'recurring_payment_template_id', 'occurrence_key', 'idempotency_key',
    'category_id', 'planned_account_id', 'period_start', 'period_end', 'due_date',
    'planned_amount', 'status', 'is_mandatory', 'notes',
])]
class PaymentObligation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'planned_amount' => 'decimal:2',
            'is_mandatory' => 'boolean',
        ];
    }

    public function isRecurring(): bool
    {
        return $this->recurring_payment_template_id !== null;
    }

    public function isOneTime(): bool
    {
        return $this->idempotency_key !== null;
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, ['SKIPPED', 'CANCELLED'], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recurringPaymentTemplate(): BelongsTo
    {
        return $this->belongsTo(RecurringPaymentTemplate::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function plannedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'planned_account_id');
    }

    public function obligationAllocations(): HasMany
    {
        return $this->hasMany(ObligationAllocation::class);
    }
}
