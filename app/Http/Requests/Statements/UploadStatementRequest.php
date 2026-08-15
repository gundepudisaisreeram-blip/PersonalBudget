<?php

namespace App\Http\Requests\Statements;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Extension/MIME are checked here as a first, cheap line of defense
 * (PHASE_7_DECISION_PACKAGE.md section 11) -- StatementFormatDetector's
 * magic-byte sniff inside StatementImportService is the authoritative
 * check; neither is trusted alone.
 */
class UploadStatementRequest extends FormRequest
{
    public const MAX_FILE_KILOBYTES = 5 * 1024;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer'],
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_FILE_KILOBYTES,
                'mimes:csv,xlsx,txt',
            ],
        ];
    }
}
