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

    /**
     * The heading the modal shows for this category.
     *
     * Spelled out rather than composed from `label()`, because the booking one
     * is not "Booking Terms" in the dialog — the dialog's whole subject is the
     * general terms, and a heading reading "Booking Terms & Conditions" over a
     * list of confirmation, arrival and pricing rules understates what the
     * customer is agreeing to. This is the wording MCA Café uses for the same
     * policy.
     */
    public function modalTitle(): string
    {
        return match ($this) {
            self::Booking => 'General Terms & Conditions',
            self::Cancellation => 'Cancellation Policy',
            self::Rescheduling => 'Rescheduling Policy',
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
