<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\SalonSetting;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for "can this appointment be booked at that time?".
 *
 * Used by:
 *  - StoreBookingRequest / RescheduleRequest (server-side validation)
 *  - AppointmentController + RescheduleAppointmentController (slot dropdown)
 *
 * Four rules, and there used to be a fifth:
 *
 *   - at least a day of notice, and no further ahead than the booking horizon;
 *   - not a day the salon is closed, per its operating hours;
 *   - inside operating hours; and
 *   - not a slot another booking already holds.
 *
 * The fifth was the blocked-date check, and it is gone with the Calendar &
 * Blocked Dates feature. A closure is now expressed the only way the salon can
 * actually close — by editing its operating hours in `salon_settings` — so there
 * is one mechanism rather than two that could disagree: a day marked closed in
 * one place and open in the other used to be a booking that no screen would accept
 * and no one could explain.
 *
 * Everything here is still enforced server-side as well as on the date input, and
 * the customer form reads this class for its slot dropdown, so a day the salon is
 * closed comes back with no slots rather than with slots that fail on submit.
 */
class BookingAvailability
{
    /** Shown when a date is inside the minimum-notice window. */
    public const MINIMUM_NOTICE_MESSAGE = 'Appointments must be booked at least one day in advance.';

    public function __construct(protected SalonSetting $settings) {}

    public static function make(): self
    {
        return new self(SalonSetting::current());
    }

    public function settings(): SalonSetting
    {
        return $this->settings;
    }

    /**
     * First bookable date.
     *
     * Tomorrow, in the salon's local time. Same-day bookings are not accepted,
     * so the customer's date input, the slot endpoint and both form validators
     * all resolve their floor through here rather than each re-deriving it.
     */
    public function firstBookableDate(): Carbon
    {
        return today()->addDay();
    }

    /** Last bookable date, bounded by the configured booking lead time. */
    public function lastBookableDate(): Carbon
    {
        return today()->addDays((int) $this->settings->booking_lead_days);
    }

    /**
     * All reasons a date is unavailable.
     *
     * No longer takes a `$serviceId`: blocking was the only rule that could vary
     * by service, and with the feature gone neither notice, horizon, opening days
     * nor hours depends on which service is in the basket. The parameter is gone
     * rather than left ignored, so no caller keeps passing a value that does
     * nothing.
     *
     * @return array<int, string>
     */
    public function dateProblems(string $date): array
    {
        $problems = [];
        $day = Carbon::parse($date);

        // One rule covers today and the past: both fail the minimum notice.
        if ($day->lt($this->firstBookableDate())) {
            $problems[] = self::MINIMUM_NOTICE_MESSAGE;
        }

        if ($day->gt($this->lastBookableDate())) {
            $problems[] = 'Bookings only open '.(int) $this->settings->booking_lead_days.' days in advance.';
        }

        if (! $this->settings->isOpenOn($day)) {
            $problems[] = 'We are closed on '.$day->format('l').'s.';
        }

        return $problems;
    }

    public function isDateAvailable(string $date): bool
    {
        return $this->dateProblems($date) === [];
    }

    /**
     * Bookable times on a date, excluding slots already taken and slots that
     * would overlap an accepted appointment.
     *
     * @return array<int, string>
     */
    public function availableSlots(string $date, ?int $ignoreAppointmentId = null): array
    {
        if (! $this->isDateAvailable($date)) {
            return [];
        }

        $slots = $this->settings->slotsFor($date);

        if ($slots === []) {
            return [];
        }

        $taken = $this->takenTimes($date, $ignoreAppointmentId);

        // A slot is unavailable once the current time is within an hour of it.
        $now = now();

        return array_values(array_filter($slots, function (string $slot) use ($taken, $date, $now) {
            if (in_array($slot, $taken, true)) {
                return false;
            }

            $slotAt = Carbon::parse($date.' '.$slot);

            return $slotAt->gt($now->copy()->addHour());
        }));
    }

    /**
     * Times already committed on a date.
     *
     * Pending and accepted both hold their slot, otherwise a customer could
     * pick a time another customer has already been given and the second
     * booking would fail at confirmation instead of at selection.
     *
     * @return array<int, string>
     */
    public function takenTimes(string $date, ?int $ignoreAppointmentId = null): array
    {
        return Appointment::query()
            ->active()
            ->whereDate('preferred_date', $date)
            ->whereIn('status', [
                AppointmentStatus::Pending->value,
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::InProgress->value,
            ])
            ->when($ignoreAppointmentId, fn ($q) => $q->whereKeyNot($ignoreAppointmentId))
            ->pluck('preferred_time')
            ->map(fn ($time) => Carbon::parse($time)->format('H:i'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function timeProblems(string $date, string $time, ?int $ignoreAppointmentId = null): array
    {
        $problems = $this->dateProblems($date);

        if ($problems !== []) {
            return $problems;
        }

        if (! $this->settings->isTimeWithinHours($date, $time)) {
            $hours = $this->settings->hoursFor($date);
            $problems[] = "That time is outside our operating hours ({$hours[0]}–{$hours[1]}).";
        }

        if (in_array(Carbon::parse($time)->format('H:i'), $this->takenTimes($date, $ignoreAppointmentId), true)) {
            $problems[] = 'That slot has just been taken. Please pick another time.';
        }

        if (Carbon::parse($date.' '.$time)->lte(now()->addHour())) {
            $problems[] = 'Please book at least an hour in advance.';
        }

        return $problems;
    }

    }