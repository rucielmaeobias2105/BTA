import { computed } from 'vue';
import {
    addDays,
    isWithin,
    startOfDay,
    subMonths,
    subWeeks,
    subYears,
    toDateInput,
    today,
} from '@/lib/dates';
import { formatDate } from '@/lib/format';
import { AppointmentStatus } from './enums';
import { appointments } from './appointments';
import { inventoryForService, inventoryItems, lowStockItems, soldOutItems } from './inventory';
import { unreadMessages } from './messages';

/**
 * Read-only aggregation.
 *
 * Ported from `App\Services\ReportService`. Revenue counts confirmed,
 * in-progress and completed appointments — the statuses that represent realised
 * income. The SQL grouping became array folding, which is the same arithmetic
 * with one less indirection.
 */

/** Statuses that represent realised revenue. */
export const REVENUE_STATUSES = [
    AppointmentStatus.Confirmed.value,
    AppointmentStatus.InProgress.value,
    AppointmentStatus.Completed.value,
];

const realised = (list) => list.filter((appointment) => REVENUE_STATUSES.includes(appointment.status));

function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function endOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0, 23, 59, 59);
}

/** Headline numbers for the dashboard stat cards. */
export const summary = computed(() => {
    const now = today();
    const monthStart = startOfMonth(now);
    const monthEnd = endOfMonth(now);
    const key = toDateInput(now);

    const inMonth = appointments.filter((appointment) => isWithin(appointment.preferred_date, monthStart, monthEnd));
    const todayRows = appointments.filter((appointment) => toDateInput(appointment.preferred_date) === key);

    const revenue = (list) => list.reduce((sum, appointment) => sum + Number(appointment.total_amount), 0);

    return {
        today_appointments: todayRows.filter((a) => a.status !== AppointmentStatus.Cancelled.value).length,
        pending_appointments: appointments.filter((a) => a.status === AppointmentStatus.Pending.value).length,
        completed_month: inMonth.filter((a) => a.status === AppointmentStatus.Completed.value).length,
        low_stock_count: lowStockItems.value.length,
        sold_out_count: soldOutItems.value.length,
        revenue_month: revenue(realised(inMonth)),
        revenue_today: revenue(realised(todayRows)),
        unread_messages: unreadMessages.value.length,
    };
});

/** Revenue per day for the dashboard bar chart, oldest first. */
export function revenueChart(days = 14) {
    const from = addDays(today(), -(days - 1));
    const grouped = new Map();

    realised(appointments).forEach((appointment) => {
        const key = toDateInput(appointment.preferred_date);
        const entry = grouped.get(key) ?? { total: 0, count: 0 };

        entry.total += Number(appointment.total_amount);
        entry.count += 1;

        grouped.set(key, entry);
    });

    const series = [];

    for (let index = 0; index < days; index += 1) {
        const date = addDays(from, index);
        const entry = grouped.get(toDateInput(date)) ?? { total: 0, count: 0 };

        series.push({
            label: formatDate(date, 'M j'),
            total: entry.total,
            count: entry.count,
        });
    }

    return series;
}

/** Per-status counts for the dashboard donut. */
export const statusBreakdown = computed(() =>
    appointments.reduce((counts, appointment) => {
        counts[appointment.status] = (counts[appointment.status] ?? 0) + 1;

        return counts;
    }, {}),
);

/** Revenue per service over a date range, highest first. */
export function revenueByService(from, to, serviceId = null) {
    const rows = new Map();

    realised(appointments)
        .filter((appointment) => isWithin(appointment.preferred_date, from, to))
        .forEach((appointment) => {
            appointment.lines.forEach((line) => {
                if (serviceId && String(line.service_id) !== String(serviceId)) return;

                const entry = rows.get(line.service_name) ?? { service: line.service_name, bookings: 0, quantity: 0, revenue: 0 };

                entry.bookings += 1;
                entry.quantity += line.quantity;
                entry.revenue += line.price * line.quantity;

                rows.set(line.service_name, entry);
            });
        });

    return [...rows.values()].sort((a, b) => b.revenue - a.revenue);
}

/** Item consumption implied by bookings (`quantity_per_service` x bookings). */
export function itemUsage(from, to, itemId = null) {
    const used = new Map();

    realised(appointments)
        .filter((appointment) => isWithin(appointment.preferred_date, from, to))
        .forEach((appointment) => {
            appointment.lines.forEach((line) => {
                inventoryForService(line.service_name).forEach(({ item, quantity_per_service }) => {
                    if (itemId && String(item.id) !== String(itemId)) return;

                    const amount = quantity_per_service * line.quantity;

                    used.set(item.sku, (used.get(item.sku) ?? 0) + amount);
                });
            });
        });

    return [...used.entries()]
        .map(([sku, amount]) => {
            const item = inventoryItems.find((row) => row.sku === sku);

            return {
                item: item?.name ?? sku,
                unit: item?.unit ?? '',
                used: Math.round(amount * 100) / 100,
                remaining: Number(item?.quantity ?? 0),
            };
        })
        .sort((a, b) => b.used - a.used);
}

function periodKey(granularity, date) {
    switch (granularity) {
        case 'weekly':
            return toDateInput(startOfDay(date) - ((date.getDay() + 6) % 7));
        case 'monthly':
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        case 'annual':
            return String(date.getFullYear());
        default:
            return toDateInput(date);
    }
}

function periodLabel(granularity, date) {
    switch (granularity) {
        case 'weekly':
            return `Week of ${formatDate(date, 'M j, Y')}`;
        case 'monthly':
            return formatDate(date, 'F Y');
        case 'annual':
            return formatDate(date, 'Y');
        default:
            return formatDate(date, 'M j, Y');
    }
}

/** Bucketed revenue for a report granularity, oldest bucket first. */
export function totalsByPeriod(granularity, from, to) {
    const buckets = new Map();

    realised(appointments)
        .filter((appointment) => isWithin(appointment.preferred_date, from, to))
        .forEach((appointment) => {
            const key = periodKey(granularity, appointment.preferred_date);
            const bucket = buckets.get(key) ?? { revenue: 0, bookings: 0, date: appointment.preferred_date };

            bucket.revenue += Number(appointment.total_amount);
            bucket.bookings += 1;

            buckets.set(key, bucket);
        });

    return [...buckets.entries()]
        .sort(([a], [b]) => (a < b ? -1 : 1))
        .map(([period, bucket]) => ({
            period,
            label: periodLabel(granularity, bucket.date),
            revenue: Math.round(bucket.revenue * 100) / 100,
            bookings: bucket.bookings,
        }));
}

/** Applies a report granularity to a date range, swapping reversed bounds. */
export function resolveRange(from, to, type) {
    const end = to ? startOfDay(to) : today();

    let start;

    if (from) {
        start = startOfDay(from);
    } else {
        switch (type) {
            case 'daily':
                start = addDays(end, -6);
                break;
            case 'weekly':
                start = subWeeks(end, 3);
                break;
            case 'monthly':
                start = startOfMonth(subMonths(end, 5));
                break;
            case 'annual':
                start = new Date(end.getFullYear() - 2, 0, 1);
                break;
            default:
                start = addDays(end, -6);
        }
    }

    return start > end ? { from: end, to: start } : { from: start, to: end };
}

export const GRANULARITIES = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'annual', label: 'Annual' },
];

export { subYears };
