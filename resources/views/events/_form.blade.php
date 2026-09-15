{{--
    Create / edit a trip or event.

    Only the name and a start date are needed. Who came is recorded so the trip
    page can offer the right friends when it is time to settle up — it does not
    split anything by itself.
--}}
@php
    $chosenPeople = collect(old('people', $event->exists ? $event->people->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $kindValue = old('kind', $event->kind?->value ?? 'trip');
    $startValue = old('start_date', $event->start_date?->toDateString());
    $endValue = old('end_date', $event->end_date?->toDateString());
@endphp

<x-ui.card-content class="space-y-5">
    <x-ui.input label="Name" name="name" :value="old('name', $event->name)" required
        placeholder="Alleppey with Rahul & Priya" />

    <div>
        <p class="mb-2 text-sm font-medium">What kind?</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($kinds as $kind)
                <label class="chip">
                    <input type="radio" name="kind" value="{{ $kind->value }}" @checked($kindValue === $kind->value)>
                    <span>
                        <span>{{ $kind->label() }}</span>
                        <span class="chip-meta">
                            {{ $kind->value === 'trip' ? 'Time away — spends default to Holiday' : 'An occasion — a wedding, a festival' }}
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.input label="Starts" name="start_date" type="date" :value="$startValue" required />
        <x-ui.input label="Ends" name="end_date" type="date" :value="$endValue"
            hint="Leave blank for a single day" />
    </div>

    <div>
        <p class="text-sm font-medium">Who came</p>
        <p class="mt-0.5 text-xs text-muted-foreground">
            Optional. Friends listed here are offered first when you settle up.
        </p>

        <div class="mt-3 space-y-3">
            @if ($household->isNotEmpty())
                <div>
                    <p class="mb-1.5 text-xs font-medium text-muted-foreground">Household</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($household as $person)
                            <label class="chip">
                                <input type="checkbox" name="people[]" value="{{ $person->id }}"
                                       @checked(in_array($person->id, $chosenPeople, true))>
                                <span>{{ $person->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <p class="mb-1.5 text-xs font-medium text-muted-foreground">Friends</p>
                @if ($friends->isEmpty())
                    <p class="text-sm text-muted-foreground">
                        No friends added yet.
                        <a href="{{ route('friends.index') }}" class="font-medium text-foreground underline underline-offset-2">Add them on the Friends page</a>
                        — you can come back and tick them here.
                    </p>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($friends as $person)
                            <label class="chip">
                                <input type="checkbox" name="people[]" value="{{ $person->id }}"
                                       @checked(in_array($person->id, $chosenPeople, true))>
                                <span>{{ $person->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-ui.textarea label="Notes" name="notes" rows="2" hint="Optional">{{ old('notes', $event->notes) }}</x-ui.textarea>

    @if ($event->exists)
        <x-ui.checkbox name="is_archived" :checked="old('is_archived', $event->is_archived)"
            label="Archived — hide it from Quick Entry" />
    @endif
</x-ui.card-content>
