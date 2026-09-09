@props([
    'amount' => 0,
    'compact' => false,
    'tone' => null,
    'signed' => false,
])

{{--
    The one place money gets rendered (spec section 44).

    Indian grouping, tabular figures so columns line up down a table, and
    semantic colour only where it carries meaning. Colour is never the only
    signal — the sign and the surrounding label say it too, which keeps the
    figures readable for anyone who cannot separate red from green.
--}}
@php
    $raw = (string) ($amount ?? '0');
    $raw = $raw === '' ? '0' : $raw;

    $comparison = bccomp($raw, '0', 2);
    $negative = $comparison === -1;

    $text = $compact
        ? \App\Support\Money::compact($raw)
        : \App\Support\Money::inr($raw);

    if ($signed && $comparison === 1) {
        $text = '+'.$text;
    }

    $tones = [
        'income' => 'text-income',
        'expense' => 'text-expense',
        'debt' => 'text-debt',
        'muted' => 'text-muted-foreground',
        'strong' => 'font-medium text-foreground',
    ];

    // A negative figure reads as money out unless the caller says otherwise.
    $resolved = $tone ?? ($negative ? 'expense' : null);
@endphp

<span {{ $attributes->merge(['class' => 'tabular whitespace-nowrap '.($tones[$resolved] ?? '')]) }}>{{ $text }}</span>
