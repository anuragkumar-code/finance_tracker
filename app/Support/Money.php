<?php

namespace App\Support;

/**
 * Money formatting for display only.
 *
 * Never use these values for arithmetic — balances are computed with bcmath in
 * AccountBalanceService and only formatted here on the way to a Blade view.
 */
class Money
{
    /**
     * Format in the Indian grouping convention: 1,35,000.00 rather than
     * 135,000.00 — the household reads amounts the way their bank shows them.
     */
    public static function inr(string|float|int|null $amount, bool $symbol = true, bool $decimals = true): string
    {
        $amount = (string) ($amount ?? '0');
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');

        $parts = explode('.', $amount);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $fraction = str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');

        $grouped = self::groupIndian($whole);

        $out = $symbol ? '₹'.$grouped : $grouped;

        if ($decimals) {
            $out .= '.'.$fraction;
        }

        return $negative ? '-'.$out : $out;
    }

    /** Compact form for dense tables and chart labels: ₹1.35L, ₹2.4Cr. */
    public static function compact(string|float|int|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        $negative = $value < 0;
        $value = abs($value);

        $formatted = match (true) {
            $value >= 10000000 => round($value / 10000000, 2).'Cr',
            $value >= 100000 => round($value / 100000, 2).'L',
            $value >= 1000 => round($value / 1000, 1).'K',
            default => (string) round($value),
        };

        return ($negative ? '-₹' : '₹').$formatted;
    }

    /** Last three digits, then pairs: 12345678 -> 1,23,45,678 */
    private static function groupIndian(string $whole): string
    {
        if (strlen($whole) <= 3) {
            return $whole;
        }

        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);

        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);

        return $rest.','.$last3;
    }
}
