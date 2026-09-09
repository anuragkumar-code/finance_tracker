@extends('layouts.app')

@section('title', 'Add card')
@section('heading', 'Add a credit card')
@section('subheading', 'Its limit, billing cycle, and what you owe today')

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('credit-cards.store') }}">
        @csrf
        <x-ui.card>
            <x-ui.card-content>
                @include('credit-cards._form', ['card' => null])
            </x-ui.card-content>
            <div class="flex gap-2 border-t border-border px-5 py-3.5">
                <x-ui.button type="submit">Save card</x-ui.button>
                <x-ui.button :href="route('credit-cards.index')" variant="ghost">Cancel</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection
