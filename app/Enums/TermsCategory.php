<?php

namespace App\Enums;

enum TermsCategory: string
{
    case Booking = 'booking';
    case Cancellation = 'cancellation';
    case Rescheduling = 'rescheduling';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Booking',
            self::Cancellation => 'Cancellation',
            self::Rescheduling => 'Rescheduling',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
