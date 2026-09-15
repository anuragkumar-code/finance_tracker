{{--
    Settle up with one friend.

    Asks for the final figure rather than trying to reconstruct who paid for
    what: that is how groups actually settle ("you owe me about three
    thousand"), and a calculator the household does not trust would just be
    overridden anyway.
--}}
@php
    $defaultDate = ($event->end_date ?? $event->start_date);
    $defaultDate = $defaultDate->isFuture() ? today() : $defaultDate;

    // Friends who came are listed first, since they are almost always the ones
    // being settled with.
    $participantIds = $event->people->pluck('id')->all();
    $orderedFriends = $friends->sortBy(fn ($f) => in_array($f->id, $participantIds, true) ? 0 : 1)->values();

    $defaultCategory = old('category_id', $event->isTrip() ? $holiday?->id : null);
    $directionValue = old('direction', 'they_owe');
    $settleErrors = $errors->has('amount') || $errors->has('person_id') || $errors->has('direction');
@endphp

<x-ui.dialog id="settle-up" title="Settle up" :description="'Square up '.$event->name.' with one friend at a time.'">
    @if ($friends->isEmpty())
        <div class="space-y-3 px-5 py-4 text-sm text-muted-foreground">
            <p>Settling up is done with friends — people outside the household — and none have been added yet.</p>
            <x-ui.button :href="route('friends.index')" size="sm" icon="user-plus">Add a friend</x-ui.button>
        </div>
    @else
        <form method="POST" action="{{ route('events.settle', $event) }}"
              x-data="{ direction: '{{ $directionValue }}' }"
              @if ($settleErrors) x-init="$nextTick(() => $dispatch('open-dialog', 'settle-up'))" @endif>
            @csrf
            <div class="space-y-4 px-5 py-4">
                <x-ui.select label="With" name="person_id" required>
                    <option value="">Choose a friend…</option>
                    @foreach ($orderedFriends as $friend)
                        <option value="{{ $friend->id }}" @selected(old('person_id') == $friend->id)>
                            {{ $friend->name }}{{ in_array($friend->id, $participantIds, true) ? ' · came on this' : '' }}
                        </option>
                    @endforeach
                </x-ui.select>

                <div>
                    <p class="mb-2 text-sm font-medium">Which way?</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="chip">
                            <input type="radio" name="direction" value="they_owe" x-model="direction">
                            <span class="w-full">
                                <span>They owe me</span>
                                <span class="chip-meta">I paid more than my share</span>
                            </span>
                        </label>
                        <label class="chip">
                            <input type="radio" name="direction" value="we_owe" x-model="direction">
                            <span class="w-full">
                                <span>I owe them</span>
                                <span class="chip-meta">They paid more than my share</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹"
                        :value="old('amount')" required />
                    <x-ui.input label="Date" name="settled_on" type="date"
                        :value="old('settled_on', $defaultDate->toDateString())" required />
                </div>

                <div x-show="direction === 'they_owe'">
                    <x-ui.alert variant="muted" icon="info">
                        Taken out of this trip's spends in proportion to their size, and added to what
                        they owe you. Your bank and card balances do not change.
                    </x-ui.alert>
                </div>

                <div x-show="direction === 'we_owe'" x-cloak class="space-y-3">
                    <x-ui.select label="Count it under" name="category_id"
                        hint="Your share of what they paid is your spending, even though it did not leave your accounts.">
                        <option value="">Uncategorised</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $defaultCategory === (string) $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <x-ui.input label="Note" name="notes" :value="old('notes')" hint="Optional — e.g. how you worked it out" />
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-dialog', 'settle-up')">Cancel</x-ui.button>
                <x-ui.button type="submit">Settle up</x-ui.button>
            </div>
        </form>
    @endif
</x-ui.dialog>
