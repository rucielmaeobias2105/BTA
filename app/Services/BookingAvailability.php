<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\SalonSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "can this appointment be booked at that time?".
 *
 * Used by:
 *  - StoreBookingRequest / UpdateRescheduleRequest (server-side validation)
 *  - BookingController + RescheduleController (slot dropdown)
 *  - Admin CalendarController (which days are struck through)
 */
class BookingAvailability
{
    public function __construct(protected SalonSetting $settings) {}

    public static function make(): self
    {
        return new self(SalonSetting::current());
    }

    public function settings(): SalonSetting
    {
        return $this->settings;
    }

    /** First bookable date. */
    public function firstBookableDate(): Carbon
    {
        return today();
    }

    /** Last bookable date, bounded by the configured booking lead time. */
    public function lastBookableDate(): Carbon
    {
        return today()->addDays((int) $this->settings->booking_lead_days);
    }

    /**
     * All reasons a date is unavailable.
     *
     * @return array<int, string>
     */
    public function dateProblems(string $date, ?int $serviceId = null): array
    {
        $problems = [];
        $day = Carbon::parse($date);

        if ($day->lt(today())) {
            $problems[] = 'The selected date is in the past.';
        }

        if ($day->gt($this->lastBookableDate())) {
            $problems[] = 'Bookings only open '.(int) $this->settings->booking_lead_days.' days in advance.';
        }

        if (! $this->settings->isOpenOn($day)) {
            $problems[] = 'We are closed on '.$day->format('l').'s.';
        }

        foreach ($this->blockedRanges($serviceId) as $blocked) {
            if ($day->betweenIncluded($blocked['start'], $blocked['end'])) {
                $label = $blocked['label'];

                $problems[] = $blocked['service_id'] === null
                    ? "The salon is closed on {$label}.".($blocked['reason'] ? " ({$blocked['reason']})" : '')
                    : "{$blocked['service']} is unavailable on {$label}.".($blocked['reason'] ? " ({$blocked['reason']})" : '');

                break;
            }
        }

        return $problems;
    }

    public function isDateAvailable(string $date, ?int $serviceId = null): bool
    {
        return $this->dateProblems($date, $serviceId) === [];
    }

    /**
     * Blocked ranges that apply to a given service.
     *
     * @return array<int, array{start: Carbon, end: Carbon, service_id: ?int, service: ?string, label: string, reason: ?string}>
     */
    public function blockedRanges(?int $serviceId = null): array
    {
        $query = BlockedDate::query()
            ->with('service:id,name')
            ->overlapping($this->firstBookableDate()->toDateString(), $this->lastBookableDate()->toDateString());

        if ($serviceId !== null) {
            // Applies to this service specifically, or to every service.
            $query->where(fn ($q) => $q->where('service_id', $serviceId)->orWhereNull('service_id'));
        } else {
            // Service-agnostic queries only consider global closures.
            $query->whereNull('service_id');
        }

        return $query->get()
            ->map(function (BlockedDate $blocked) {
                $start = $blocked->start_date->copy()->startOfDay();
                $end = $blocked->end_date->copy()->startOfDay();

                return [
                    'start' => $start,
                    'end' => $end,
                    'service_id' => $blocked->service_id,
                    'service' => $blocked->service?->name,
                    'label' => $start->isSameDay($end)
                        ? $start->format('M j, Y')
                        : $start->format('M j').'–'.$end->format('j, Y'),
                    'reason' => $blocked->reason,
                ];
            })
            ->all();
    }

    /**
     * Bookable times on a date, excluding slots already taken and slots that
     * would overlap a confirmed/in-progress appointment.
     *
     * @return array<int, string>
     */
    public function availableSlots(string $date, ?int $serviceId = null, ?int $ignoreAppointmentId = null): array
    {
        if (! $this->isDateAvailable($date, $serviceId)) {
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
     * @return array<int, string>
     */
    public function takenTimes(string $date, ?int $ignoreAppointmentId = null): array
    {
        return Appointment::query()
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
    public function timeProblems(string $date, string $time, ?int $serviceId = null, ?int $ignoreAppointmentId = null): array
    {
        $problems = $this->dateProblems($date, $serviceId);

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

    /**
     * Days between today and the booking horizon, flagged for the date picker.
     *
     * @return array<string, array{blocked: bool, label: ?string}>
     */
    public function calendarMap(?int $serviceId = null, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= $this->firstBookableDate();
        $to ??= $from->copy()->addDays(29);

        $map = collect();
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $problems = $this->dateProblems($cursor->toDateString(), $serviceId);

            $map->put($cursor->toDateString(), [
                'blocked' => $problems !== [],
                'label' => $problems[0] ?? null,
            ]);

            $cursor->addDay();
        }

        return $map;
    }
}
