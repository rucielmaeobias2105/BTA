import { reactive, computed } from 'vue';
import { addDays, startOfDay, toDateInput, today } from '@/lib/dates';
import { AppointmentStatus, ChangedBy, DownPaymentStatus } from './enums';
import { defaultVariant, effectiveDuration, serviceByName } from './services';
import { users } from './users';
import { superAdmin } from './admins';
import { expectedDownPaymentFor, isOpenOn, nextOpenDay } from './settings';

/**
 * Appointments, their service lines, status history and reviews.
 *
 * Ported from `database/seeders/AppointmentSeeder.php` and the presentation
 * accessors on `App\Models\Appointment`. Records are generated relative to today
 * so the calendar and the booking horizon always have data in them.
 */

/** `Appointment::generateReferenceNumber()` — `BTA-20260926-4F2A`. */
export function generateReferenceNumber() {
    const stamp = toDateInput(new Date()).replaceAll('-', '');
    let suffix;

    do {
        suffix = Math.random().toString(16).slice(2, 6).toUpperCase().padEnd(4, '0');
    } while (appointments.some((appointment) => appointment.reference_number === `BTA-${stamp}-${suffix}`));

    return `BTA-${stamp}-${suffix}`;
}

let nextId = 1;
let nextLineId = 1;
let nextHistoryId = 1;
let nextReviewId = 1;

function withTime(date, time) {
    const [hours, minutes] = String(time).split(':').map(Number);
    const value = startOfDay(date);

    value.setHours(hours, minutes, 0, 0);

    return value;
}

/**
 * The status path a seeded appointment walked to reach its final state, and
 * the `recordHistory()` trail that `AppointmentSeeder` wrote for it.
 */
function buildHistory({ status, cancelReason, customer, stylist }) {
    const admin = superAdmin();
    const trail = [
        {
            id: nextHistoryId++,
            from_status: null,
            to_status: AppointmentStatus.Pending.value,
            changed_by: ChangedBy.Customer.value,
            changed_by_id: customer.id,
            changed_by_name: customer.full_name,
            note: 'Appointment submitted by customer.',
        },
    ];

    const steps = {
        pending: [],
        confirmed: [AppointmentStatus.Confirmed],
        in_progress: [AppointmentStatus.Confirmed, AppointmentStatus.InProgress],
        completed: [AppointmentStatus.Confirmed, AppointmentStatus.InProgress, AppointmentStatus.Completed],
        cancelled: [AppointmentStatus.Cancelled],
    }[status] ?? [];

    steps.forEach((step) => {
        const isCancellation = step === AppointmentStatus.Cancelled;

        trail.push({
            id: nextHistoryId++,
            from_status: trail[trail.length - 1].to_status,
            to_status: step.value,
            changed_by: isCancellation ? ChangedBy.Customer.value : ChangedBy.Admin.value,
            changed_by_id: isCancellation ? customer.id : (admin?.id ?? null),
            changed_by_name: isCancellation ? customer.full_name : (admin?.full_name ?? 'Admin'),
            note: isCancellation ? `Cancelled by customer. Reason: ${cancelReason ?? 'n/a'}` : null,
        });
    });

    return trail;
}

function buildLines(serviceNames) {
    const lines = [];
    let total = 0;

    serviceNames.forEach((name) => {
        const service = serviceByName(name);

        if (!service) return;

        const variant = defaultVariant(service);
        const price = Number(variant?.price ?? service.price);

        total += price;

        lines.push({
            id: nextLineId++,
            service_id: service.id,
            service_variant_id: variant?.id ?? null,
            service_name: service.name,
            variant_name: variant?.name ?? null,
            price,
            duration_minutes: effectiveDuration(service, variant),
            quantity: 1,
        });
    });

    return { lines, total };
}

const PLAN = [
    ['maria@example.test', 0, '13:30', AppointmentStatus.InProgress, ['Signature Blowout & Styling']],
    ['juan@example.test', 0, '10:00', AppointmentStatus.Confirmed, ['Gelish Manicure']],
    ['angeline@example.test', 0, '16:00', AppointmentStatus.Pending, ['Brightening Facial']],
    ['paolo@example.test', 1, '11:00', AppointmentStatus.Confirmed, ['Volume Lash Extensions']],
    ['kristine@example.test', 2, '14:00', AppointmentStatus.Pending, ['Spa Pedicure', 'Glow Manicure']],
    ['diego@example.test', 3, '09:30', AppointmentStatus.Pending, ['Aromatherapy Massage (90 min)']],
    ['juan@example.test', -3, '15:00', AppointmentStatus.Completed, ['Classic Eyelash Extensions']],
    ['maria@example.test', -6, '10:30', AppointmentStatus.Completed, ['Gelish Manicure']],
    ['angeline@example.test', -8, '13:00', AppointmentStatus.Completed, ['Hydrating Facial']],
    ['paolo@example.test', -10, '16:30', AppointmentStatus.Completed, ['Relaxing Massage (60 min)']],
    ['kristine@example.test', -12, '11:00', AppointmentStatus.Cancelled, ['Color & Highlights'], 'Change of plans'],
    ['diego@example.test', -14, '14:00', AppointmentStatus.Completed, ['Classic Pedicure']],
    ['juan@example.test', -20, '10:00', AppointmentStatus.Completed, ['Spa Pedicure']],
    ['maria@example.test', -25, '15:00', AppointmentStatus.Completed, ['Brazilian Waxing']],
    ['angeline@example.test', -4, '09:00', AppointmentStatus.Cancelled, ['Bridal Glow Package'], 'Rescheduled to a later date'],
];

const REVIEW_RATINGS = [5, 5, 4, 5, 3, 4, 5];

const REVIEW_MESSAGES = [
    'Amazing service! My therapist was so gentle and the result lasted for weeks.',
    'Very happy with the result. The room was spotless and everyone was so welcoming.',
    'Great value for the price. Booking was easy through the website.',
    'Loved it! The glow manicure is the best I have had in this town.',
    'Good service overall, though we ran a little late starting.',
    'The facial was soothing and my skin felt great afterwards.',
];

/**
 * Index 7 is deliberately left unreviewed so the "rate your completed visit"
 * path stays demonstrable, exactly as the seeder arranged it.
 */
const UNREVIEWED_INDEX = 7;

function seedAppointments() {
    return PLAN.map((row, index) => {
        const [email, offset, time, status, serviceNames] = row;
        const cancelReason = row[5] ?? null;
        const customer = users.find((user) => user.email === email);

        if (!customer) return null;

        // Land on an open day so the demo data is self-consistent.
        const date = nextOpenDay(addDays(today(), offset));
        const { lines, total } = buildLines(serviceNames);

        if (lines.length === 0) return null;

        const id = nextId++;
        const stylist = [3, 4, 5][index % 3];

        const appointment = {
            id,
            reference_number: `BTA-${toDateInput(date).replaceAll('-', '')}-${(1000 + id).toString(16).toUpperCase()}`,
            user_id: customer.id,
            customer_name: customer.full_name,
            customer_phone: customer.contact_number,
            customer_email: customer.email,
            preferred_date: date,
            preferred_time: time,
            allergies: index % 4 === 0 ? 'Mild sensitivity to latex.' : null,
            last_services_availed: index > 6 ? 'Glow Manicure' : null,
            preferred_stylist_id: stylist,
            special_request: index % 3 === 0 ? 'Please prepare a quiet corner if possible.' : null,
            down_payment_reference: `GCASH${String(100000 + index).padStart(6, '0')}`,
            down_payment_amount: expectedDownPaymentFor(total),
            down_payment_status:
                index % 3 === 0
                    ? DownPaymentStatus.Verified.value
                    : index % 3 === 1
                        ? DownPaymentStatus.Unverified.value
                        : DownPaymentStatus.Rejected.value,
            total_amount: total,
            status: status.value,
            source: 'website',
            admin_notes: index % 5 === 0 ? 'Prefers late afternoon slots.' : null,
            cancellation_reason: status === AppointmentStatus.Cancelled ? cancelReason : null,
            reschedule_reason: null,
            cancelled_at: status === AppointmentStatus.Cancelled ? addDays(today(), -Math.abs(offset)) : null,
            confirmed_at:
                status === AppointmentStatus.Confirmed
                || status === AppointmentStatus.InProgress
                || status === AppointmentStatus.Completed
                    ? addDays(today(), -Math.abs(offset) - 2)
                    : null,
            completed_at: status === AppointmentStatus.Completed ? addDays(today(), -Math.abs(offset)) : null,
            started_at: status === AppointmentStatus.InProgress ? today() : null,
            lines,
            history: [],
            review: null,
        };

        appointment.history = buildHistory({ status: status.value, cancelReason, customer, stylist });

        if (status === AppointmentStatus.Completed && index !== UNREVIEWED_INDEX) {
            appointment.review = {
                id: nextReviewId++,
                appointment_id: id,
                user_id: customer.id,
                service_id: lines[0].service_id,
                rating: REVIEW_RATINGS[index % REVIEW_RATINGS.length],
                message: REVIEW_MESSAGES[index % REVIEW_MESSAGES.length],
                customer_name: customer.full_name,
                // Rated a couple of days after the visit, which is what the
                // "latest" ordering on the service page sorted by.
                created_at: addDays(today(), -Math.abs(offset) - 2),
            };
        }

        return appointment;
    }).filter(Boolean);
}

export const appointments = reactive(seedAppointments());

/** Reviews live in their own table in the original; both views read them. */
export const reviews = computed(() => appointments.filter((appointment) => appointment.review).map((appointment) => appointment.review));

export const averageRating = computed(() => {
    if (reviews.value.length === 0) return 0;

    const total = reviews.value.reduce((sum, review) => sum + review.rating, 0);

    return Math.round((total / reviews.value.length) * 10) / 10;
});

/* ------------------------------------------------------------------ */
/* Queries — the scopes on App\Models\Appointment                       */
/* ------------------------------------------------------------------ */

export function appointmentsForUser(userId) {
    return appointments.filter((appointment) => String(appointment.user_id) === String(userId));
}

export function findAppointment(id) {
    return (
        appointments.find((appointment) => String(appointment.id) === String(id))
        ?? appointments.find((appointment) => appointment.reference_number === id)
        ?? null
    );
}

export function filterByStatus(list, status) {
    if (!status || status === 'all') return list;

    return list.filter((appointment) => appointment.status === status);
}

export function upcomingAppointments(list = appointments) {
    return list
        .filter(
            (appointment) =>
                startOfDay(appointment.preferred_date) >= today()
                && [AppointmentStatus.Pending.value, AppointmentStatus.Confirmed.value].includes(appointment.status),
        )
        .sort(
            (a, b) =>
                withTime(a.preferred_date, a.preferred_time) - withTime(b.preferred_date, b.preferred_time),
        );
}

export function appointmentsBetween(list, from, to) {
    return list.filter((appointment) => {
        if (from && startOfDay(appointment.preferred_date) < startOfDay(from)) return false;
        if (to && startOfDay(appointment.preferred_date) > startOfDay(to)) return false;

        return true;
    });
}

export function appointmentsOn(date) {
    const key = toDateInput(date);

    return appointments.filter((appointment) => toDateInput(appointment.preferred_date) === key);
}

/* ------------------------------------------------------------------ */
/* Presentation accessors                                              */
/* ------------------------------------------------------------------ */

/** `"09:30"` -> `"9:30 AM"`. */
export function timeLabel(time) {
    const [hours, minutes] = String(time ?? '00:00').split(':').map(Number);
    const suffix = hours < 12 ? 'AM' : 'PM';
    const display = hours % 12 || 12;

    return `${display}:${String(minutes).padStart(2, '0')} ${suffix}`;
}

export function dateTimeLabel(appointment) {
    return `${toDateInput(appointment.preferred_date)} ${timeLabel(appointment.preferred_time)}`;
}

/** `"Gelish Manicure (Gelish Manicure), Spa Pedicure"` — the line display names. */
export function serviceNamesLabel(appointment) {
    if (!appointment.lines.length) return '—';

    return appointment.lines.map((line) => line.variant_name ?? line.service_name).join(', ');
}

export function totalDuration(appointment) {
    return appointment.lines.reduce((sum, line) => sum + (line.duration_minutes * line.quantity), 0);
}

export function canBeCancelled(appointment) {
    return [AppointmentStatus.Pending.value, AppointmentStatus.Confirmed.value].includes(appointment.status);
}

export function canBeRescheduled(appointment) {
    return canBeCancelled(appointment);
}

export function canBeRated(appointment) {
    return appointment.status === AppointmentStatus.Completed.value && !appointment.review;
}

/** Status counts for a list, shaped like the `status` => `total` pluck. */
export function statusCounts(list) {
    return list.reduce((counts, appointment) => {
        counts[appointment.status] = (counts[appointment.status] ?? 0) + 1;

        return counts;
    }, {});
}

/** Sorted by the booking date, soonest first. */
export function byBookingDate(list) {
    return [...list].sort(
        (a, b) =>
            withTime(a.preferred_date, a.preferred_time) - withTime(b.preferred_date, b.preferred_time),
    );
}

/** Customer dashboard stats, ported from `Customer\DashboardController`. */
export function statsForUser(userId) {
    const mine = appointmentsForUser(userId);
    const count = (status) => mine.filter((appointment) => appointment.status === status).length;

    return {
        upcoming: upcomingAppointments(mine).length,
        completed: count(AppointmentStatus.Completed.value),
        cancelled: count(AppointmentStatus.Cancelled.value),
        pending: count(AppointmentStatus.Pending.value),
    };
}

export function reviewableAppointments(userId) {
    return appointmentsForUser(userId).filter(canBeRated);
}

/** Admin dashboard headline numbers, ported from `Admin\DashboardController`. */
export const dashboardStats = computed(() => {
    const completed = appointments.filter((a) => a.status === AppointmentStatus.Completed.value);
    const revenue = completed.reduce((sum, a) => sum + a.total_amount, 0);

    return {
        total_appointments: appointments.length,
        upcoming: upcomingAppointments().length,
        pending: appointments.filter((a) => a.status === AppointmentStatus.Pending.value).length,
        confirmed: appointments.filter((a) => a.status === AppointmentStatus.Confirmed.value).length,
        in_progress: appointments.filter((a) => a.status === AppointmentStatus.InProgress.value).length,
        completed: completed.length,
        cancelled: appointments.filter((a) => a.status === AppointmentStatus.Cancelled.value).length,
        total_revenue: revenue,
        average_rating: averageRating.value,
        reviews: reviews.value.length,
    };
});

/** Today's queue for the admin dashboard, in slot order. */
export const todaySchedule = computed(() => {
    const key = toDateInput(today());

    return byBookingDate(
        appointments.filter((appointment) => toDateInput(appointment.preferred_date) === key),
    );
});

export { isOpenOn };
