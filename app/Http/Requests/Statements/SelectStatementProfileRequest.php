<?php

namespace App\Http\Requests\Statements;

use Illuminate\Foundation\Http\FormRequest;

class SelectStatementProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parser_profile' => ['required', 'string'],
        ];
    }
}
