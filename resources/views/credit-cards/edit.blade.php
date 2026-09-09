@extends('layouts.app')

@section('title', 'Edit card')
@section('heading', 'Edit '.$card->card_name)

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('credit-cards.update', $card) }}">
        @csrf
        @method('PUT')
        <x-ui.card>
            <x-ui.card-content>
                @include('credit-cards._form')
            </x-ui.card-content>
            <div class="flex gap-2 border-t border-border px-5 py-3.5">
                <x-ui.button type="submit">Save changes</x-ui.button>
                <x-ui.button :href="route('credit-cards.show', $card)" variant="ghost">Cancel</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection
