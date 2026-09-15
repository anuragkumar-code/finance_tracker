<?php

namespace App\Http\Requests;

use App\Enums\PlannedStatus;
use App\Enums\Purpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreExpenseRequest extends FormRequest
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
            'subcategory_id' => ['nullable', 'exists:categories,id'],
            'payer_id' => ['nullable', 'exists:people,id'],
            'beneficiary_id' => ['nullable', 'exists:people,id'],
            'merchant_id' => ['nullable', 'exists:merchants,id'],
            'event_id' => ['nullable', 'exists:events,id'],

            // Splitting one bill with a friend. Their share has to be less than
            // the bill: if they owe all of it, the household spent nothing.
            'split_person_id' => ['nullable', Rule::exists('people', 'id')->where('is_external', true)],
            'split_amount' => ['nullable', 'required_with:split_person_id', 'numeric', 'gt:0', 'lt:amount'],
            'planned_status' => ['nullable', new Enum(PlannedStatus::class)],
            'purpose' => ['nullable', new Enum(Purpose::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'Enter an amount greater than zero.',
            'account_id.required' => 'Choose which account this was paid from.',
            'split_amount.required_with' => 'Enter how much of this is your friend\'s share.',
            'split_amount.lt' => 'Your friend\'s share has to be less than the whole bill.',
            'split_person_id.exists' => 'Choose a friend to split with.',
        ];
    }
}
