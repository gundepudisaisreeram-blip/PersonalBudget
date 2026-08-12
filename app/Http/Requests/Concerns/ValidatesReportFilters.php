<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Canonical Date-Range Contract (PHASE_6_DECISION_PACKAGE.md section 4.0,
 * Open Decision 4): both dates optional, but the client may not supply
 * exactly one of them; maximum range one year; future report dates are
 * rejected. Shared by every Phase 6 report Form Request so no report
 * invents its own date semantics.
 */
trait ValidatesReportFilters
{
    protected function reportDateRules(): array
    {
        return [
            'start_date' => ['nullable', 'date', 'required_with:end_date'],
            'end_date' => ['nullable', 'date', 'required_with:start_date', 'after_or_equal:start_date'],
        ];
    }

    protected function reportAccountRules(): array
    {
        return [
            'account_id' => ['nullable', 'array'],
            'account_id.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $start = $this->input('start_date');
            $end = $this->input('end_date');

            if ($start === null || $end === null) {
                return;
            }

            $timezone = $this->user()->timezone ?? config('app.timezone');
            $startDate = Carbon::parse($start, $timezone)->startOfDay();
            $endDate = Carbon::parse($end, $timezone)->startOfDay();
            $today = Carbon::now($timezone)->startOfDay();

            if ($endDate->gt($today)) {
                $validator->errors()->add('end_date', 'The report range must not extend beyond today.');
            }

            if ($startDate->diffInDays($endDate) > 365) {
                $validator->errors()->add('end_date', 'The report range must not exceed one year.');
            }
        });
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function dateInputs(): array
    {
        return [$this->input('start_date'), $this->input('end_date')];
    }

    /**
     * @return array<int, int>
     */
    public function accountIdInputs(): array
    {
        return $this->input('account_id', []);
    }
}
