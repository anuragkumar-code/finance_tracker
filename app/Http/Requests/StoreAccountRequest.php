<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', new Enum(AccountType::class)],
            'institution' => ['nullable', 'string', 'max:100'],
            'owner_id' => ['nullable', 'exists:people,id'],
            // Opening balance is a magnitude: for a credit card it is the amount
            // owed, so it stays positive and is never entered as a negative.
            // Only accounts that keep a balance need an opening one. A bank or
            // cash account has no running balance to open, so these default to
            // zero and today rather than being demanded of the household.
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'opening_balance_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
            'is_set_aside' => ['nullable', 'boolean'],
            'set_aside_reason' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /** Fill in what a balance-free account does not ask for. */
    protected function passedValidation(): void
    {
        $this->merge([
            'opening_balance' => $this->input('opening_balance') ?? '0',
            'opening_balance_date' => $this->input('opening_balance_date') ?? now()->toDateString(),
        ]);
    }

    public function validated($key = null, $default = null): array
    {
        return parent::validated($key, $default) + [
            'opening_balance' => $this->input('opening_balance'),
            'opening_balance_date' => $this->input('opening_balance_date'),
        ];
    }

    public function messages(): array
    {
        return [
            'opening_balance.min' => 'Enter the amount as a positive number. For a credit card, enter what you owe.',
        ];
    }
}
