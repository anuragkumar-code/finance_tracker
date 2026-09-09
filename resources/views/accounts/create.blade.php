@extends('layouts.app')

@section('title', 'Add account')
@section('heading', 'Add an account')
@section('subheading', 'Record where your money sits today')

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('accounts.store') }}">
        @csrf
        <x-ui.card>
            <x-ui.card-content>
                @include('accounts._form', ['account' => null])
            </x-ui.card-content>
            <div class="flex gap-2 border-t border-border px-5 py-3.5">
                <x-ui.button type="submit">Save account</x-ui.button>
                <x-ui.button :href="route('accounts.index')" variant="ghost">Cancel</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection
