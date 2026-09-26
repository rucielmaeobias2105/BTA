<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Tailwind badge modifier used by <x-ui.badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Confirmed => 'confirmed',
            self::InProgress => 'in_progress',
            self::Completed => 'completed',
            self::Cancelled => 'cancelled',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Customer-selectable status filter options (no input fields, action buttons only).
     *
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        return [
            'all' => 'All',
            self::Pending->value => 'Pending',
            self::Confirmed->value => 'Confirmed',
            self::Completed->value => 'Completed',
            self::Cancelled->value => 'Cancelled',
        ];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** Statuses that a customer may still act on (cancel / reschedule / rate). */
    public function isCustomerActionable(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }
}
