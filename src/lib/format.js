/**
 * Formatting helpers.
 *
 * These replace the `Str::` / `number_format()` / `->format()` calls the Blade
 * templates used, so a page ported to Vue renders dates and money exactly the
 * way the server-rendered version did.
 */

export const APP_NAME = 'Balai ti Arjud';

const MONTHS_SHORT = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

const MONTHS_LONG = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const DAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const DAYS_LONG = [
    'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
];

const pad = (value) => String(value).padStart(2, '0');

/**
 * Renders a date using a PHP-style format string.
 *
 * Only the tokens the templates actually used are supported, which keeps the
 * mapping unambiguous: an unknown character is emitted verbatim rather than
 * being silently swallowed.
 */
export function formatDate(value, pattern = 'M j, Y') {
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) return '';

    return pattern.replace(
        /\\?(d|j|D|l|N|S|w|z|W|F|m|M|n|t|L|Y|y|a|A|B|g|G|h|H|i|s|u|v|e|O|P|p|T|Z|c|r|U)/g,
        (token) => {
            switch (token) {
                case 'd':
                    return pad(date.getDate());
                case 'j':
                    return String(date.getDate());
                case 'D':
                    return DAYS_SHORT[date.getDay()];
                case 'l':
                    return DAYS_LONG[date.getDay()];
                case 'N':
                    return String(date.getDay() === 0 ? 7 : date.getDay());
                case 'w':
                    return String(date.getDay());
                case 'F':
                    return MONTHS_LONG[date.getMonth()];
                case 'm':
                    return pad(date.getMonth() + 1);
                case 'M':
                    return MONTHS_SHORT[date.getMonth()];
                case 'n':
                    return String(date.getMonth() + 1);
                case 't':
                    return String(new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate());
                case 'Y':
                    return String(date.getFullYear());
                case 'y':
                    return String(date.getFullYear()).slice(-2);
                case 'a':
                    return date.getHours() < 12 ? 'am' : 'pm';
                case 'A':
                    return date.getHours() < 12 ? 'AM' : 'PM';
                case 'g':
                    return String(date.getHours() % 12 || 12);
                case 'G':
                    return String(date.getHours());
                case 'h':
                    return pad(date.getHours() % 12 || 12);
                case 'H':
                    return pad(date.getHours());
                case 'i':
                    return pad(date.getMinutes());
                case 's':
                    return pad(date.getSeconds());
                default:
                    return token;
            }
        },
    );
}

/** `1234.5` -> `"1,234.50"`, matching `number_format($n, 2)`. */
export function formatNumber(value, decimals = 2) {
    const amount = Number(value ?? 0);

    if (!Number.isFinite(amount)) return (0).toFixed(decimals);

    return amount.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

/** Appends the peso sign the salon shows next to every price. */
export function formatMoney(value, decimals = 2) {
    return `₱${formatNumber(value, decimals)}`;
}

/** `Str::limit()` — hard cut at `limit` characters, ellipsis appended. */
export function truncate(value, limit = 100) {
    const text = stripTags(String(value ?? ''));

    if (text.length <= limit) return text;

    return `${text.slice(0, limit).trimEnd()}...`;
}

/** Drops HTML tags; promo descriptions are stored with markup in the old seed. */
export function stripTags(value) {
    return String(value ?? '').replace(/<[^>]*>/g, '');
}

/** `Str::upper()` */
export function upper(value) {
    return String(value ?? '').toUpperCase();
}

/** `Str::before($full, ' ')` — the given name half of a full name. */
export function firstName(fullName) {
    return String(fullName ?? '').split(' ')[0] ?? '';
}

/** The two-letter monogram used by the avatar fallback. */
export function initials(first = '', last = '') {
    return `${first.charAt(0)}${last.charAt(0)}`.toUpperCase();
}

/** The two-letter monogram derived from a full name. */
export function initialsFrom(fullName) {
    const [first = '', ...rest] = String(fullName ?? '').split(' ');

    return initials(first, rest[rest.length - 1] ?? '');
}

/** `Str::plural($count, $word)` — "1 item" / "3 items". */
export function plural(count, singular, pluralForm) {
    const word = count === 1 ? singular : (pluralForm ?? `${singular}s`);

    return `${formatNumber(count, 0)} ${word}`;
}

/** Sums a numeric field across a list of records. */
export function sumBy(rows, field) {
    return (rows ?? []).reduce((total, row) => total + Number(row?.[field] ?? 0), 0);
}
