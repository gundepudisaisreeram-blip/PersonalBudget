<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    /**
     * Fields that become immutable once the account is closed.
     *
     * @var list<string>
     */
    public const FROZEN_WHEN_CLOSED = ['account_type', 'opening_balance', 'opening_balance_date', 'currency'];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('account'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'string', 'in:ASSET,LIABILITY'],
            'subtype' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:255'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_balance_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            /** @var Account $account */
            $account = $this->route('account');

            if ($account->status !== 'CLOSED') {
                return;
            }

            $currentValues = [
                'account_type' => $account->account_type,
                'opening_balance' => $account->opening_balance,
                'opening_balance_date' => $account->opening_balance_date?->toDateString(),
                'currency' => $account->currency,
            ];

            foreach (self::FROZEN_WHEN_CLOSED as $field) {
                if ((string) $this->input($field) !== (string) $currentValues[$field]) {
                    $validator->errors()->add(
                        $field,
                        "The {$field} field cannot be changed once the account is closed."
                    );
                }
            }
        });
    }
}
