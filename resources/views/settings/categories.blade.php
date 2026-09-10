@extends('layouts.app')

@section('title', 'Categories')
@section('heading', 'Categories')
@section('subheading', 'What you group your spending into')

@section('content')
<div class="grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-7">
        <x-ui.card>
            <x-ui.card-header title="All categories" />
            <x-ui.card-content flush>
                @forelse ($categories as $category)
                    <div x-data="{ editing: false }" class="border-b border-border last:border-0">
                        <div class="flex items-start justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-medium {{ $category->is_active ? '' : 'text-muted-foreground line-through' }}">
                                        {{ $category->name }}
                                    </span>
                                    <x-ui.badge variant="secondary">{{ $category->applies_to }}</x-ui.badge>
                                </div>
                                @if ($category->children->isNotEmpty())
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ $category->children->pluck('name')->join(' · ') }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" x-on:click="editing = !editing"
                                        class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                        aria-label="Edit {{ $category->name }}">
                                    <x-ui.icon name="pencil" class="size-3.5" />
                                </button>
                                @if ($category->is_active)
                                    <form method="POST" action="{{ route('settings.categories.destroy', $category) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                aria-label="Hide {{ $category->name }}">
                                            <x-ui.icon name="trash-2" class="size-3.5" />
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div x-show="editing" x-cloak class="border-t border-border bg-muted/40 px-5 py-4">
                            <form method="POST" action="{{ route('settings.categories.update', $category) }}"
                                  class="grid gap-3 sm:grid-cols-2">
                                @csrf @method('PUT')
                                <x-ui.input label="Name" name="name" :value="$category->name" required />
                                <x-ui.select label="Applies to" name="applies_to">
                                    @foreach (['expense', 'income', 'both'] as $applies)
                                        <option value="{{ $applies }}" @selected($category->applies_to === $applies)>
                                            {{ ucfirst($applies) }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                                <div class="flex items-end">
                                    <x-ui.checkbox name="is_active" :checked="$category->is_active" label="Active" />
                                </div>
                                <div class="flex items-end gap-2">
                                    <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                    <x-ui.button type="button" variant="ghost" size="sm" x-on:click="editing = false">
                                        Cancel
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="tags" title="No categories yet" />
                @endforelse
            </x-ui.card-content>
            <x-ui.card-footer>
                Categories are retired rather than deleted, so past transactions keep the label they
                were filed under.
            </x-ui.card-footer>
        </x-ui.card>
    </div>

    <div class="lg:col-span-5">
        <x-ui.card>
            <x-ui.card-header title="Add a category" />
            <form method="POST" action="{{ route('settings.categories.store') }}">
                @csrf
                <x-ui.card-content class="space-y-4">
                    <x-ui.input label="Name" name="name" required />

                    <x-ui.select label="Sits under" name="parent_id" hint="Optional">
                        <option value="">Top level</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select label="Applies to" name="applies_to">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                        <option value="both">Both</option>
                    </x-ui.select>

                    <x-ui.button type="submit" class="w-full">Add category</x-ui.button>
                </x-ui.card-content>
            </form>
        </x-ui.card>
    </div>
</div>
@endsection
