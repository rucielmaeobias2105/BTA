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
        //
        // `down_payment_required` is false: the salon takes no deposit for a web
        // booking, and the form no longer asks for a GCash reference. Left true it
        // would make `BookingService` compute a deposit amount for a booking that
        // can never have paid one.
        return static::create([
            'name' => 'Balai ti Arjud — Glow & Co. Beauty Lounge',
            'operating_hours' => self::defaultHours(),
            'slot_interval_minutes' => 30,
            'booking_lead_days' => 60,
            'down_payment_required' => false,
            'down_payment_percentage' => 50,
        ]);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function defaultHours(): array
    {
        // Open every day, 9am to 5pm — one window, no per-day variation.
        //
        // Previously Monday–Thursday 09:00–18:00, Friday to 19:00, Saturday from
        // 08:00 to 19:00 and Sunday to 17:00, so a customer could book a slot that
        // existed on two days and not on five others. The salon books to a single
        // schedule now, which is also why the days are listed out rather than
        // generated: `operating_hours` treats a missing day as closed, and a
        // generated list would quietly depend on that rule to mean "open".
        return [
            'monday' => ['09:00', '17:00'],
            'tuesday' => ['09:00', '17:00'],
            'wednesday' => ['09:00', '17:00'],
            'thursday' => ['09:00', '17:00'],
            'friday' => ['09:00', '17:00'],
            'saturday' => ['09:00', '17:00'],
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

    /**
     * "09:00" → "09:00 AM", "17:00" → "05:00 PM", for display only.
     *
     * `operating_hours` is stored and compared as 24-hour "HH:MM" because
     * `isTimeWithinHours` and the slot maths both parse it as such — switching the
     * stored format to "5:00 PM" would break every availability rule behind the
     * booking form. So the 12-hour form belongs at the edge, in the view, and
     * this is the one place it is derived.
     *
     * The hour is zero-padded on purpose: "09:00 AM" lines its digits up with
     * "05:00 PM" in a column, which "9:00 AM" next to "5:00 PM" does not.
     */
    public static function formatTimeForDisplay(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        [$hour, $minute] = array_pad(explode(':', $time, 2), 2, '00');

        $hour = (int) $hour;
        $suffix = $hour >= 12 ? 'PM' : 'AM';

        // 12-hour wrap, with 12 and 0 both landing on 12 rather than 0.
        $hour12 = $hour % 12 === 0 ? 12 : $hour % 12;

        return sprintf('%02d:%02d %s', $hour12, (int) $minute, $suffix);
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
