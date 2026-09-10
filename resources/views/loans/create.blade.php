@extends('layouts.app')

@section('title', 'Add loan')
@section('heading', 'Add a loan')
@section('subheading', 'Just the EMI, the tenure and when it started')

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('loans.store') }}">
        @csrf
        <x-ui.card>
            <x-ui.card-content class="space-y-4">
                <x-ui.alert variant="info">
                    No interest rate needed. The EMI and the number of months are all that is
                    required — what you have paid, what is left and every future due date are worked
                    out from those.
                </x-ui.alert>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Loan name" name="name" :value="old('name')"
                        placeholder="Land Loan" required autofocus />
                    <x-ui.input label="Lender" name="lender" :value="old('lender')"
                        placeholder="HDFC" hint="Optional" />
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium">Whose loan</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($owners as $owner)
                            <label class="chip">
                                <input type="radio" name="owner_id" value="{{ $owner->id }}"
                                       @checked(old('owner_id') == $owner->id)>
                                <span>{{ $owner->name }}</span>
                            </label>
                        @endforeach
                        <label class="chip">
                            <input type="radio" name="owner_id" value="" @checked(old('owner_id') === null)>
                            <span>Not set</span>
                        </label>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.input label="Monthly EMI" name="emi_amount" inputmode="decimal" prefix="₹"
                        :value="old('emi_amount')" required />
                    <x-ui.input label="Total months" name="total_months" type="number" min="1" max="600"
                        :value="old('total_months')" id="total_months" required />
                    <x-ui.input label="Deducted on day" name="due_day" type="number" min="1" max="31"
                        :value="old('due_day')" id="due_day" required />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Loan started" name="start_date" type="date" id="start_date"
                        :value="old('start_date')" required />
                    <div class="space-y-1.5">
                        <span class="block text-sm font-medium text-foreground">Ends</span>
                        <div class="flex h-10 items-center rounded-md border border-input bg-muted px-3 text-sm text-muted-foreground"
                             id="endPreview" aria-live="polite">—</div>
                        <p class="text-xs text-muted-foreground">Worked out from the start date and tenure.</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select label="Usually paid from" name="payment_account_id">
                        <option value="">—</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected(old('payment_account_id') == $account->id)>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select label="File EMIs under" name="category_id">
                        <option value="">—</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                {{ $category->full_name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="border-t border-border pt-4">
                    <x-ui.checkbox name="mark_past_as_paid" :checked="old('mark_past_as_paid', true)"
                        label="EMIs before today have already been paid"
                        hint="Marks past instalments as paid so the loan shows the right progress. No bank entries are created for them — those payments happened before you started tracking, and inventing them would throw your balances off." />
                </div>

                <x-ui.textarea label="Notes" name="notes" rows="2" hint="Optional">{{ old('notes') }}</x-ui.textarea>
            </x-ui.card-content>

            <div class="flex gap-2 border-t border-border px-5 py-3.5">
                <x-ui.button type="submit">Save loan</x-ui.button>
                <x-ui.button :href="route('loans.index')" variant="ghost">Cancel</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Show the end date as it is typed, so the tenure can be sanity-checked
    // before anything is saved.
    const start = document.getElementById('start_date');
    const months = document.getElementById('total_months');
    const day = document.getElementById('due_day');
    const out = document.getElementById('endPreview');

    function update() {
        const s = start.value, m = parseInt(months.value, 10), d = parseInt(day.value, 10);
        if (!s || !m || !d) { out.textContent = '—'; return; }

        const startDate = new Date(s + 'T00:00:00');
        let first = new Date(startDate.getFullYear(), startDate.getMonth(), 1);

        const daysThisMonth = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0).getDate();
        if (Math.min(d, daysThisMonth) < startDate.getDate()) {
            first = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 1);
        }

        const last = new Date(first.getFullYear(), first.getMonth() + m - 1, 1);
        const lastDay = Math.min(d, new Date(last.getFullYear(), last.getMonth() + 1, 0).getDate());

        out.textContent = new Date(last.getFullYear(), last.getMonth(), lastDay)
            .toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    [start, months, day].forEach((el) => el.addEventListener('input', update));
    update();
})();
</script>
@endpush
