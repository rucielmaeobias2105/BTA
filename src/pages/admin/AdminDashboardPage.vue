<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import Badge from '@/components/ui/Badge.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StarRating from '@/components/ui/StarRating.vue';
import {
    averageRating,
    reviews,
    serviceNamesLabel,
    timeLabel,
    todaySchedule,
    upcomingAppointments,
} from '@/data/appointments';
import { AdminRole, AppointmentStatus, roleCan } from '@/data/enums';
import { lowStockItems } from '@/data/inventory';
import { revenueChart, statusBreakdown, summary } from '@/data/reports';
import { currentAdmin } from '@/lib/session';
import { toDateInput, today } from '@/lib/dates';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';

/**
 * Admin Flow 2 — Admin Dashboard.
 *
 * Ported from `admin/dashboard.blade.php` and `Admin\DashboardController`.
 * Every eager-loaded query became a lookup over the reactive demo collections;
 * the server-rendered bar chart and status donut are plain CSS here, driven by
 * the same `revenueChart(14)` / `statusBreakdown` aggregates the reports module
 * already exposes.
 */
const admin = computed(() => currentAdmin.value);
const role = computed(() => currentAdmin.value?.role ?? AdminRole.SuperAdmin.value);

const canViewReports = computed(() => roleCan(role.value, 'reports.view'));

const stats = computed(() => summary.value);

/** `@foreach` over the stat-card definition list in the Blade view. */
const statCards = computed(() => [
    {
        label: "Today's Appointments",
        value: stats.value.today_appointments,
        icon: 'calendar-days',
        text: 'text-primary',
        bg: 'bg-primary/10',
    },
    {
        label: 'Low Stock Alerts',
        value: stats.value.low_stock_count,
        icon: 'exclamation-triangle',
        text: 'text-status-low-stock',
        bg: 'bg-status-low-stock-bg',
    },
    {
        label: 'Completed (Month)',
        value: stats.value.completed_month,
        icon: 'check-circle',
        text: 'text-status-completed',
        bg: 'bg-status-completed-bg',
    },
    {
        label: 'Revenue (Month)',
        value: formatMoney(stats.value.revenue_month, 0),
        icon: 'banknotes',
        text: 'text-gold-dark',
        bg: 'bg-gold/20',
    },
]);

const chart = computed(() => revenueChart(14));

/** `$maxChart` — the tallest bar, with a floor of 1 so the division is safe. */
const maxChart = computed(() => {
    const totals = chart.value.map((point) => Number(point.total) || 0);

    return Math.max(0, ...totals) || 1;
});

/** The bar's inline height, the way the Blade view computed it. */
function barHeight(point) {
    return Math.max(2, Math.round((Number(point.total) / maxChart.value) * 100));
}

const statusTotal = computed(() => {
    const total = Object.values(statusBreakdown.value).reduce((sum, count) => sum + count, 0);

    return total || 1;
});

/** `AppointmentStatus::cases()` — every status, in declaration order. */
const statusRows = computed(() =>
    Object.values(AppointmentStatus).map((meta) => {
        const count = statusBreakdown.value[meta.value] ?? 0;

        return {
            meta,
            count,
            percent: Math.round((count / statusTotal.value) * 100),
        };
    }),
);

/** The bar colour each status bar used, kept from the Blade `@class` list. */
const STATUS_BAR = {
    pending: 'bg-status-pending',
    confirmed: 'bg-status-confirmed',
    in_progress: 'bg-status-progress',
    completed: 'bg-status-completed',
    cancelled: 'bg-status-cancelled',
};

const todayRows = computed(() => todaySchedule.value);

/** `->upcoming()->orderByDate()->orderByTime()->take(6)`. */
const upcoming = computed(() => upcomingAppointments().slice(0, 6));

/** `->lowStock()->orderBy('quantity')->take(6)`. */
const lowStock = computed(() =>
    [...lowStockItems.value].sort((a, b) => Number(a.quantity) - Number(b.quantity)).slice(0, 6),
);

/** `Review::latest()->take(4)` — newest review first. */
const recentReviews = computed(() =>
    [...reviews.value]
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 4),
);

const rating = computed(() => Math.round(averageRating.value * 10) / 10);

const todayKey = toDateInput(today());

function statusMeta(appointment) {
    return AppointmentStatus[appointment.status] ?? null;
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Overview"
            :title="`Welcome back, ${admin?.first_name ?? 'there'}`"
            description="Live snapshot of appointments, revenue and stock across the salon."
        >
            <template #actions>
                <router-link
                    :to="{ name: 'admin.appointments.index', query: { status: 'pending' } }"
                    class="btn-secondary btn-sm"
                >
                    Pending Requests
                    <span
                        v-if="stats.pending_appointments > 0"
                        class="rounded-pill bg-primary px-1.5 py-0.5 text-[10px] text-cream"
                    >{{ stats.pending_appointments }}</span>
                </router-link>
            </template>
        </PageHeader>

        <!-- Quick stats -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="card in statCards" :key="card.label" class="bta-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ card.label }}</p>
                        <p class="mt-2 truncate font-display text-3xl font-bold text-primary">{{ card.value }}</p>
                    </div>
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full" :class="[card.bg, card.text]">
                        <AppIcon :name="card.icon" class="h-5 w-5" />
                    </span>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <!-- Revenue chart -->
            <div class="xl:col-span-2">
                <CardPanel title="Revenue Chart" subtitle="Last 14 days (confirmed, in-progress and completed bookings).">
                    <template #actions>
                        <router-link
                            v-if="canViewReports"
                            :to="{ name: 'admin.reports.index' }"
                            class="text-xs font-medium text-primary underline underline-offset-2"
                        >Full report</router-link>
                    </template>

                    <div class="flex h-56 items-end gap-1.5 pt-4">
                        <div
                            v-for="point in chart"
                            :key="point.label"
                            class="group relative flex flex-1 flex-col items-center justify-end"
                            :title="`${point.label}: ₱${formatNumber(point.total, 2)} (${point.count} bookings)`"
                        >
                            <div
                                class="w-full rounded-t bg-gradient-to-t from-primary to-primary-light transition group-hover:from-gold group-hover:to-gold-light"
                                :style="{ height: `${barHeight(point)}%` }"
                            />
                        </div>
                    </div>

                    <div class="mt-2 flex justify-between text-[10px] text-ink-muted">
                        <span>{{ chart.length ? chart[0].label : '' }}</span>
                        <span>{{ chart.length ? chart[chart.length - 1].label : '' }}</span>
                    </div>
                </CardPanel>
            </div>

            <!-- Status breakdown -->
            <CardPanel title="Appointments by Status">
                <ul class="space-y-3">
                    <li v-for="row in statusRows" :key="row.meta.value">
                        <div class="mb-1.5 flex items-center justify-between text-sm">
                            <Badge :status="row.meta.badge" :label="row.meta.label" />
                            <span class="text-ink-muted">{{ row.count }} ({{ row.percent }}%)</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-pill bg-linen">
                            <div
                                class="h-full rounded-pill"
                                :class="STATUS_BAR[row.meta.value]"
                                :style="{ width: `${row.percent}%` }"
                            />
                        </div>
                    </li>
                </ul>
            </CardPanel>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <!-- Today's schedule -->
            <div class="xl:col-span-2">
                <CardPanel title="Today's Appointments" :subtitle="formatDate(today(), 'l, F j, Y')">
                    <template #actions>
                        <router-link
                            :to="{ name: 'admin.appointments.index', query: { from: todayKey, to: todayKey } }"
                            class="text-xs font-medium text-primary underline underline-offset-2"
                        >Manage</router-link>
                    </template>

                    <p v-if="todayRows.length === 0" class="text-sm text-ink-muted">Nothing scheduled today.</p>

                    <div v-else class="overflow-x-auto">
                        <table class="bta-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Customer</th>
                                    <th>Service(s)</th>
                                    <th>Status</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="appointment in todayRows" :key="appointment.id">
                                    <td class="whitespace-nowrap font-medium text-primary">{{ timeLabel(appointment.preferred_time) }}</td>
                                    <td>
                                        <router-link
                                            :to="{ name: 'admin.appointments.show', params: { id: appointment.id } }"
                                            class="font-medium text-primary hover:underline"
                                        >{{ appointment.customer_name }}</router-link>
                                    </td>
                                    <td class="max-w-xs truncate text-ink">{{ serviceNamesLabel(appointment) }}</td>
                                    <td>
                                        <Badge
                                            v-if="statusMeta(appointment)"
                                            :status="statusMeta(appointment).badge"
                                            :label="statusMeta(appointment).label"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap text-right font-medium text-primary">{{ formatMoney(appointment.total_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardPanel>
            </div>

            <!-- Low stock -->
            <CardPanel title="Low Stock Alerts" subtitle="Items at or below their reorder threshold.">
                <template #actions>
                    <router-link
                        :to="{ name: 'admin.tags.index' }"
                        class="text-xs font-medium text-primary underline underline-offset-2"
                    >Tag items</router-link>
                </template>

                <p v-if="lowStock.length === 0" class="text-sm text-ink-muted">All items are well stocked.</p>

                <ul v-else class="space-y-2.5">
                    <li
                        v-for="item in lowStock"
                        :key="item.id"
                        class="flex items-center justify-between gap-3 rounded-xl bg-linen/60 px-3.5 py-2.5"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">{{ item.name }}</p>
                            <p class="text-xs text-ink-muted">Reorder at {{ item.reorder_threshold }} {{ item.unit }}</p>
                        </div>
                        <Badge :status="item.status_tag" :label="item.status_tag.replaceAll('_', ' ')" />
                    </li>
                </ul>
            </CardPanel>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <!-- Upcoming -->
            <CardPanel title="Upcoming Appointments">
                <p v-if="upcoming.length === 0" class="text-sm text-ink-muted">No upcoming appointments.</p>

                <ul v-else class="divide-y divide-primary/8">
                    <li
                        v-for="appointment in upcoming"
                        :key="appointment.id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0">
                            <router-link
                                :to="{ name: 'admin.appointments.show', params: { id: appointment.id } }"
                                class="truncate text-sm font-medium text-primary hover:underline"
                            >{{ appointment.customer_name }}</router-link>
                            <p class="truncate text-xs text-ink-muted">{{ serviceNamesLabel(appointment) }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="whitespace-nowrap text-xs font-medium text-primary">
                                {{ formatDate(appointment.preferred_date, 'M j') }} &middot; {{ timeLabel(appointment.preferred_time) }}
                            </p>
                            <Badge
                                v-if="statusMeta(appointment)"
                                :status="statusMeta(appointment).badge"
                                :label="statusMeta(appointment).label"
                            />
                        </div>
                    </li>
                </ul>
            </CardPanel>

            <!-- Recent reviews -->
            <CardPanel title="Recent Reviews" :subtitle="`Average rating: ${rating} / 5`">
                <template #actions>
                    <router-link
                        :to="{ name: 'admin.reviews.index' }"
                        class="text-xs font-medium text-primary underline underline-offset-2"
                    >Moderate</router-link>
                </template>

                <p v-if="recentReviews.length === 0" class="text-sm text-ink-muted">No reviews yet.</p>

                <ul v-else class="space-y-3.5">
                    <li
                        v-for="review in recentReviews"
                        :key="review.id"
                        class="border-b border-primary/8 pb-3.5 last:border-0 last:pb-0"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-primary">{{ review.customer_name }}</p>
                            <StarRating :model-value="review.rating" :interactive="false" size="sm" />
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm text-ink-muted">{{ review.message }}</p>
                    </li>
                </ul>
            </CardPanel>
        </div>
    </div>
</template>
