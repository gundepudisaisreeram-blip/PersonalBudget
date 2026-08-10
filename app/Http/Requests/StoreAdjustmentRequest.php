<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'direction' => ['required', 'string', 'in:INFLOW,OUTFLOW'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
