<script setup>
import { computed, provide } from 'vue';
import { useRoute } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StarRating from '@/components/ui/StarRating.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import { appointments, findAppointment, serviceNamesLabel, timeLabel, totalDuration } from '@/data/appointments';
import {
    AdminRole,
    AppointmentStatus,
    ChangedBy,
    DownPaymentStatus,
    roleCan,
} from '@/data/enums';
import { findAdmin } from '@/data/admins';
import { findUser } from '@/data/users';
import { currentAdmin, setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';
import { formatDate, formatMoney } from '@/lib/format';

/**
 * Admin Flow 3 — manage a single appointment.
 *
 * Ported from `admin/appointments/show.blade.php` and the three `PATCH` actions
 * on `Admin\AppointmentController` (`updateStatus`, `updateNotes`,
 * `updateDownPayment`). Each of those became a local mutation on the reactive
 * appointment followed by the same `session('status')` flash.
 */
const route = useRoute();

const appointment = computed(() => findAppointment(route.params.id));

const role = computed(() => currentAdmin.value?.role ?? AdminRole.SuperAdmin.value);
const canManage = computed(() => roleCan(role.value, 'appointments.manage'));

/** `AppointmentStatus::options()` / `DownPaymentStatus::options()`. */
const statusOptions = computed(() =>
    Object.fromEntries(Object.values(AppointmentStatus).map((meta) => [meta.value, meta.label])),
);

const downPaymentOptions = computed(() =>
    Object.fromEntries(Object.values(DownPaymentStatus).map((meta) => [meta.value, meta.label])),
);

/** The four transitions the status form offered, with their button styling. */
const STATUS_ACTIONS = [
    { value: AppointmentStatus.Confirmed.value, label: 'Approve / Confirm', class: 'btn-primary' },
    { value: AppointmentStatus.InProgress.value, label: 'Start (In Progress)', class: 'btn-gold' },
    { value: AppointmentStatus.Completed.value, label: 'Mark Completed', class: 'btn-ghost' },
    { value: AppointmentStatus.Cancelled.value, label: 'Decline / Cancel', class: 'btn-danger' },
];

/**
 * The status form and the down-payment form both validate through
 * `createForm`; only one errors bag can be provided to the whole page, so the
 * down-payment select is handed its message through the `error` prop instead.
 */
const form = createForm({
    initial: {
        status: appointment.value?.status ?? AppointmentStatus.Pending.value,
        admin_notes: appointment.value?.admin_notes ?? '',
    },
    rules: {
        status: ['required', `in:${Object.keys(statusOptions.value).join(',')}`],
        admin_notes: ['nullable', 'string', 'max:2000'],
    },
});

provide('form-errors', form.errors);

const downPayment = createForm({
    initial: { down_payment_status: appointment.value?.down_payment_status ?? '' },
    rules: {
        down_payment_status: ['required', `in:${Object.keys(downPaymentOptions.value).join(',')}`],
    },
});

/** `Appointment::getDateTimeLabelAttribute()` — "Apr 18, 2026 at 9:30 AM". */
const dateTimeLabel = computed(() => {
    if (!appointment.value) return '';

    return `${formatDate(appointment.value.preferred_date, 'M j, Y')} at ${timeLabel(appointment.value.preferred_time)}`;
});

const statusMeta = computed(() => (appointment.value ? AppointmentStatus[appointment.value.status] : null));
const downPaymentMeta = computed(() =>
    appointment.value ? DownPaymentStatus[appointment.value.down_payment_status] : null,
);

const stylistName = computed(() => {
    if (!appointment.value?.preferred_stylist_id) return 'No preference';

    return findAdmin(appointment.value.preferred_stylist_id)?.full_name ?? 'No preference';
});

const customer = computed(() => (appointment.value?.user_id ? findUser(appointment.value.user_id) : null));

/** The `Allergies` / `… Reason` pairs, already filtered down to what is set. */
const notes = computed(() => {
    if (!appointment.value) return [];

    return [
        { label: 'Allergies', value: appointment.value.allergies },
        { label: 'Last Service(s) Availed', value: appointment.value.last_services_availed },
        { label: 'Special Request', value: appointment.value.special_request },
        { label: 'Cancellation Reason', value: appointment.value.cancellation_reason },
        { label: 'Reschedule Reason', value: appointment.value.reschedule_reason },
    ].filter((row) => row.value);
});

/** `statusHistory()` returns newest first, with `id` as the tiebreaker. */
const history = computed(() => (appointment.value ? [...appointment.value.history].reverse() : []));

/** `AppointmentService::getDisplayNameAttribute()`. */
function displayName(line) {
    return line.variant_name ? `${line.service_name} (${line.variant_name})` : line.service_name;
}

/** `AppointmentService::getLineTotalAttribute()`. */
function lineTotal(line) {
    return Number(line.price) * Number(line.quantity);
}

/** `AppointmentStatusHistory::getArrowAttribute()`. */
function arrow(entry) {
    const from = entry.from_status ? (AppointmentStatus[entry.from_status]?.label ?? entry.from_status) : 'Created';

    return `${from} → ${AppointmentStatus[entry.to_status]?.label ?? entry.to_status}`;
}

/** `AppointmentStatusHistory::actorLabel()`. */
function actorLabel(entry) {
    if (entry.changed_by_name) return entry.changed_by_name;

    return ChangedBy[entry.changed_by]?.label ?? 'System';
}

/** The seeded rows carry no timestamp, so date each one from the moment it happened. */
function historyDate(entry) {
    const row = appointment.value;

    if (!row) return null;

    switch (entry.to_status) {
        case AppointmentStatus.Confirmed.value:
            return row.confirmed_at ?? row.preferred_date;
        case AppointmentStatus.InProgress.value:
            return row.started_at ?? row.preferred_date;
        case AppointmentStatus.Completed.value:
            return row.completed_at ?? row.preferred_date;
        case AppointmentStatus.Cancelled.value:
            return row.cancelled_at ?? row.preferred_date;
        default:
            return row.preferred_date;
    }
}

/** `updateStatus()` — the button posts its own value as `status`. */
function updateStatus(target) {
    if (!appointment.value) return;

    form.values.status = target;

    if (!form.validate()) return;

    const row = appointment.value;
    const previous = row.status;
    const notes = form.values.admin_notes ? String(form.values.admin_notes).trim() : '';
    const now = new Date();

    // The textarea sat inside the status form, so its contents were saved with
    // the transition (`updateNotes` was a separate, unused screen).
    if (notes !== (row.admin_notes ?? '')) row.admin_notes = notes || null;

    if (target === AppointmentStatus.Confirmed.value && !row.confirmed_at) row.confirmed_at = now;
    if (target === AppointmentStatus.InProgress.value && !row.started_at) row.started_at = now;
    if (target === AppointmentStatus.Completed.value) row.completed_at = now;

    row.cancelled_at = target === AppointmentStatus.Cancelled.value ? now : null;
    row.status = target;

    row.history.push({
        id: Math.max(0, ...appointments.flatMap((item) => item.history.map((entry) => entry.id))) + 1,
        from_status: previous,
        to_status: target,
        changed_by: ChangedBy.Admin.value,
        changed_by_id: currentAdmin.value?.id ?? null,
        changed_by_name: currentAdmin.value?.full_name ?? 'Admin',
        note: notes || null,
    });

    setFlash(`Appointment ${row.reference_number} marked as ${AppointmentStatus[target].label}.`);
}

/** `updateDownPayment()` — manual GCash verification, no gateway. */
function saveDownPayment() {
    if (!appointment.value) return;

    if (!downPayment.validate()) return;

    appointment.value.down_payment_status = downPayment.values.down_payment_status;

    setFlash('Down payment status updated.');
}
</script>

<template>
    <NotFoundPage v-if="!appointment" />

    <div v-else>
        <router-link
            :to="{ name: 'admin.appointments.index' }"
            class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to appointments
        </router-link>

        <PageHeader
            :eyebrow="appointment.reference_number"
            :title="appointment.customer_name"
            :description="`${serviceNamesLabel(appointment)} — ${dateTimeLabel}`"
        >
            <template #actions>
                <router-link
                    :to="{ name: 'admin.appointments.edit', params: { id: appointment.id } }"
                    class="btn-secondary btn-sm"
                >Reschedule / Edit</router-link>

                <Badge
                    v-if="statusMeta"
                    :status="statusMeta.badge"
                    :label="statusMeta.label"
                />
            </template>
        </PageHeader>

        <div class="grid gap-6 xl:grid-cols-3">
            <!-- Left column -->
            <div class="space-y-6 xl:col-span-2">
                <!-- Status update (Approve / Decline / Update Status) -->
                <CardPanel title="Update Status" subtitle="Changes are logged to the status history and the customer is notified.">
                    <ErrorSummary />

                    <form class="space-y-4" novalidate @submit.prevent="updateStatus(form.values.status)">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="action in STATUS_ACTIONS"
                                :key="action.value"
                                type="button"
                                class="btn-sm"
                                :class="appointment.status === action.value ? '' : action.class"
                                :disabled="!canManage || appointment.status === action.value"
                                @click="updateStatus(action.value)"
                            >{{ action.label }}</button>
                        </div>

                        <div>
                            <label for="admin_notes" class="label">
                                Admin Notes
                                <span class="ml-1.5 rounded-pill bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-primary">Internal only</span>
                            </label>
                            <textarea
                                id="admin_notes"
                                name="admin_notes"
                                rows="3"
                                class="input"
                                placeholder="Only visible to staff — never shown to the customer."
                                v-model="form.values.admin_notes"
                            />
                            <p v-if="form.errors.admin_notes" class="input-error-text">{{ form.errors.admin_notes }}</p>
                            <p class="input-hint">Saving notes here also applies them to the status update above.</p>
                        </div>
                    </form>
                </CardPanel>

                <!-- Manual down payment verification -->
                <CardPanel title="Down Payment (GCash)" subtitle="Verified manually — no payment gateway is integrated.">
                    <dl class="mb-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Reference</dt>
                            <dd class="mt-1 font-mono text-sm font-semibold text-primary">
                                {{ appointment.down_payment_reference || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Amount</dt>
                            <dd class="mt-1 text-sm font-semibold text-primary">
                                {{ appointment.down_payment_amount ? formatMoney(appointment.down_payment_amount) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Status</dt>
                            <dd class="mt-1">
                                <Badge
                                    v-if="downPaymentMeta"
                                    :status="downPaymentMeta.badge"
                                    :label="downPaymentMeta.label"
                                />
                            </dd>
                        </div>
                    </dl>

                    <form class="flex flex-wrap items-end gap-3" novalidate @submit.prevent="saveDownPayment">
                        <div class="min-w-56 flex-1">
                            <FormSelect
                                v-model="downPayment.values.down_payment_status"
                                name="down_payment_status"
                                label="Set verification status"
                                :options="downPaymentOptions"
                                :error="downPayment.errors.down_payment_status ?? null"
                            />
                        </div>
                        <button type="submit" class="btn-primary" :disabled="!canManage">Save</button>
                    </form>
                </CardPanel>

                <!-- Booking details -->
                <CardPanel title="Booking Details">
                    <dl class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Customer</dt>
                            <dd class="mt-1 font-medium text-primary">{{ appointment.customer_name }}</dd>
                            <dd class="text-sm text-ink-muted">{{ appointment.customer_phone }}</dd>
                            <dd v-if="appointment.customer_email" class="text-sm text-ink-muted">
                                {{ appointment.customer_email }}
                            </dd>
                            <router-link
                                v-if="customer"
                                :to="{ name: 'admin.users.show', params: { id: customer.id } }"
                                class="mt-1 inline-block text-xs font-medium text-primary underline underline-offset-2"
                            >View customer profile</router-link>
                            <p v-else class="mt-1 text-xs text-ink-muted">Guest booking (no account)</p>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Schedule</dt>
                            <dd class="mt-1 font-medium text-primary">
                                {{ formatDate(appointment.preferred_date, 'l, M j, Y') }}
                            </dd>
                            <dd class="text-sm text-ink-muted">
                                {{ timeLabel(appointment.preferred_time) }} &middot; {{ totalDuration(appointment) }} min total
                            </dd>
                            <dd class="text-sm text-ink-muted">Stylist: {{ stylistName }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Service(s)</dt>
                            <ul class="mt-2 space-y-2">
                                <li
                                    v-for="line in appointment.lines"
                                    :key="line.id"
                                    class="flex items-start justify-between gap-3 rounded-xl bg-linen/60 px-4 py-3"
                                >
                                    <div class="min-w-0">
                                        <p class="font-medium text-ink">{{ displayName(line) }}</p>
                                        <p class="text-xs text-ink-muted">
                                            {{ line.duration_minutes }} min &middot; Qty {{ line.quantity }}
                                        </p>
                                    </div>
                                    <p class="shrink-0 font-semibold text-primary">{{ formatMoney(lineTotal(line)) }}</p>
                                </li>
                            </ul>
                            <p class="mt-3 text-right text-sm text-ink-muted">
                                Total:
                                <span class="font-display text-lg font-bold text-primary">{{ formatMoney(appointment.total_amount) }}</span>
                            </p>
                        </div>

                        <div v-for="note in notes" :key="note.label">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ note.label }}</dt>
                            <dd class="mt-1 text-sm text-ink">{{ note.value }}</dd>
                        </div>
                    </dl>
                </CardPanel>
            </div>

            <!-- Right column -->
            <aside class="space-y-6">
                <CardPanel title="Status History">
                    <p v-if="history.length === 0" class="text-sm text-ink-muted">No history recorded.</p>

                    <ol v-else class="relative space-y-5 border-l border-primary/15 pl-5">
                        <li v-for="entry in history" :key="entry.id" class="relative">
                            <span class="absolute -left-[1.6rem] top-1 h-2.5 w-2.5 rounded-full bg-primary ring-4 ring-cream"></span>
                            <p class="text-sm font-medium text-primary">{{ arrow(entry) }}</p>
                            <p class="mt-0.5 text-xs text-ink-muted">
                                {{ formatDate(historyDate(entry), 'M j, Y g:i A') }}
                            </p>
                            <p class="text-xs text-ink-muted">by {{ actorLabel(entry) }}</p>
                            <p v-if="entry.note" class="mt-1.5 text-xs italic text-ink-muted">{{ entry.note }}</p>
                        </li>
                    </ol>
                </CardPanel>

                <CardPanel v-if="appointment.review" title="Customer Review">
                    <StarRating :model-value="appointment.review.rating" :interactive="false" />
                    <p class="mt-3 text-sm text-ink">{{ appointment.review.message }}</p>
                </CardPanel>
            </aside>
        </div>
    </div>
</template>
