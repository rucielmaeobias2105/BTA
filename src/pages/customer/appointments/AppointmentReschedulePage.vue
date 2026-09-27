<script setup>
import { computed, provide, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormTextarea from '@/components/ui/form/FormTextarea.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import {
    appointments,
    appointmentsForUser,
    canBeRescheduled,
    serviceNamesLabel,
    timeLabel,
} from '@/data/appointments';
import { ChangedBy, TermsCategory } from '@/data/enums';
import { publishedTerms } from '@/data/terms';
import {
    availableSlots,
    blockedRanges,
    firstBookableDate,
    lastBookableDate,
    timeProblems,
} from '@/data/availability';
import { addDays, fromDateInput, startOfDay, toDateInput, today } from '@/lib/dates';
import { currentUser, setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';
import { formatDate } from '@/lib/format';

/**
 * Customer Flow 8 — Reschedule Appointment.
 *
 * Ported from `customer/appointments/reschedule.blade.php`,
 * `Customer\RescheduleAppointmentController` and `Customer\RescheduleRequest`.
 * The `/book/slots` JSON fetch is now a local computed over
 * `availableSlots()`, still excluding the appointment's own slot, and the
 * `withValidator()` hook moved into `createForm`'s `after` callback.
 */
const route = useRoute();
const router = useRouter();

const appointment = computed(
    () => appointmentsForUser(currentUser.value?.id).find((row) => String(row.id) === String(route.params.id)) ?? null,
);

/** `edit()` read the first service line to check that service's own closures. */
const serviceId = computed(() => appointment.value?.lines[0]?.service_id ?? null);

const policy = computed(() => publishedTerms(TermsCategory.Rescheduling.value));

/**
 * `RescheduleAppointmentController::nextAvailableDate()` — the first day from
 * the current booking date onwards that still has an open slot.
 */
function nextAvailableDate() {
    const first = firstBookableDate();
    const last = lastBookableDate();
    const from = startOfDay(appointment.value.preferred_date) > first
        ? startOfDay(appointment.value.preferred_date)
        : first;

    for (let cursor = from; cursor <= last; cursor = addDays(cursor, 1)) {
        if (availableSlots(cursor, serviceId.value, appointment.value.id).length > 0) {
            return toDateInput(cursor);
        }
    }

    return toDateInput(today());
}

const form = createForm({
    initial: {
        preferred_date: appointment.value ? nextAvailableDate() : '',
        preferred_time: '',
        reason: '',
    },
    rules: {
        preferred_date: ['required', 'date'],
        preferred_time: ['required'],
        reason: ['nullable', 'string', 'max:500'],
    },
    after(errors, values) {
        if (!canBeRescheduled(appointment.value)) {
            errors.preferred_date = 'This appointment can no longer be rescheduled.';

            return;
        }

        const problems = timeProblems(
            fromDateInput(values.preferred_date),
            values.preferred_time,
            serviceId.value,
            appointment.value.id,
        );

        // Laravel appended one entry per problem and Blade showed the first.
        if (problems.length) errors.preferred_date = problems[0];
    },
});

provide('form-errors', form.errors);

const minDate = toDateInput(firstBookableDate());
const maxDate = toDateInput(lastBookableDate());

const slots = computed(() =>
    availableSlots(fromDateInput(form.values.preferred_date), serviceId.value, appointment.value?.id ?? null),
);

const closures = computed(() => blockedRanges(serviceId.value));

// The Alpine picker dropped a chosen time that the new day's slots no longer offer.
watch(slots, (list) => {
    if (form.values.preferred_time && !list.includes(form.values.preferred_time)) {
        form.values.preferred_time = '';
    }
});

function submit() {
    if (!form.validate()) return;

    const previousDate = toDateInput(appointment.value.preferred_date);
    const previousTime = appointment.value.preferred_time;
    const date = form.values.preferred_date;
    const time = form.values.preferred_time;
    const reason = form.values.reason ? String(form.values.reason).trim() : '';

    appointment.value.preferred_date = fromDateInput(date);
    appointment.value.preferred_time = time;
    appointment.value.reschedule_reason = reason || null;

    // A reschedule does not change the status, so from == to.
    appointment.value.history.push({
        id: Math.max(0, ...appointments.flatMap((row) => row.history.map((entry) => entry.id))) + 1,
        from_status: appointment.value.status,
        to_status: appointment.value.status,
        changed_by: ChangedBy.Customer.value,
        changed_by_id: currentUser.value?.id ?? null,
        changed_by_name: appointment.value.customer_name,
        note: `Rescheduled from ${previousDate} ${previousTime} to ${date} ${time}.${reason ? ` Reason: ${reason}` : ''}`,
    });

    setFlash(`Your appointment has been rescheduled to ${dateTimeLabel(appointment.value)}.`);

    router.push({ name: 'appointments.show', params: { id: appointment.value.id } });
}

/** `Appointment::getDateTimeLabelAttribute()` — "Apr 18, 2026 at 9:30 AM". */
function dateTimeLabel(row) {
    return `${formatDate(row.preferred_date, 'M j, Y')} at ${timeLabel(row.preferred_time)}`;
}
</script>

<template>
    <NotFoundPage v-if="!appointment" />

    <div v-else class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <router-link
            :to="{ name: 'appointments.show', params: { id: appointment.id } }"
            class="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to appointment
        </router-link>

        <PageHeader
            eyebrow="Reschedule"
            title="Reschedule Appointment"
            description="Pick a new date and time. Your new slot is re-validated against our calendar."
        />

        <ErrorSummary />

        <!-- Current date/time — read-only -->
        <div class="bta-card mb-6 border-l-4 border-l-gold p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Current Date &amp; Time (read-only)</p>
            <p class="mt-1.5 font-display text-xl font-bold text-primary">{{ dateTimeLabel(appointment) }}</p>
            <p class="mt-0.5 text-sm text-ink-muted">{{ serviceNamesLabel(appointment) }} &middot; Ref {{ appointment.reference_number }}</p>
        </div>

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <CardPanel title="New Date &amp; Time">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="preferred_date" class="label">
                            New Preferred Date <span class="text-status-cancelled">*</span>
                        </label>

                        <input
                            id="preferred_date"
                            name="preferred_date"
                            type="date"
                            class="input"
                            :class="form.errors.preferred_date ? 'input-error' : ''"
                            :min="minDate"
                            :max="maxDate"
                            required
                            :aria-invalid="form.errors.preferred_date ? 'true' : undefined"
                            v-model="form.values.preferred_date"
                        >

                        <p v-if="form.errors.preferred_date" class="input-error-text">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.preferred_date }}
                        </p>
                    </div>

                    <div>
                        <label for="preferred_time" class="label">
                            New Preferred Time <span class="text-status-cancelled">*</span>
                        </label>

                        <select
                            id="preferred_time"
                            name="preferred_time"
                            class="input"
                            :class="form.errors.preferred_time ? 'input-error' : ''"
                            required
                            v-model="form.values.preferred_time"
                        >
                            <option value="">Select a time</option>
                            <option v-for="slot in slots" :key="slot" :value="slot">{{ timeLabel(slot) }}</option>
                        </select>

                        <p v-if="slots.length === 0" class="input-hint">
                            No open slots on this date. Please choose another day.
                        </p>

                        <p v-if="form.errors.preferred_time" class="input-error-text">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.preferred_time }}
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <FormTextarea
                        v-model="form.values.reason"
                        name="reason"
                        label="Reason (optional)"
                        :rows="3"
                        placeholder="Let us know why you need to move your appointment."
                    />
                </div>
            </CardPanel>

            <div
                v-if="closures.length !== 0"
                class="mt-6 rounded-card border border-status-low-stock/25 bg-status-low-stock-bg/40 p-5"
            >
                <p class="text-sm font-semibold text-status-low-stock">Dates we are closed</p>
                <ul class="mt-2 space-y-1 text-xs text-ink">
                    <li v-for="block in closures" :key="`${block.label}-${block.service_id}`">
                        <span class="font-medium text-primary">{{ block.label }}</span>
                        — {{ block.service_id === null ? 'Salon closed' : `${block.service} unavailable` }}
                        <span v-if="block.reason" class="text-ink-muted">({{ block.reason }})</span>
                    </li>
                </ul>
            </div>

            <CardPanel v-if="policy" title="Rescheduling Policy" class="mt-6">
                <div
                    class="max-h-48 space-y-2 overflow-y-auto pr-2 text-sm leading-relaxed text-ink [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-primary [&_h3]:mt-3 [&_h3]:font-semibold [&_h3]:text-primary [&_li]:ml-4 [&_li]:list-disc"
                    v-html="policy.content"
                />
            </CardPanel>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                <button type="submit" class="btn-primary sm:min-w-44">Confirm Reschedule</button>
                <router-link
                    :to="{ name: 'appointments.show', params: { id: appointment.id } }"
                    class="btn-ghost sm:min-w-44"
                >Keep Current Slot</router-link>
            </div>
        </form>
    </div>
</template>
