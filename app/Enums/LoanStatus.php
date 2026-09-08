<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Running',
            self::Closed => 'Closed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'primary',
            self::Closed => 'secondary',
        };
    }
}
