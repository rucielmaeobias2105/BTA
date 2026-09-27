<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { GRANULARITIES, itemUsage, resolveRange, revenueByService, totalsByPeriod } from '@/data/reports';
import { services } from '@/data/services';
import { inventoryItems } from '@/data/inventory';
import { fromDateInput, toDateInput } from '@/lib/dates';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';

/**
 * Admin Flow 12 — Sales / Usage Reports.
 *
 * Ported from `admin/reports/index.blade.php` and `ReportController`. Every
 * figure on screen comes from `src/data/reports.js`, which is the ported
 * `ReportService`: the SQL grouping became array folding, and the hand-drawn
 * bar chart stayed a hand-drawn bar chart. The GET filter form became router
 * query state, and `export` became a client-side CSV download of exactly the
 * rows currently displayed.
 */
const route = useRoute();
const router = useRouter();

const typeOptions = Object.fromEntries(GRANULARITIES.map((row) => [row.value, row.label]));

/** `ReportController::TYPES`, with anything unrecognised falling back to daily. */
const type = computed(() =>
    GRANULARITIES.some((row) => row.value === route.query.type) ? String(route.query.type) : 'daily',
);

const serviceOptions = computed(() =>
    Object.fromEntries(
        [...services]
            .sort((a, b) => a.name.localeCompare(b.name))
            .map((service) => [service.id, service.name]),
    ),
);

const itemOptions = computed(() =>
    Object.fromEntries(
        [...inventoryItems]
            .sort((a, b) => a.name.localeCompare(b.name))
            .map((item) => [item.id, `${item.name} (${item.unit})`]),
    ),
);

/** `ReportService::resolveRange()` — applies the granularity when a bound is missing. */
const range = computed(() =>
    resolveRange(
        route.query.from ? fromDateInput(route.query.from) : null,
        route.query.to ? fromDateInput(route.query.to) : null,
        type.value,
    ),
);

const totals = computed(() => totalsByPeriod(type.value, range.value.from, range.value.to));
const byService = computed(() => revenueByService(range.value.from, range.value.to, route.query.service_id || null));
const usage = computed(() => itemUsage(range.value.from, range.value.to, route.query.item_id || null));

const grandTotal = computed(() => totals.value.reduce((total, row) => total + row.revenue, 0));
const totalBookings = computed(() => totals.value.reduce((total, row) => total + row.bookings, 0));
const maxPeriodRevenue = computed(() => totals.value.reduce((max, row) => Math.max(max, row.revenue), 0));

/**
 * The Blade GET form posted the already-resolved dates, so the controls are
 * seeded from the resolved range rather than from the raw query.
 */
const filters = computed(() => ({
    type: type.value,
    from: toDateInput(range.value.from),
    to: toDateInput(range.value.to),
    service_id: String(route.query.service_id ?? ''),
    item_id: String(route.query.item_id ?? ''),
}));

const draft = ref({ ...filters.value });

watch(filters, (value) => {
    draft.value = { ...value };
});

function applyFilters() {
    router.replace({
        name: 'admin.reports.index',
        query: {
            type: draft.value.type,
            from: draft.value.from,
            to: draft.value.to,
            ...(draft.value.service_id ? { service_id: draft.value.service_id } : {}),
            ...(draft.value.item_id ? { item_id: draft.value.item_id } : {}),
        },
    });
}

function reset() {
    router.replace({ name: 'admin.reports.index' });
}

/** `max(2, round($row['revenue'] / $maxPeriodRevenue * 100))` */
function barHeight(revenue) {
    return maxPeriodRevenue.value > 0 ? Math.max(2, Math.round((revenue / maxPeriodRevenue.value) * 100)) : 2;
}

/** `rtrim(rtrim(number_format($n, 2), '0'), '.')` — 12.00 becomes "12", 0.50 "0.5". */
function trimNumber(value) {
    return formatNumber(value, 2).replace(/0+$/, '').replace(/\.$/, '');
}

/* ------------------------------------------------------------------ */
/* CSV export                                                          */
/* ------------------------------------------------------------------ */

function csvCell(value) {
    const text = String(value ?? '');

    return /[",\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text;
}

function csvRow(cells) {
    return cells.map(csvCell).join(',');
}

/** The same figures `ReportController::export()` streamed, minus the HTTP layer. */
function exportCsv() {
    const lines = [
        csvRow([`Balai ti Arjud — ${typeOptions[type.value]} Report`]),
        csvRow(['Range', `${toDateInput(range.value.from)} to ${toDateInput(range.value.to)}`]),
        csvRow(['Generated', formatDate(new Date(), 'Y-m-d H:i:s')]),
        csvRow([]),
        csvRow(['SUMMARY BY PERIOD']),
        csvRow(['Period', 'Bookings', 'Revenue (PHP)']),
        ...totals.value.map((row) => csvRow([row.label, row.bookings, formatNumber(row.revenue, 2)])),
        csvRow([]),
        csvRow(['REVENUE BY SERVICE']),
        csvRow(['Service', 'Bookings', 'Quantity', 'Revenue (PHP)']),
        ...byService.value.map((row) => csvRow([row.service, row.bookings, row.quantity, formatNumber(row.revenue, 2)])),
        csvRow([]),
        csvRow(['ITEM USAGE']),
        csvRow(['Item', 'Unit', 'Used', 'Remaining']),
        ...usage.value.map((row) => csvRow([row.item, row.unit, row.used, row.remaining])),
    ];

    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const stamp = (date) => formatDate(date, 'Ymd');

    link.href = url;
    link.download = `bta-${type.value}-report-${stamp(range.value.from)}-${stamp(range.value.to)}.csv`;

    document.body.appendChild(link);
    link.click();
    link.remove();

    URL.revokeObjectURL(url);
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Analytics"
            title="Sales & Usage Reports"
            description="Revenue and item consumption for a chosen date range, bucketed by your chosen period."
        >
            <template #actions>
                <button type="button" class="btn-secondary btn-sm" @click="exportCsv">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Export CSV
                </button>
            </template>
        </PageHeader>

        <!-- Filters -->
        <form class="bta-card mb-6 p-5" novalidate @submit.prevent="applyFilters">
            <div class="grid gap-4 md:grid-cols-12">
                <div class="md:col-span-2">
                    <FormSelect v-model="draft.type" name="type" label="Report Type" :options="typeOptions" />
                </div>
                <div class="md:col-span-3">
                    <FormInput v-model="draft.from" name="from" type="date" label="From" />
                </div>
                <div class="md:col-span-3">
                    <FormInput v-model="draft.to" name="to" type="date" label="To" />
                </div>
                <div class="md:col-span-2">
                    <FormSelect
                        v-model="draft.service_id"
                        name="service_id"
                        label="Filter by Service"
                        include-blank
                        blank-label="All Services"
                        :options="serviceOptions"
                    />
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="btn-primary flex-1">Run Report</button>
                    <button type="button" class="btn-ghost" @click="reset">Reset</button>
                </div>
            </div>

            <div class="mt-4 border-t border-primary/10 pt-4">
                <div class="max-w-sm">
                    <FormSelect
                        v-model="draft.item_id"
                        name="item_id"
                        label="Filter by Item (usage table only)"
                        include-blank
                        blank-label="All Items"
                        :options="itemOptions"
                    />
                </div>
            </div>
        </form>

        <!-- Headline figures -->
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="bta-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Revenue</p>
                <p class="mt-1.5 font-display text-3xl font-bold text-primary">{{ formatMoney(grandTotal) }}</p>
                <p class="mt-1 text-xs text-ink-muted">
                    {{ formatDate(range.from, 'M j, Y') }} – {{ formatDate(range.to, 'M j, Y') }}
                </p>
            </div>
            <div class="bta-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Bookings</p>
                <p class="mt-1.5 font-display text-3xl font-bold text-primary">{{ totalBookings }}</p>
                <p class="mt-1 text-xs text-ink-muted">Confirmed, in-progress and completed</p>
            </div>
            <div class="bta-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Average Booking</p>
                <p class="mt-1.5 font-display text-3xl font-bold text-primary">
                    {{ formatMoney(totalBookings > 0 ? grandTotal / totalBookings : 0) }}
                </p>
            </div>
        </div>

        <!-- Period chart -->
        <CardPanel title="Revenue by Period" :subtitle="`${typeOptions[type]} buckets`">
            <p v-if="totals.length === 0" class="text-sm text-ink-muted">No revenue recorded in this range.</p>

            <div v-else class="flex h-56 items-end gap-1.5 pt-4">
                <div
                    v-for="row in totals"
                    :key="row.period"
                    class="group relative flex flex-1 flex-col items-center justify-end"
                    :title="`${row.label}: ${formatMoney(row.revenue)} (${row.bookings} bookings)`"
                >
                    <div
                        class="w-full rounded-t bg-gradient-to-t from-primary to-primary-light transition group-hover:from-gold group-hover:to-gold-light"
                        :style="{ height: `${barHeight(row.revenue)}%` }"
                    />
                </div>
            </div>
        </CardPanel>

        <div class="mt-6 grid gap-6 2xl:grid-cols-2">
            <!-- Revenue by service -->
            <CardPanel title="Revenue by Service" subtitle="Ranked by total revenue.">
                <p v-if="byService.length === 0" class="text-sm text-ink-muted">No service revenue in this range.</p>

                <div v-else class="overflow-x-auto">
                    <table class="bta-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th class="text-center">Bookings</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in byService" :key="row.service">
                                <td class="text-ink">{{ row.service }}</td>
                                <td class="text-center text-ink">{{ row.bookings }}</td>
                                <td class="text-center text-ink">{{ row.quantity }}</td>
                                <td class="whitespace-nowrap text-right font-medium text-primary">{{ formatMoney(row.revenue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardPanel>

            <!-- Item usage -->
            <CardPanel title="Item Usage" subtitle="Consumption implied by bookings, with stock remaining.">
                <p v-if="usage.length === 0" class="text-sm text-ink-muted">No linked item usage in this range.</p>

                <div v-else class="overflow-x-auto">
                    <table class="bta-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-right">Used</th>
                                <th class="text-right">Remaining</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in usage"
                                :key="row.item"
                                :class="{ 'bg-status-low-stock-bg/25': row.remaining <= 0 }"
                            >
                                <td class="text-ink">{{ row.item }}</td>
                                <td class="whitespace-nowrap text-right font-medium text-primary">
                                    {{ trimNumber(row.used) }} {{ row.unit }}
                                </td>
                                <td class="whitespace-nowrap text-right text-ink">
                                    {{ trimNumber(row.remaining) }} {{ row.unit }}
                                </td>
                                <td class="text-center">
                                    <Badge
                                        :status="row.remaining <= 0 ? 'sold_out' : 'confirmed'"
                                        :label="row.remaining <= 0 ? 'Depleted' : 'In stock'"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardPanel>
        </div>

        <!-- Period totals table -->
        <CardPanel title="Summary Table" class="mt-6">
            <p v-if="totals.length === 0" class="text-sm text-ink-muted">Nothing to summarise for this range.</p>

            <div v-else class="overflow-x-auto">
                <table class="bta-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th class="text-center">Bookings</th>
                            <th class="text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in totals" :key="row.period">
                            <td class="text-ink">{{ row.label }}</td>
                            <td class="text-center text-ink">{{ row.bookings }}</td>
                            <td class="whitespace-nowrap text-right font-medium text-primary">{{ formatMoney(row.revenue) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-linen/60">
                            <td class="font-semibold text-primary">Total</td>
                            <td class="text-center font-semibold text-primary">{{ totalBookings }}</td>
                            <td class="whitespace-nowrap text-right font-display text-lg font-bold text-primary">{{ formatMoney(grandTotal) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </CardPanel>
    </div>
</template>
