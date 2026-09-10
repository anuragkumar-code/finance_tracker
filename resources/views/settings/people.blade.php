@extends('layouts.app')

@section('title', 'People')
@section('heading', 'People')
@section('subheading', 'Who pays, and who the money is for')

@section('content')
<div class="grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-7">
        <x-ui.card>
            <x-ui.card-header title="Household" />
            <x-ui.card-content flush>
                @foreach ($people as $person)
                    <div x-data="{ editing: false }"
                         class="border-b border-border last:border-0 {{ $person->is_active ? '' : 'opacity-60' }}">
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-medium">{{ $person->name }}</span>
                                    @if ($person->is_household)
                                        <x-ui.badge variant="secondary">Joint</x-ui.badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ $person->relationship ?: 'No relationship set' }}
                                    · {{ $person->can_be_payer ? 'can pay' : 'cannot pay' }}
                                    · {{ $person->can_be_beneficiary ? 'can benefit' : 'cannot benefit' }}
                                </p>
                            </div>
                            <button type="button" x-on:click="editing = !editing"
                                    class="shrink-0 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    aria-label="Edit {{ $person->name }}">
                                <x-ui.icon name="pencil" class="size-3.5" />
                            </button>
                        </div>

                        <div x-show="editing" x-cloak class="border-t border-border bg-muted/40 px-5 py-4">
                            <form method="POST" action="{{ route('settings.people.update', $person) }}"
                                  class="grid gap-3 sm:grid-cols-2">
                                @csrf @method('PUT')
                                <x-ui.input label="Name" name="name" :value="$person->name" required />
                                <x-ui.input label="Relationship" name="relationship" :value="$person->relationship" />

                                <div class="flex flex-wrap items-end gap-4 sm:col-span-2">
                                    <x-ui.checkbox name="can_be_payer" :checked="$person->can_be_payer" label="Pays" />
                                    <x-ui.checkbox name="can_be_beneficiary" :checked="$person->can_be_beneficiary" label="Benefits" />
                                    <x-ui.checkbox name="is_active" :checked="$person->is_active" label="Active" />
                                </div>

                                <div class="flex gap-2 sm:col-span-2">
                                    <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                    <x-ui.button type="button" variant="ghost" size="sm" x-on:click="editing = false">
                                        Cancel
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </x-ui.card-content>
            <x-ui.card-footer>
                People are referenced by id, so renaming someone carries through every transaction
                already tagged to them.
            </x-ui.card-footer>
        </x-ui.card>
    </div>

    <div class="lg:col-span-5">
        <x-ui.card>
            <x-ui.card-header title="Add a person" />
            <form method="POST" action="{{ route('settings.people.store') }}">
                @csrf
                <x-ui.card-content class="space-y-4">
                    <x-ui.input label="Name" name="name" required />
                    <x-ui.input label="Relationship" name="relationship"
                        placeholder="brother, in-laws…" hint="Optional" />

                    <div class="flex flex-wrap gap-4">
                        <x-ui.checkbox name="can_be_payer" :checked="true" label="Can pay" />
                        <x-ui.checkbox name="can_be_beneficiary" :checked="true" label="Can benefit" />
                    </div>

                    <x-ui.button type="submit" class="w-full">Add person</x-ui.button>
                </x-ui.card-content>
            </form>
        </x-ui.card>
    </div>
</div>
@endsection
