/**
 * Date helpers.
 *
 * The seeders built every demo record relative to `today()`, so the data never
 * goes stale. The same trick is applied here: each record is generated from the
 * current date at module load, which keeps the calendar, the booking form and
 * the "expires in N days" copy honest whenever the site is opened.
 */

const DAY_MS = 24 * 60 * 60 * 1000;

/** Local midnight today. Deliberately not `new Date()` — times must be stable. */
export function today() {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

export function startOfDay(value) {
    const date = toDate(value);

    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

export function toDate(value) {
    return value instanceof Date ? new Date(value) : new Date(value);
}

export function addDays(value, days) {
    const date = startOfDay(value);

    date.setDate(date.getDate() + days);

    return date;
}

export function addMinutes(value, minutes) {
    return new Date(toDate(value).getTime() + minutes * 60 * 1000);
}

export function addWeeks(value, weeks) {
    return addDays(value, weeks * 7);
}

export function subWeeks(value, weeks) {
    return addDays(value, -weeks * 7);
}

export function subMonths(value, months) {
    const date = startOfDay(value);

    date.setMonth(date.getMonth() - months);

    return date;
}

export function subYears(value, years) {
    const date = startOfDay(value);

    date.setFullYear(date.getFullYear() - years);

    return date;
}

/** Whole days from `a` to `b`; negative when `b` is in the past. */
export function daysBetween(a, b) {
    return Math.round((startOfDay(b) - startOfDay(a)) / DAY_MS);
}

/** Inclusive on both ends, matching `Carbon::betweenIncluded()`. */
export function isWithin(value, start, end) {
    const target = startOfDay(value).getTime();
    const from = startOfDay(start).getTime();
    const until = startOfDay(end).getTime();

    return target >= from && target <= until;
}

/** `"2026-04-18"` — the value an `<input type="date">` expects. */
export function toDateInput(value) {
    const date = toDate(value);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/** Parses `"2026-04-18"` as local midnight, avoiding the UTC shift of `new Date(str)`. */
export function fromDateInput(value) {
    if (!value) return null;

    const [year, month, day] = String(value).split('-').map(Number);

    if (!year || !month || !day) return null;

    return new Date(year, month - 1, day);
}

/** `"monday"` — the key used in the operating-hours map. */
export function weekdayKey(value) {
    return ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][
        toDate(value).getDay()
    ];
}

/** `"09:00"` -> minutes since midnight. */
export function timeToMinutes(time) {
    const [hours, minutes] = String(time ?? '00:00').split(':').map(Number);

    return (hours * 60) + minutes;
}

/** 570 -> `"09:30"`. */
export function minutesToTime(minutes) {
    const hours = String(Math.floor(minutes / 60)).padStart(2, '0');
    const rest = String(minutes % 60).padStart(2, '0');

    return `${hours}:${rest}`;
}
