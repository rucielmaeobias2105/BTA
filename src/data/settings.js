import { reactive } from 'vue';
import { addDays, isWithin, minutesToTime, timeToMinutes, today, weekdayKey } from '@/lib/dates';

/**
 * Salon operating configuration.
 *
 * Ported from `App\Models\SalonSetting` and the blocked-date rows seeded by
 * `ContentSeeder`. Operating hours drive the customer-side slot picker and the
 * admin calendar, and the down-payment percentage drives the booking summary.
 */

/** `SalonSetting::defaultHours()` — `[open, close]` per weekday. */
const DEFAULT_HOURS = {
    monday: ['09:00', '18:00'],
    tuesday: ['09:00', '18:00'],
    wednesday: ['09:00', '18:00'],
    thursday: ['09:00', '18:00'],
    friday: ['09:00', '19:00'],
    saturday: ['08:00', '19:00'],
    sunday: ['09:00', '17:00'],
};

/** `SalonSetting::dayNames()` */
export const DAY_NAMES = {
    monday: 'Monday',
    tuesday: 'Tuesday',
    wednesday: 'Wednesday',
    thursday: 'Thursday',
    friday: 'Friday',
    saturday: 'Saturday',
    sunday: 'Sunday',
};

/** Monday-first ordering for the calendar and the hours editor. */
export const WEEKDAY_ORDER = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

export const salonSettings = reactive({
    name: 'Balai ti Arjud — Glow & Co. Beauty Lounge',
    address: 'Purok 5, Abra, Philippines',
    phone: '+63 900 000 0000',
    email: 'hello@balaitiarjud.test',
    operating_hours: { ...DEFAULT_HOURS },
    slot_interval_minutes: 30,
    booking_lead_days: 60,
    down_payment_required: true,
    down_payment_percentage: 50,
});

/** Hours for a date, or null when the salon is closed that day. */
export function hoursFor(date) {
    return salonSettings.operating_hours[weekdayKey(date)] ?? null;
}

export function isOpenOn(date) {
    return hoursFor(date) !== null;
}

/** Bookable slot times (`"HH:MM"`) for a date. */
export function slotsFor(date) {
    const hours = hoursFor(date);

    if (hours === null) return [];

    const [open, close] = hours.map(timeToMinutes);
    const step = Math.max(15, Number(salonSettings.slot_interval_minutes));
    const slots = [];

    for (let minute = open; minute < close; minute += step) {
        slots.push(minutesToTime(minute));
    }

    return slots;
}

export function isTimeWithinHours(date, time) {
    const hours = hoursFor(date);

    if (hours === null) return false;

    const value = timeToMinutes(time);

    return value >= timeToMinutes(hours[0]) && value <= timeToMinutes(hours[1]);
}

/** `expectedDownPaymentFor()` — rounds to 2dp like the PHP helper. */
export function expectedDownPaymentFor(total) {
    return Math.round(Number(total) * (Number(salonSettings.down_payment_percentage) / 100) * 100) / 100;
}

/** Advances to the next open day, so seeded demo data never lands on a closure. */
export function nextOpenDay(date) {
    let candidate = addDays(date, 0);

    while (!isOpenOn(candidate) || isBlocked(candidate)) {
        candidate = addDays(candidate, 1);
    }

    return candidate;
}

export const blockedDates = reactive([
    {
        id: 1,
        start_date: nextOpenDay(addDays(today(), 9)),
        end_date: nextOpenDay(addDays(today(), 9)),
        service_id: null,
        service_name: null,
        reason: 'Provincial holiday — salon closed',
        created_by: 1,
    },
    {
        id: 2,
        start_date: nextOpenDay(addDays(today(), 23)),
        end_date: nextOpenDay(addDays(today(), 23)),
        service_id: null,
        service_name: null,
        reason: 'Team training day',
        created_by: 1,
    },
    {
        id: 3,
        start_date: nextOpenDay(addDays(today(), 30)),
        end_date: nextOpenDay(addDays(today(), 30)),
        service_id: 9,
        service_name: 'Volume Lash Extensions',
        reason: 'Lash stock delivery delayed',
        created_by: 1,
    },
    {
        id: 4,
        start_date: nextOpenDay(addDays(today(), 45)),
        end_date: nextOpenDay(addDays(today(), 47)),
        service_id: null,
        service_name: null,
        reason: 'Inventory audit and deep clean',
        created_by: 1,
    },
]);

/** Is the salon closed on this date, salon-wide or for one service? */
export function isBlocked(date, serviceId = null) {
    return blockedDates.some(
        (block) =>
            isWithin(date, block.start_date, block.end_date)
            && (block.service_id === null || String(block.service_id) === String(serviceId)),
    );
}

/** The blocks touching a month, for the admin calendar grid. */
export function blocksInMonth(year, month) {
    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);

    return blockedDates.filter((block) => isWithin(block.start_date, first, last) || isWithin(block.end_date, first, last));
}
