<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import {
    appointments,
    serviceNamesLabel,
    statusCounts,
    timeLabel,
} from '@/data/appointments';
import {
    AdminRole,
    AppointmentStatus,
    ChangedBy,
    DownPaymentStatus,
    roleCan,
} from '@/data/enums';
import { findAdmin } from '@/data/admins';
import { currentAdmin, setFlash } from '@/lib/session';
import { fromDateInput, startOfDay, toDateInput } from '@/lib/dates';
import { formatDate, formatMoney, plural } from '@/lib/format';

/**
 * Admin Flow 3 — Appointment Management (list + filters).
 *
 * Ported from `admin/appointments/index.blade.php` and
 * `Admin\AppointmentController::index()`. The query-string filters are read from
 * `route.query` and written back with `router.replace()`, so the URLs the
 * Laravel version produced still work; `paginate(15)` became a client-side slice
 * with prev/next controls.
 */
const route = useRoute();
const router = useRouter();

/** `AppointmentStatus::options()` — a value => label map, as Blade received it. */
const statusOptions = computed(() =>
    Object.fromEntries(Object.values(AppointmentStatus).map((meta) => [meta.value, meta.label])),
);

const role = computed(() => currentAdmin.value?.role ?? AdminRole.SuperAdmin.value);
const canManage = computed(() => roleCan(role.value, 'appointments.manage'));

/* ------------------------------------------------------------------ */
/* Filters — the query string the Blade form submitted                 */
/* ------------------------------------------------------------------ */

const status = computed(() => {
    const requested = String(route.query.status ?? '');

    return requested in statusOptions ? requested : '';
});

const search = ref(String(route.query.search ?? ''));
const from = ref(String(route.query.from ?? ''));
const to = ref(String(route.query.to ?? ''));

// A status link or "Clear" rewrites the query from outside, so mirror it back
// into the inputs without re-triggering the debounce.
watch(
    () => route.query.search,
    (value) => {
        const next = String(value ?? '');

        if (next !== search.value) search.value = next;
    },
);

/** Merges a patch into the current query, dropping blanks, and rewrites the URL. */
function applyQuery(patch) {
    const next = { ...route.query, ...patch };

    Object.keys(next).forEach((key) => {
        if (next[key] === '' || next[key] === null || next[key] === undefined) delete next[key];
    });

    router.replace({ name: 'admin.appointments.index', query: next });
}

/** Free-text search is debounced so typing does not spam the history stack. */
let searchTimer = null;

watch(search, (value) => {
    window.clearTimeout(searchTimer);

    searchTimer = window.setTimeout(() => {
        if (value === String(route.query.search ?? '')) return;

        applyQuery({ search: value, page: '' });
    }, 300);
});

function filter() {
    applyQuery({ search: search.value, from: from.value, to: to.value, page: '' });
}

function clearFilters() {
    search.value = '';
    from.value = '';
    to.value = '';

    router.replace({ name: 'admin.appointments.index', query: {} });
}

const hasFilters = computed(
    () => Boolean(route.query.search || route.query.from || route.query.to || route.query.status),
);

/* ------------------------------------------------------------------ */
/* The list                                                             */
/* ------------------------------------------------------------------ */

const counts = computed(() => statusCounts(appointments));

const filtered = computed(() => {
    const term = String(route.query.search ?? '').trim().toLowerCase();

    let rows = appointments.filter((appointment) =>
        status.value ? appointment.status === status.value : true,
    );

    // `->betweenDates($from, $to)`
    if (route.query.from) {
        const first = startOfDay(fromDateInput(route.query.from));

        rows = rows.filter((appointment) => startOfDay(appointment.preferred_date) >= first);
    }

    if (route.query.to) {
        const last = startOfDay(fromDateInput(route.query.to));

        rows = rows.filter((appointment) => startOfDay(appointment.preferred_date) <= last);
    }

    if (term) {
        rows = rows.filter((appointment) =>
            [appointment.reference_number, appointment.customer_name, appointment.customer_phone, appointment.customer_email].some(
                (field) => String(field ?? '').toLowerCase().includes(term),
            ),
        );
    }

    // `->orderByDesc('preferred_date')->orderByDesc('preferred_time')`
    return [...rows].sort((a, b) => {
        const left = toDateInput(b.preferred_date) + b.preferred_time;
        const right = toDateInput(a.preferred_date) + a.preferred_time;

        return left < right ? -1 : left > right ? 1 : 0;
    });
});

/* ------------------------------------------------------------------ */
/* Pagination — `->paginate(15)`                                       */
/* ------------------------------------------------------------------ */

const PER_PAGE = 10;

const page = computed(() => {
    const requested = Number(route.query.page ?? 1);

    return Number.isInteger(requested) && requested > 0 ? requested : 1;
});

const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)));

const rows = computed(() => {
    const current = Math.min(page.value, lastPage.value);

    return filtered.value.slice((current - 1) * PER_PAGE, current * PER_PAGE);
});

const rangeLabel = computed(() => {
    if (filtered.value.length === 0) return '0 results';

    const first = (Math.min(page.value, lastPage.value) - 1) * PER_PAGE + 1;

    return `Showing ${first}–${Math.min(first + PER_PAGE - 1, filtered.value.length)} of ${plural(filtered.value.length, 'appointment')}`;
});

function goToPage(next) {
    if (next < 1 || next > lastPage.value) return;

    applyQuery({ page: next === 1 ? '' : String(next) });
}

/* ------------------------------------------------------------------ */
/* Approve / Decline — `Admin\AppointmentController::updateStatus()`    */
/* ------------------------------------------------------------------ */

function changeStatus(appointment, target) {
    const previous = appointment.status;
    const now = new Date();

    if (target === AppointmentStatus.Confirmed.value && !appointment.confirmed_at) {
        appointment.confirmed_at = now;
    }

    if (target === AppointmentStatus.InProgress.value && !appointment.started_at) {
        appointment.started_at = now;
    }

    if (target === AppointmentStatus.Completed.value) appointment.completed_at = now;

    // Cancelling stamps the time; anything else clears it, exactly as the update did.
    appointment.cancelled_at = target === AppointmentStatus.Cancelled.value ? now : null;

    appointment.status = target;

    appointment.history.push({
        id: Math.max(0, ...appointments.flatMap((row) => row.history.map((entry) => entry.id))) + 1,
        from_status: previous,
        to_status: target,
        changed_by: ChangedBy.Admin.value,
        changed_by_id: currentAdmin.value?.id ?? null,
        changed_by_name: currentAdmin.value?.full_name ?? 'Admin',
        note: null,
    });

    setFlash(`Appointment ${appointment.reference_number} marked as ${AppointmentStatus[target].label}.`);
}

function statusMeta(appointment) {
    return AppointmentStatus[appointment.status] ?? null;
}

function downPaymentMeta(appointment) {
    return DownPaymentStatus[appointment.down_payment_status] ?? null;
}

/** `Appointment::preferredStylist?->first_name ?? '—'`. */
function stylistFirstName(appointment) {
    if (!appointment.preferred_stylist_id) return '—';

    return findAdmin(appointment.preferred_stylist_id)?.first_name ?? '—';
}

const statusTabs = computed(() => [
    { value: '', label: 'All' },
    ...Object.entries(statusOptions).map(([value, label]) => ({ value, label })),
]);
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Booking Management"
            title="Appointments"
            description="Review, approve, decline and update every booking."
        />

        <!-- Status tabs -->
        <div class="mb-5 flex flex-wrap gap-2">
            <router-link
                v-for="tab in statusTabs"
                :key="tab.value || 'all'"
                :to="{
                    name: 'admin.appointments.index',
                    query: tab.value ? { status: tab.value } : {},
                }"
                class="rounded-pill px-3.5 py-1.5 text-sm font-medium transition"
                :class="status === tab.value ? 'bg-primary text-cream' : 'bg-cream text-ink hover:bg-linen'"
            >
                {{ tab.label }}
                <span v-if="tab.value && (counts[tab.value] ?? 0) > 0" class="ml-1 opacity-70">
                    {{ counts[tab.value] }}
                </span>
            </router-link>
        </div>

        <!-- Filters -->
        <CardPanel class="mb-6" :padded="false">
            <form class="p-5" novalidate @submit.prevent="filter">
                <div class="grid gap-4 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <FormInput
                            v-model="search"
                            name="search"
                            label="Search"
                            placeholder="Reference, name, phone or email"
                        />
                    </div>
                    <div class="md:col-span-3">
                        <FormInput v-model="from" name="from" type="date" label="From" />
                    </div>
                    <div class="md:col-span-3">
                        <FormInput v-model="to" name="to" type="date" label="To" />
                    </div>
                    <div class="flex items-end gap-2 md:col-span-2">
                        <button type="submit" class="btn-primary flex-1">Filter</button>
                        <router-link
                            v-if="hasFilters"
                            :to="{ name: 'admin.appointments.index' }"
                            class="btn-ghost"
                        >Clear</router-link>
                    </div>
                </div>
            </form>
        </CardPanel>

        <EmptyState
            v-if="filtered.length === 0"
            title="No appointments found"
            description="Try adjusting your filters."
        />

        <div v-else class="bta-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="bta-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Service(s)</th>
                            <th>Date &amp; Time</th>
                            <th>Stylist</th>
                            <th>Down Payment</th>
                            <th>Status</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="appointment in rows" :key="appointment.id">
                            <td class="whitespace-nowrap">
                                <router-link
                                    :to="{ name: 'admin.appointments.show', params: { id: appointment.id } }"
                                    class="font-mono text-xs font-semibold text-primary hover:underline"
                                >
                                    {{ appointment.reference_number }}
                                </router-link>
                            </td>
                            <td>
                                <p class="whitespace-nowrap font-medium text-ink">{{ appointment.customer_name }}</p>
                                <p class="whitespace-nowrap text-xs text-ink-muted">{{ appointment.customer_phone }}</p>
                            </td>
                            <td class="max-w-xs">
                                <p class="truncate text-ink">{{ serviceNamesLabel(appointment) }}</p>
                            </td>
                            <td class="whitespace-nowrap">
                                <p class="text-ink">{{ formatDate(appointment.preferred_date, 'M j, Y') }}</p>
                                <p class="text-xs text-ink-muted">{{ timeLabel(appointment.preferred_time) }}</p>
                            </td>
                            <td class="whitespace-nowrap text-ink">{{ stylistFirstName(appointment) }}</td>
                            <td>
                                <Badge
                                    v-if="downPaymentMeta(appointment)"
                                    :status="downPaymentMeta(appointment).badge"
                                    :label="downPaymentMeta(appointment).label"
                                />
                            </td>
                            <td>
                                <Badge
                                    v-if="statusMeta(appointment)"
                                    :status="statusMeta(appointment).badge"
                                    :label="statusMeta(appointment).label"
                                />
                            </td>
                            <td class="whitespace-nowrap text-right font-medium text-primary">
                                {{ formatMoney(appointment.total_amount) }}
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    <template v-if="canManage && appointment.status === AppointmentStatus.Pending.value">
                                        <button
                                            type="button"
                                            class="btn-primary btn-sm"
                                            title="Approve"
                                            @click="changeStatus(appointment, AppointmentStatus.Confirmed.value)"
                                        >Approve</button>
                                        <button
                                            type="button"
                                            class="btn-danger btn-sm"
                                            title="Decline"
                                            @click="changeStatus(appointment, AppointmentStatus.Cancelled.value)"
                                        >Decline</button>
                                    </template>

                                    <router-link
                                        :to="{ name: 'admin.appointments.show', params: { id: appointment.id } }"
                                        class="btn-ghost btn-sm"
                                    >Manage</router-link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="filtered.length > 0" class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-ink-muted">{{ rangeLabel }}</p>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="btn-ghost btn-sm"
                    :disabled="page <= 1"
                    @click="goToPage(page - 1)"
                >Previous</button>

                <span class="text-sm text-ink-muted">Page {{ Math.min(page, lastPage) }} of {{ lastPage }}</span>

                <button
                    type="button"
                    class="btn-ghost btn-sm"
                    :disabled="page >= lastPage"
                    @click="goToPage(page + 1)"
                >Next</button>
            </div>
        </div>
    </div>
</template>
