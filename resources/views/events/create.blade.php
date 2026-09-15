@extends('layouts.app')

@section('title', 'New trip or event')
@section('heading', 'New trip or event')
@section('subheading', 'See what it cost as one figure, and square up with friends afterwards')

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('events.store') }}">
        @csrf
        <x-ui.card>
            @include('events._form')
            <div class="flex gap-2 border-t border-border px-5 py-3.5">
                <x-ui.button type="submit">Create</x-ui.button>
                <x-ui.button :href="route('events.index')" variant="ghost">Cancel</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection
