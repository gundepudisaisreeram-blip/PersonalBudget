<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\Concerns\ValidatesReportFilters;
use Illuminate\Foundation\Http\FormRequest;

class ObligationsReportRequest extends FormRequest
{
    use ValidatesReportFilters;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->reportDateRules(), [
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:PENDING,PARTIALLY_PAID,PAID,SKIPPED,CANCELLED,OVERDUE'],
        ]);
    }
}
