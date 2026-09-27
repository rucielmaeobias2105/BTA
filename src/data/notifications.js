import { reactive, computed } from 'vue';
import { addDays, daysBetween, today } from '@/lib/dates';
import { formatDate, truncate } from '@/lib/format';
import { AppointmentStatus } from './enums';
import { appointments, serviceNamesLabel, timeLabel } from './appointments';
import { activePromos } from './promos';
import { validityLabel } from './promos';

/**
 * Customer notifications.
 *
 * The notifications table was written by the `App\Notifications\*` classes. Each
 * one returns the same payload shape, so the bell and the notification list
 * could render all of them uniformly:
 *
 *   { title, message, icon, tone, url, read_at, created_at }
 *
 * With no queue, the rows are derived from the demo customer's own appointments
 * and the active promos, which is exactly what would have generated them.
 */

let nextId = 1;

function makeNotification(payload, { createdAt, read = true }) {
    return reactive({
        id: nextId++,
        ...payload,
        read_at: read ? createdAt : null,
        created_at: createdAt,
    });
}

/** The appointment-status notifications, keyed by the state they announce. */
const STATUS_NOTIFICATIONS = {
    [AppointmentStatus.Pending.value]: {
        title: 'Booking request received',
        icon: 'clock',
        tone: 'pending',
        message: (appointment) =>
            `We have received your request for ${serviceNamesLabel(appointment)}. It is not confirmed until our team verifies your down payment.`,
    },
    [AppointmentStatus.Confirmed.value]: {
        title: 'Your appointment is confirmed',
        icon: 'check',
        tone: 'confirmed',
        message: (appointment) =>
            `We look forward to welcoming you for ${serviceNamesLabel(appointment)}.`,
    },
    [AppointmentStatus.InProgress.value]: {
        title: 'Your appointment is in progress',
        icon: 'sparkles',
        tone: 'progress',
        message: (appointment) => `Your ${serviceNamesLabel(appointment)} session has started. Enjoy!`,
    },
    [AppointmentStatus.Completed.value]: {
        title: 'Thank you for visiting',
        icon: 'heart',
        tone: 'completed',
        message: (appointment) =>
            `We hope you loved your ${serviceNamesLabel(appointment)}. Please rate your visit to help other guests.`,
    },
    [AppointmentStatus.Cancelled.value]: {
        title: 'Your appointment was cancelled',
        icon: 'x',
        tone: 'cancelled',
        message: (appointment) =>
            `Your ${serviceNamesLabel(appointment)} appointment on ${formatDate(appointment.preferred_date, 'M j, Y')} has been cancelled. Your down payment is handled according to our cancellation policy.`,
    },
};

function seedNotifications() {
    const rows = [];

    // Promo announcements (Admin Flow 13) are sent to everyone.
    activePromos.value.forEach((promo) => {
        rows.push(
            makeNotification(
                {
                    title: `Promo: ${promo.title}`,
                    message: truncate(promo.description, 140),
                    icon: 'gift',
                    tone: 'gold',
                    url: '/services',
                    promo_id: promo.id,
                },
                { createdAt: addDays(today(), -1), read: false },
            ),
        );
    });

    // One row per appointment the demo customer owns, oldest first.
    appointments
        .filter((appointment) => appointment.user_id === 2)
        .sort((a, b) => new Date(b.preferred_date) - new Date(a.preferred_date))
        .forEach((appointment) => {
            const template = STATUS_NOTIFICATIONS[appointment.status];

            if (!template) return;

            const createdAt = appointment.completed_at
                ?? appointment.cancelled_at
                ?? appointment.started_at
                ?? addDays(appointment.preferred_date, -1);

            rows.push(
                makeNotification(
                    {
                        title: template.title,
                        message: template.message(appointment),
                        icon: template.icon,
                        tone: template.tone,
                        url: `/appointments/${appointment.id}`,
                        appointment_id: appointment.id,
                        reference_number: appointment.reference_number,
                    },
                    {
                        createdAt,
                        // The most recent one stays unread so the bell badge shows.
                        read: daysBetween(createdAt, today()) > 0,
                    },
                ),
            );
        });

    return rows;
}

export const notifications = reactive(seedNotifications());

/** Newest first, matching the notifications table's default order. */
export const orderedNotifications = computed(() =>
    [...notifications].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)),
);

export const unreadNotifications = computed(() => notifications.filter((entry) => !entry.read_at));

export const unreadNotificationCount = computed(() => unreadNotifications.value.length);

export function filterNotifications(filter = 'all') {
    return filter === 'unread' ? unreadNotifications.value : orderedNotifications.value;
}

export function markAsRead(id) {
    const entry = notifications.find((row) => String(row.id) === String(id));

    if (entry && !entry.read_at) entry.read_at = new Date();
}

export function markAllAsRead() {
    notifications.forEach((entry) => {
        if (!entry.read_at) entry.read_at = new Date();
    });
}

/** `"Oct 3, 2026 at 2:30 PM"` — the timestamp under each row. */
export function createdLabel(entry) {
    return `${formatDate(entry.created_at, 'M j, Y')} at ${formatDate(entry.created_at, 'g:i A')}`;
}

/** Convenience for pages that want the appointment time in the copy. */
export function appointmentTimeLabel(appointment) {
    return `${timeLabel(appointment.preferred_time)}`;
}
