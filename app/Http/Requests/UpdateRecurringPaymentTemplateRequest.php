<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurringPaymentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('recurringPaymentTemplate'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', 'string', 'in:MONTHLY'],
            'due_rule' => ['required', 'string', 'regex:/^DAY:([1-9]|[12][0-9]|3[01])$/'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'default_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_mandatory' => ['boolean'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
