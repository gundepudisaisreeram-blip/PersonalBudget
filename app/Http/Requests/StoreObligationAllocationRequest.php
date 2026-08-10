<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreObligationAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('paymentObligation'));
    }

    /**
     * Eligibility (EXPENSE/TRANSFER only) and the aggregate-limit check are
     * intentionally NOT validated here -- both remain AllocationException
     * from ObligationAllocationService, the single authoritative source for
     * those domain rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->user()->transactions()->find($value) === null) {
                        $fail('The selected transaction is invalid.');
                    }
                },
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
