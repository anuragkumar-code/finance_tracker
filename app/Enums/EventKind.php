<?php

namespace App\Enums;

/**
 * A trip is time away (Alleppey, Goa); an event is an occasion at home (a
 * wedding, Diwali, a house-warming). They are tracked identically — the kind
 * only decides the icon and whether entries default to the Holiday category.
 */
enum EventKind: string
{
    case Trip = 'trip';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::Trip => 'Trip',
            self::Event => 'Event',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Trip => 'plane',
            self::Event => 'calendar-days',
        };
    }
}
