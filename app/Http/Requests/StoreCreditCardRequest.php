<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreditCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_name' => ['required', 'string', 'max:100'],
            'institution' => ['nullable', 'string', 'max:100'],
            'owner_id' => ['nullable', 'exists:people,id'],
            'credit_limit' => ['required', 'numeric', 'gt:0', 'max:99999999999.99'],
            'statement_day' => ['required', 'integer', 'between:1,31'],
            'payment_due_day' => ['required', 'integer', 'between:1,31'],
            'annual_fee' => ['nullable', 'numeric', 'min:0'],
            // What is owed today, entered as a positive number.
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:99999999999.99'],
            'opening_balance_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'opening_balance.min' => 'Enter what you currently owe as a positive number.',
            'credit_limit.gt' => 'A card needs a credit limit above zero.',
            'statement_day.between' => 'Statement day must be a day of the month (1–31).',
            'payment_due_day.between' => 'Due day must be a day of the month (1–31).',
        ];
    }
}
