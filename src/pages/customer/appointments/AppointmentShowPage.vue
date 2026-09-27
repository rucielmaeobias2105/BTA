<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import StarRating from '@/components/ui/StarRating.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import {
    appointmentsForUser,
    canBeCancelled,
    canBeRated,
    canBeRescheduled,
    timeLabel,
    totalDuration,
} from '@/data/appointments';
import { AppointmentStatus, ChangedBy, DownPaymentStatus } from '@/data/enums';
import { findAdmin } from '@/data/admins';
import { currentUser } from '@/lib/session';
import { formatDate, formatMoney } from '@/lib/format';

/**
 * Read-only detail for a single appointment.
 *
 * Ported from `customer/appointments/show.blade.php` and
 * `Customer\AppointmentController::show()`. `authorizeOwnership()` threw a 403
 * for somebody else's booking; with no server that becomes the 404 treatment,
 * the same way an unknown id is handled.
 */
const route = useRoute();

const appointment = computed(
    () => appointmentsForUser(currentUser.value?.id).find((row) => String(row.id) === String(route.params.id)) ?? null,
);

const statusMeta = computed(() => (appointment.value ? AppointmentStatus[appointment.value.status] : null));
const downPaymentMeta = computed(() =>
    appointment.value ? DownPaymentStatus[appointment.value.down_payment_status] : null,
);

const stylistName = computed(() => {
    if (!appointment.value?.preferred_stylist_id) return 'No preference';

    return findAdmin(appointment.value.preferred_stylist_id)?.full_name ?? 'No preference';
});

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

/** `AppointmentService::getLineTotalAttribute()`. */
function lineTotal(line) {
    return Number(line.price) * Number(line.quantity);
}

/** `AppointmentService::getDisplayNameAttribute()`. */
function displayName(line) {
    return line.variant_name ? `${line.service_name} (${line.variant_name})` : line.service_name;
}

/**
 * `statusHistory()` returned newest first, with `id` as the tiebreaker, so the
 * port reverses the seeded chronological trail.
 */
const history = computed(() => (appointment.value ? [...appointment.value.history].reverse() : []));

/**
 * The seeded history rows carry no timestamp, so each one is dated from the
 * moment in the appointment's life the status it moved to actually happened.
 */
function historyDate(entry) {
    const a = appointment.value;

    if (!a) return null;

    switch (entry.to_status) {
        case AppointmentStatus.Confirmed.value:
            return a.confirmed_at ?? a.preferred_date;
        case AppointmentStatus.InProgress.value:
            return a.started_at ?? a.preferred_date;
        case AppointmentStatus.Completed.value:
            return a.completed_at ?? a.preferred_date;
        case AppointmentStatus.Cancelled.value:
            return a.cancelled_at ?? a.preferred_date;
        default:
            return a.preferred_date;
    }
}

/** `AppointmentStatusHistory::getArrowAttribute()`. */
function arrow(entry) {
    const from = entry.from_status
        ? (AppointmentStatus[entry.from_status]?.label ?? entry.from_status)
        : 'Created';

    return `${from} → ${AppointmentStatus[entry.to_status]?.label ?? entry.to_status}`;
}

/** `AppointmentStatusHistory::actorLabel()`. */
function actorLabel(entry) {
    if (entry.changed_by_name) return entry.changed_by_name;

    return ChangedBy[entry.changed_by]?.label ?? 'System';
}

const showActions = computed(() =>
    Boolean(appointment.value) && (canBeCancelled(appointment.value) || canBeRescheduled(appointment.value) || canBeRated(appointment.value)),
);
</script>

<template>
    <NotFoundPage v-if="!appointment" />

    <div v-else class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <nav class="mb-6 flex items-center gap-2 text-sm text-ink-muted" aria-label="Breadcrumb">
            <router-link :to="{ name: 'dashboard' }" class="transition hover:text-primary">Dashboard</router-link>
            <span aria-hidden="true">/</span>
            <router-link :to="{ name: 'appointments.index' }" class="transition hover:text-primary">My Appointments</router-link>
            <span aria-hidden="true">/</span>
            <span class="truncate font-medium text-primary">{{ appointment.reference_number }}</span>
        </nav>

        <!-- Header -->
        <div class="bta-card mb-6 overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-primary/10 bg-primary px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gold">Booking Reference</p>
                    <h1 class="mt-1 font-display text-2xl font-bold tracking-tight text-cream">
                        {{ appointment.reference_number }}
                    </h1>
                </div>
                <Badge
                    v-if="statusMeta"
                    :status="statusMeta.badge"
                    :label="statusMeta.label"
                    class="!bg-cream/15 !text-cream"
                />
            </div>

            <div class="grid gap-5 p-6 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Date &amp; Time</p>
                    <p class="mt-1.5 font-medium text-primary">{{ formatDate(appointment.preferred_date, 'M j, Y') }}</p>
                    <p class="text-sm text-ink-muted">{{ timeLabel(appointment.preferred_time) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Amount</p>
                    <p class="mt-1.5 font-display text-xl font-bold text-primary">
                        {{ formatMoney(appointment.total_amount) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Down Payment</p>
                    <p class="mt-1.5 font-medium text-primary">
                        {{ appointment.down_payment_amount ? formatMoney(appointment.down_payment_amount) : '—' }}
                    </p>
                    <div class="mt-1">
                        <Badge
                            v-if="downPaymentMeta"
                            :status="downPaymentMeta.badge"
                            :label="downPaymentMeta.label"
                        />
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">GCash Reference</p>
                    <p class="mt-1.5 font-medium text-primary">{{ appointment.down_payment_reference || '—' }}</p>
                    <p class="text-xs text-ink-muted">Verified manually by our team</p>
                </div>
            </div>

            <div v-if="showActions" class="flex flex-wrap gap-2 border-t border-primary/10 bg-linen/50 px-6 py-4">
                <router-link
                    v-if="canBeRated(appointment)"
                    :to="{ name: 'appointments.rate.create', params: { id: appointment.id } }"
                    class="btn-primary btn-sm"
                >Rate Service</router-link>
                <router-link
                    v-if="canBeRescheduled(appointment)"
                    :to="{ name: 'appointments.reschedule', params: { id: appointment.id } }"
                    class="btn-gold btn-sm"
                >Reschedule</router-link>
                <router-link
                    v-if="canBeCancelled(appointment)"
                    :to="{ name: 'appointments.cancel', params: { id: appointment.id } }"
                    class="btn-danger btn-sm"
                >Cancel Appointment</router-link>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Booking summary (read-only) -->
            <div class="space-y-6 lg:col-span-2">
                <CardPanel title="Booking Summary" subtitle="Read-only">
                    <dl class="space-y-4 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Customer</dt>
                            <dd class="mt-1 font-medium text-primary">{{ appointment.customer_name }}</dd>
                            <dd class="text-ink-muted">{{ appointment.customer_phone }}</dd>
                            <dd v-if="appointment.customer_email" class="text-ink-muted">{{ appointment.customer_email }}</dd>
                        </div>

                        <div>
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
                                    <p class="shrink-0 font-semibold text-primary">
                                        {{ formatMoney(lineTotal(line)) }}
                                    </p>
                                </li>
                            </ul>
                            <p class="mt-3 text-right text-sm text-ink-muted">
                                Total duration:
                                <span class="font-medium text-primary">{{ totalDuration(appointment) }} min</span>
                            </p>
                        </div>
                    </dl>
                </CardPanel>

                <!-- Preferences -->
                <CardPanel title="Your Preferences & Notes">
                    <dl class="space-y-4 text-sm">
                        <div
                            v-for="note in notes"
                            :key="note.label"
                            class="border-b border-primary/5 pb-4 last:border-0 last:pb-0"
                        >
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ note.label }}</dt>
                            <dd class="mt-1 text-ink">{{ note.value }}</dd>
                        </div>

                        <p v-if="notes.length === 0" class="text-ink-muted">No additional notes for this appointment.</p>

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Preferred Stylist</dt>
                            <dd class="mt-1 text-ink">{{ stylistName }}</dd>
                        </div>
                    </dl>
                </CardPanel>

                <!-- Review -->
                <div id="review">
                    <CardPanel v-if="appointment.review" title="Your Review">
                        <StarRating :model-value="appointment.review.rating" :interactive="false" />
                        <p class="mt-3 text-sm leading-relaxed text-ink">{{ appointment.review.message }}</p>
                        <p class="mt-3 text-xs text-ink-muted">Submitted {{ formatDate(appointment.review.created_at, 'M j, Y') }}</p>
                    </CardPanel>

                    <CardPanel
                        v-else-if="canBeRated(appointment)"
                        title="Rate Your Experience"
                        subtitle="Only completed appointments can be rated."
                    >
                        <p class="text-sm text-ink-muted">How did we do? Your feedback helps us improve.</p>
                        <router-link
                            :to="{ name: 'appointments.rate.create', params: { id: appointment.id } }"
                            class="btn-primary mt-4"
                        >Rate Service</router-link>
                    </CardPanel>
                </div>
            </div>

            <!-- Status history -->
            <aside>
                <CardPanel title="Status History" subtitle="Every change to this appointment.">
                    <p v-if="history.length === 0" class="text-sm text-ink-muted">No history recorded yet.</p>

                    <ol v-else class="relative space-y-5 border-l border-primary/15 pl-5">
                        <li v-for="entry in history" :key="entry.id" class="relative">
                            <span class="absolute -left-[1.6rem] top-1 h-2.5 w-2.5 rounded-full bg-primary ring-4 ring-cream"></span>
                            <p class="text-sm font-medium text-primary">{{ arrow(entry) }}</p>
                            <p class="mt-0.5 text-xs text-ink-muted">
                                {{ formatDate(historyDate(entry), 'M j, Y g:i A') }}
                            </p>
                            <p class="text-xs text-ink-muted">
                                by {{ actorLabel(entry) }}
                            </p>
                            <p v-if="entry.note" class="mt-1.5 text-xs italic text-ink-muted">{{ entry.note }}</p>
                        </li>
                    </ol>
                </CardPanel>
            </aside>
        </div>
    </div>
</template>
