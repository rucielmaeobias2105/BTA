<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import {
    appointmentsForUser,
    byBookingDate,
    canBeCancelled,
    canBeRated,
    canBeRescheduled,
    filterByStatus,
    serviceNamesLabel,
    statusCounts,
    timeLabel,
} from '@/data/appointments';
import { APPOINTMENT_FILTER_OPTIONS, AppointmentStatus, DownPaymentStatus } from '@/data/enums';
import { findAdmin } from '@/data/admins';
import { currentUser } from '@/lib/session';
import { formatDate, formatMoney } from '@/lib/format';

/**
 * Customer Flow 5 (part 1) — My Appointments / Transactions.
 *
 * Ported from `customer/appointments/index.blade.php` and
 * `Customer\AppointmentController::index()`. The status filter moved from the
 * query string onto the router query, so the filter pills are router links and
 * the list re-derives from the same reactive collection.
 */

/** `Appointment::getDateTimeLabelAttribute()` — "Apr 18, 2026 at 9:30 AM". */
function dateTimeLabel(appointment) {
    return `${formatDate(appointment.preferred_date, 'M j, Y')} at ${timeLabel(appointment.preferred_time)}`;
}

const route = useRoute();

const statusOptions = APPOINTMENT_FILTER_OPTIONS;

/** Anything the enum does not know about falls back to 'all', as the controller did. */
const status = computed(() => {
    const requested = String(route.query.status ?? '');

    return statusOptions.some((option) => option.value === requested) ? requested : 'all';
});

const mine = computed(() => appointmentsForUser(currentUser.value?.id));

const counts = computed(() => statusCounts(mine.value));

const appointments = computed(() => byBookingDate(filterByStatus(mine.value, status.value)));

function stylistName(appointment) {
    if (!appointment.preferred_stylist_id) return null;

    return findAdmin(appointment.preferred_stylist_id)?.full_name ?? null;
}

function statusOf(appointment) {
    return AppointmentStatus[appointment.status] ?? null;
}

function downPaymentOf(appointment) {
    return DownPaymentStatus[appointment.down_payment_status] ?? null;
}
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <PageHeader
            eyebrow="My Bookings"
            title="My Appointments"
            description="View, cancel, reschedule or rate your appointments."
        >
            <template #actions>
                <router-link :to="{ name: 'appointments.create' }" class="btn-primary btn-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    New Booking
                </router-link>
            </template>
        </PageHeader>

        <!-- Status filter — action links only, no input fields -->
        <div class="mb-6 flex flex-wrap gap-2">
            <router-link
                v-for="option in statusOptions"
                :key="option.value"
                :to="{ name: 'appointments.index', query: option.value === 'all' ? {} : { status: option.value } }"
                class="rounded-pill px-4 py-2 text-sm font-medium transition"
                :class="status === option.value ? 'bg-primary text-cream shadow-card' : 'bg-cream text-ink hover:bg-linen'"
            >
                {{ option.label }}
                <span v-if="option.value !== 'all' && (counts[option.value] ?? 0) > 0" class="ml-1 opacity-70">
                    {{ counts[option.value] }}
                </span>
            </router-link>
        </div>

        <EmptyState
            v-if="appointments.length === 0"
            title="No appointments here"
            :description="`You don't have any ${status === 'all' ? '' : status} appointments right now.`"
        >
            <router-link :to="{ name: 'appointments.create' }" class="btn-primary">Book an Appointment</router-link>
        </EmptyState>

        <div v-else class="space-y-4">
            <article v-for="appointment in appointments" :key="appointment.id" class="bta-card p-5 sm:p-6">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <!-- Left: details -->
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                v-if="statusOf(appointment)"
                                :status="statusOf(appointment).badge"
                                :label="statusOf(appointment).label"
                            />
                            <span class="text-xs text-ink-muted">Ref: {{ appointment.reference_number }}</span>
                        </div>

                        <h3 class="mt-3 font-display text-lg font-semibold text-primary">
                            {{ serviceNamesLabel(appointment) }}
                        </h3>

                        <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                                <dd class="text-ink">{{ dateTimeLabel(appointment) }}</dd>
                            </div>

                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                <dd class="font-semibold text-primary">{{ formatMoney(appointment.total_amount) }}</dd>
                            </div>

                            <div v-if="stylistName(appointment)" class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                                <dd class="text-ink">{{ stylistName(appointment) }}</dd>
                            </div>

                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"/></svg>
                                <dd>
                                    <Badge
                                        v-if="downPaymentOf(appointment)"
                                        :status="downPaymentOf(appointment).badge"
                                        :label="downPaymentOf(appointment).label"
                                    />
                                </dd>
                            </div>
                        </dl>

                        <p
                            v-if="appointment.cancellation_reason"
                            class="mt-3 rounded-lg bg-status-cancelled-bg/50 px-3.5 py-2.5 text-xs text-ink"
                        >
                            <span class="font-semibold text-status-cancelled">Reason:</span>
                            {{ appointment.cancellation_reason }}
                        </p>
                    </div>

                    <!-- Right: action buttons only -->
                    <div class="flex shrink-0 flex-wrap gap-2 sm:w-44 sm:flex-col">
                        <router-link
                            :to="{ name: 'appointments.show', params: { id: appointment.id } }"
                            class="btn-secondary btn-sm flex-1 sm:w-full"
                        >
                            View
                        </router-link>

                        <router-link
                            v-if="canBeRescheduled(appointment)"
                            :to="{ name: 'appointments.reschedule', params: { id: appointment.id } }"
                            class="btn-gold btn-sm flex-1 sm:w-full"
                        >
                            Reschedule
                        </router-link>

                        <router-link
                            v-if="canBeRated(appointment)"
                            :to="{ name: 'appointments.rate.create', params: { id: appointment.id } }"
                            class="btn-primary btn-sm flex-1 sm:w-full"
                        >
                            Rate
                        </router-link>
                        <router-link
                            v-else-if="appointment.review"
                            :to="{ name: 'appointments.show', params: { id: appointment.id }, hash: '#review' }"
                            class="btn-ghost btn-sm flex-1 sm:w-full"
                        >
                            Your Review
                        </router-link>

                        <router-link
                            v-if="canBeCancelled(appointment)"
                            :to="{ name: 'appointments.cancel', params: { id: appointment.id } }"
                            class="btn-danger btn-sm flex-1 sm:w-full"
                        >
                            Cancel
                        </router-link>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
