<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\Concerns\ValidatesReportFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Shared by Monthly Summaries and Month Review (section 4.5/4.6): both are
 * a single calendar month pinned view, not a free date range. `month`
 * defaults to the current month; future months are rejected, mirroring the
 * canonical date-range contract's "no future report dates" rule (Open
 * Decision 4).
 */
class MonthlyPeriodReportRequest extends FormRequest
{
    use ValidatesReportFilters;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->reportAccountRules(), [
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $month = $this->input('month');

            if ($month === null) {
                return;
            }

            $timezone = $this->user()->timezone ?? config('app.timezone');
            $requested = Carbon::createFromFormat('Y-m-d', $month.'-01', $timezone)->startOfMonth();
            $currentMonth = Carbon::now($timezone)->startOfMonth();

            if ($requested->gt($currentMonth)) {
                $validator->errors()->add('month', 'The selected month must not be in the future.');
            }
        });
    }

    public function resolveMonth(): string
    {
        $timezone = $this->user()->timezone ?? config('app.timezone');

        return $this->input('month') ?? Carbon::now($timezone)->format('Y-m');
    }
}
