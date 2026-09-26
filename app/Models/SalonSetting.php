<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Single-row operating configuration for the salon.
 *
 * Operating hours drive customer-side booking validation; blocked dates live
 * in their own table because admins maintain many of them.
 */
class SalonSetting extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'operating_hours',
        'slot_interval_minutes',
        'booking_lead_days',
        'down_payment_required',
        'down_payment_percentage',
    ];

    protected function casts(): array
    {
        return [
            'operating_hours' => 'array',
            'slot_interval_minutes' => 'integer',
            'booking_lead_days' => 'integer',
            'down_payment_required' => 'boolean',
            'down_payment_percentage' => 'integer',
        ];
    }

    public static function current(): self
    {
        $settings = static::first();

        if ($settings) {
            return $settings;
        }

        // Create with every default explicit: column-level DB defaults are not
        // reflected on the in-memory model, and a booking_lead_days of 0 would
        // make every date look unbookable.
        return static::create([
            'name' => 'Balai ti Arjud — Glow & Co. Beauty Lounge',
            'operating_hours' => self::defaultHours(),
            'slot_interval_minutes' => 30,
            'booking_lead_days' => 60,
            'down_payment_required' => true,
            'down_payment_percentage' => 50,
        ]);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function defaultHours(): array
    {
        return [
            'monday' => ['09:00', '18:00'],
            'tuesday' => ['09:00', '18:00'],
            'wednesday' => ['09:00', '18:00'],
            'thursday' => ['09:00', '18:00'],
            'friday' => ['09:00', '19:00'],
            'saturday' => ['08:00', '19:00'],
            'sunday' => ['09:00', '17:00'],
        ];
    }

    /** @return array<string, string> */
    public static function dayNames(): array
    {
        return [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];
    }

    /** Hours for a given date, or null when the salon is closed that day. */
    public function hoursFor(Carbon|string $date): ?array
    {
        $day = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        return $this->operating_hours[strtolower($day->dayName)] ?? null;
    }

    public function isOpenOn(Carbon|string $date): bool
    {
        return $this->hoursFor($date) !== null;
    }

    /** Bookable slot times ("HH:MM") for a given date. */
    public function slotsFor(Carbon|string $date): array
    {
        $hours = $this->hoursFor($date);

        if ($hours === null) {
            return [];
        }

        $day = $date instanceof Carbon ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');

        $start = Carbon::createFromFormat('Y-m-d H:i', $day.' '.$hours[0]);
        $end = Carbon::createFromFormat('Y-m-d H:i', $day.' '.$hours[1]);
        $step = max(15, (int) $this->slot_interval_minutes);

        $slots = [];

        while ($start->lt($end)) {
            $slots[] = $start->format('H:i');
            $start->addMinutes($step);
        }

        return $slots;
    }

    public function isTimeWithinHours(string $date, string $time): bool
    {
        $hours = $this->hoursFor($date);

        if ($hours === null) {
            return false;
        }

        return $time >= $hours[0] && $time <= $hours[1];
    }

    public function expectedDownPaymentFor(float $total): float
    {
        return round($total * ((int) $this->down_payment_percentage / 100), 2);
    }
}
