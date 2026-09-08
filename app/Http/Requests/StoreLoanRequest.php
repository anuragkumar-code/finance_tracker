<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'lender' => ['nullable', 'string', 'max:100'],
            'owner_id' => ['nullable', 'exists:people,id'],
            'emi_amount' => ['required', 'numeric', 'gt:0', 'max:99999999999.99'],
            'total_months' => ['required', 'integer', 'between:1,600'],
            'start_date' => ['required', 'date'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'payment_account_id' => ['nullable', 'exists:accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'mark_past_as_paid' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'emi_amount.gt' => 'Enter the monthly EMI amount.',
            'total_months.between' => 'Tenure must be between 1 and 600 months.',
            'due_day.between' => 'The EMI deduction day must be a day of the month (1–31).',
        ];
    }
}
