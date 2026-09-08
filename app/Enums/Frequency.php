<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum Frequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Every day',
            self::Weekly => 'Every week',
            self::Monthly => 'Every month',
            self::Quarterly => 'Every 3 months',
            self::Yearly => 'Every year',
        };
    }

    /**
     * Advance a date by one interval.
     *
     * Month arithmetic uses the no-overflow variants so a commitment due on the
     * 31st lands on the 30th in a short month rather than skipping into the next.
     */
    public function advance(CarbonInterface $date, int $count = 1): CarbonInterface
    {
        return match ($this) {
            self::Daily => $date->copy()->addDays($count),
            self::Weekly => $date->copy()->addWeeks($count),
            self::Monthly => $date->copy()->addMonthsNoOverflow($count),
            self::Quarterly => $date->copy()->addMonthsNoOverflow(3 * $count),
            self::Yearly => $date->copy()->addYearsNoOverflow($count),
        };
    }
}
