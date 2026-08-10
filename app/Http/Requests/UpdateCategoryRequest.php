<?php

namespace App\Http\Requests;

use App\Models\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_type' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ((int) $value === $this->route('category')->id) {
                        $fail('A category cannot be its own parent.');

                        return;
                    }

                    $parent = Category::find($value);

                    if ($parent === null || $parent->user_id !== $this->user()->id) {
                        $fail('The selected parent category is invalid.');
                    }
                },
            ],
        ];
    }
}
