import { addDays, addMinutes, isWithin, startOfDay, toDateInput, today } from '@/lib/dates';
import { AppointmentStatus } from './enums';
import { appointments } from './appointments';
import { blockedDates, hoursFor, isOpenOn, salonSettings, slotsFor } from './settings';
import { serviceByName } from './services';

/**
 * "Can this appointment be booked at that time?"
 *
 * Ported from `App\Services\BookingAvailability`. Three consumers used to share
 * this one class: the server-side form requests, the booking / reschedule slot
 * dropdowns, and the admin calendar's struck-through days. All three read it
 * here now, so they cannot drift apart.
 */

/** First bookable date. */
export function firstBookableDate() {
    return today();
}

/** Last bookable date, bounded by the configured booking lead time. */
export function lastBookableDate() {
    return addDays(today(), Number(salonSettings.booking_lead_days));
}

/**
 * Blocked ranges that apply to a service, in the shape the date picker wants:
 * `{ start, end, service_id, service, label, reason }`.
 *
 * A null `serviceId` means "any service", which only considers global closures.
 */
export function blockedRanges(serviceId = null) {
    const from = firstBookableDate();
    const to = lastBookableDate();

    return blockedDates
        .filter(
            (block) =>
                // Applies to this service specifically, or to every service.
                (serviceId === null ? block.service_id === null : true)
                && isWithin(block.start_date, from, to),
        )
        .map((block) => {
            const start = startOfDay(block.start_date);
            const end = startOfDay(block.end_date);
            const sameDay = toDateInput(start) === toDateInput(end);

            return {
                start,
                end,
                service_id: block.service_id,
                service: block.service_name,
                label: sameDay
                    ? `${start.getMonth() + 1}/${start.getDate()}/${start.getFullYear()}`
                    : `${start.getMonth() + 1}/${start.getDate()}–${end.getDate()}, ${end.getFullYear()}`,
                reason: block.reason,
            };
        });
}

/** Every reason a date is unavailable; empty means bookable. */
export function dateProblems(date, serviceId = null) {
    if (!date) return ['No date selected.'];

    const problems = [];
    const day = startOfDay(date);

    if (day < today()) {
        problems.push('The selected date is in the past.');
    }

    if (day > lastBookableDate()) {
        problems.push(`Bookings only open ${Number(salonSettings.booking_lead_days)} days in advance.`);
    }

    if (!isOpenOn(day)) {
        problems.push(`We are closed on ${day.toLocaleDateString('en-US', { weekday: 'long' })}s.`);
    }

    const match = blockedRanges(serviceId).find((block) => isWithin(day, block.start, block.end));

    if (match) {
        const reason = match.reason ? ` (${match.reason})` : '';

        problems.push(
            match.service_id === null
                ? `The salon is closed on ${match.label}.${reason}`
                : `${match.service} is unavailable on ${match.label}.${reason}`,
        );
    }

    return problems;
}

export function isDateAvailable(date, serviceId = null) {
    return dateProblems(date, serviceId).length === 0;
}

/** Times already committed on a date. */
export function takenTimes(date, ignoreAppointmentId = null) {
    const key = toDateInput(date);

    return [
        ...new Set(
            appointments
                .filter(
                    (appointment) =>
                        toDateInput(appointment.preferred_date) === key
                        && String(appointment.id) !== String(ignoreAppointmentId)
                        && [
                            AppointmentStatus.Pending.value,
                            AppointmentStatus.Confirmed.value,
                            AppointmentStatus.InProgress.value,
                        ].includes(appointment.status),
                )
                .map((appointment) => appointment.preferred_time),
        ),
    ];
}

/**
 * Bookable times on a date: operating hours minus taken slots, and minus
 * anything starting within the next hour.
 */
export function availableSlots(date, serviceId = null, ignoreAppointmentId = null) {
    if (!isDateAvailable(date, serviceId)) return [];

    const slots = slotsFor(date);

    if (slots.length === 0) return [];

    const taken = takenTimes(date, ignoreAppointmentId);
    const cutoff = addMinutes(new Date(), 60);

    return slots.filter((slot) => {
        if (taken.includes(slot)) return false;

        const [hours, minutes] = slot.split(':').map(Number);
        const slotAt = new Date(startOfDay(date).getTime() + ((hours * 60) + minutes) * 60 * 1000);

        return slotAt > cutoff;
    });
}

/** Everything wrong with a specific date + time, for form-level validation. */
export function timeProblems(date, time, serviceId = null, ignoreAppointmentId = null) {
    const problems = dateProblems(date, serviceId);

    if (problems.length) return problems;

    const hours = hoursFor(date);

    if (hours === null) {
        return [`We are closed on ${startOfDay(date).toLocaleDateString('en-US', { weekday: 'long' })}s.`];
    }

    if (time < hours[0] || time > hours[1]) {
        problems.push(`That time is outside our operating hours (${hours[0]}–${hours[1]}).`);
    }

    if (takenTimes(date, ignoreAppointmentId).includes(time)) {
        problems.push('That slot has just been taken. Please pick another time.');
    }

    if (!isDateAvailable(date, serviceId)) {
        problems.push('Please book at least an hour in advance.');
    }

    return problems;
}

/**
 * The booking horizon as a `YYYY-MM-DD` => `{ blocked, label }` map, for the
 * date picker and the admin calendar grid.
 */
export function calendarMap(serviceId = null, from = null, to = null) {
    const start = from ?? firstBookableDate();
    const end = to ?? addDays(start, 29);
    const map = {};

    for (let cursor = startOfDay(start); cursor <= startOfDay(end); cursor = addDays(cursor, 1)) {
        const problems = dateProblems(cursor, serviceId);

        map[toDateInput(cursor)] = {
            blocked: problems.length > 0,
            label: problems[0] ?? null,
        };
    }

    return map;
}

/** First bookable date that still has an open slot. */
export function nextAvailableDate(serviceId = null) {
    const last = lastBookableDate();

    for (let cursor = firstBookableDate(); cursor <= last; cursor = addDays(cursor, 1)) {
        if (availableSlots(cursor, serviceId).length > 0) return toDateInput(cursor);
    }

    return toDateInput(today());
}

/** Appointments grouped by day, for the admin calendar month grid. */
export function appointmentsByDay() {
    return appointments.reduce((grouped, appointment) => {
        const key = toDateInput(appointment.preferred_date);

        (grouped[key] ??= []).push(appointment);

        return grouped;
    }, {});
}

/** The service a blocked date refers to, if any. */
export function blockedService(block) {
    return block.service_id ? serviceByName(block.service_name) : null;
}
