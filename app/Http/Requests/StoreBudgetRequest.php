<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The no-overlapping-period rule is intentionally NOT validated here --
     * it requires row-locking for concurrency safety, which a Form Request
     * cannot provide. It remains BudgetException from BudgetService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'budget_amount' => ['required', 'numeric', 'min:0'],
            'is_mandatory_reserve' => ['boolean'],
        ];
    }
}
