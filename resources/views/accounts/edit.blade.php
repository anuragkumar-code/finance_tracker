@extends('layouts.app')

@section('title', 'Edit account')
@section('heading', 'Edit '.$account->name)

@section('actions')
    <x-ui.button :href="route('accounts.show', $account)" variant="outline">View ledger</x-ui.button>
@endsection

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('accounts.update', $account) }}">
        @csrf
        @method('PUT')
        <x-ui.card>
            <x-ui.card-content>
                @include('accounts._form')
            </x-ui.card-content>
            <div class="flex gap-2 border-t border-border px-5 py-3.5">
                <x-ui.button type="submit">Save changes</x-ui.button>
                <x-ui.button :href="route('accounts.index')" variant="ghost">Cancel</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection
