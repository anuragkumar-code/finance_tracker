<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'account_id' => ['required', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999.99'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'payer_id' => ['nullable', 'exists:people,id'],
            'beneficiary_id' => ['nullable', 'exists:people,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'Enter an amount greater than zero.',
            'account_id.required' => 'Choose which account received this money.',
        ];
    }
}
