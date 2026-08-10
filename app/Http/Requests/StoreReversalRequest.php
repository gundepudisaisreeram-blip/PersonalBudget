<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Whether the parent has ledger entries is intentionally NOT checked
     * here -- that is a domain-level rule (ReversalService throws
     * InvalidTransactionException), not a Form Request validation rule. This
     * rule only resolves parent_transaction_id through the authenticated
     * user's own transactions (Phase 3 Decision Package, row 2).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_transaction_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->user()->transactions()->find($value) === null) {
                        $fail('The selected parent transaction is invalid.');
                    }
                },
            ],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
