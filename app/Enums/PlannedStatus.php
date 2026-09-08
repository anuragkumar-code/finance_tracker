<?php

namespace App\Enums;

/**
 * Whether spending was anticipated (spec section 6).
 *
 * High-priority reporting dimension — unplanned spending is a stated concern
 * for this household.
 */
enum PlannedStatus: string
{
    case Planned = 'planned';
    case Unplanned = 'unplanned';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Unplanned => 'Unplanned',
            self::Emergency => 'Emergency',
        };
    }

    /** Bootstrap contextual colour for badges. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Planned => 'success',
            self::Unplanned => 'warning',
            self::Emergency => 'danger',
        };
    }
}
