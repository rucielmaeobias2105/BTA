<?php

namespace App\Enums;

enum DownPaymentStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case NotRequired = 'not_required';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Awaiting Verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::NotRequired => 'Not Required',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Unverified => 'pending',
            self::Verified => 'confirmed',
            self::Rejected => 'cancelled',
            self::NotRequired => 'gold',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
