@extends('layouts.app')

@section('title', 'Edit loan')
@section('heading', 'Edit '.$loan->name)

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('loans.update', $loan) }}">
        @csrf
        @method('PUT')
        <x-ui.card>
            <x-ui.card-content class="space-y-4">
                {{-- EMI and tenure are intentionally not editable: instalments
                     already confirmed are anchored to them. --}}
                <x-ui.alert variant="muted">
                    The EMI ({{ \App\Support\Money::inr($loan->emi_amount) }}) and tenure
                    ({{ $loan->total_months }} months) cannot be changed, because instalments have
                    already been recorded against this schedule. If the loan was restructured, add
                    it as a new loan.
                </x-ui.alert>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Loan name" name="name" :value="old('name', $loan->name)" required />
                    <x-ui.input label="Lender" name="lender" :value="old('lender', $loan->lender)" />
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium">Whose loan</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($owners as $owner)
                            <label class="chip">
                                <input type="radio" name="owner_id" value="{{ $owner->id }}"
                                       @checked(old('owner_id', $loan->owner_id) == $owner->id)>
                                <span>{{ $owner->name }}</span>
                            </label>
                        @endforeach
                        <label class="chip">
                            <input type="radio" name="owner_id" value=""
                                   @checked(old('owner_id', $loan->owner_id) === null)>
                            <span>Not set</span>
                        </label>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select label="Usually paid from" name="payment_account_id">
                        <option value="">—</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}"
                                    @selected(old('payment_account_id', $loan->payment_account_id) == $account->id)>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select label="File EMIs under" name="category_id">
                        <option value="">—</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}"
                                    @selected(old('category_id', $loan->category_id) == $category->id)>
                                {{ $category->full_name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <x-ui.textarea label="Notes" name="notes" rows="2">{{ old('notes', $loan->notes) }}</x-ui.textarea>
            </x-ui.card-content>

            <div class="flex gap-2 border-t border-border px-5 py-3.5">
                <x-ui.button type="submit">Save changes</x-ui.button>
                <x-ui.button :href="route('loans.show', $loan)" variant="ghost">Cancel</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection
