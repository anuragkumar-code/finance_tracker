@extends('layouts.app')

@section('title', 'Edit '.$event->name)
@section('heading', 'Edit '.$event->name)

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('events.update', $event) }}">
        @csrf
        @method('PUT')
        <x-ui.card>
            @include('events._form')
            <div class="flex gap-2 border-t border-border px-5 py-3.5">
                <x-ui.button type="submit">Save</x-ui.button>
                <x-ui.button :href="route('events.show', $event)" variant="ghost">Cancel</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection
