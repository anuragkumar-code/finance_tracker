<?php

namespace App\Enums;

/**
 * State of one scheduled instalment or recurring commitment.
 *
 * Spec section 13 is explicit that the app must not silently assume a scheduled
 * payment happened — hence a distinct Scheduled state that only becomes Paid
 * when someone confirms it.
 */
enum ScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Paid = 'paid';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Due',
            self::Paid => 'Paid',
            self::Skipped => 'Skipped',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Scheduled => 'warning',
            self::Paid => 'success',
            self::Skipped => 'secondary',
        };
    }
}
