<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999.99'],
            // Bills are paid from money you hold, never from another card.
            'source_account_id' => [
                'required',
                Rule::exists('accounts', 'id')
                    ->where('normal_balance', 'asset')
                    ->whereNull('deleted_at'),
            ],
            'statement_id' => ['nullable', 'exists:credit_card_statements,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_account_id.exists' => 'Choose a bank or cash account to pay from.',
            'amount.gt' => 'Enter an amount greater than zero.',
        ];
    }
}
