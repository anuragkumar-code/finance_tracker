<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'from_account_id' => ['required', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'to_account_id' => [
                'required',
                'different:from_account_id',
                Rule::exists('accounts', 'id')->whereNull('deleted_at'),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999.99'],
            'payer_id' => ['nullable', 'exists:people,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_account_id.different' => 'A transfer needs two different accounts.',
            'amount.gt' => 'Enter an amount greater than zero.',
        ];
    }
}
