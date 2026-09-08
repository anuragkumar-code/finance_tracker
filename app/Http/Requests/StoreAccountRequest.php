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
            // Opening balance is a magnitude: for a credit card it is the amount
            // owed, so it stays positive and is never entered as a negative.
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:99999999999.99'],
            'opening_balance_date' => ['required', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'opening_balance.min' => 'Enter the amount as a positive number. For a credit card, enter what you owe.',
        ];
    }
}
