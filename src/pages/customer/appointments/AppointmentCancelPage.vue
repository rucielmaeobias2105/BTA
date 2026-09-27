<script setup>
import { computed, provide } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import FormTextarea from '@/components/ui/form/FormTextarea.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import { appointments, appointmentsForUser, canBeCancelled, serviceNamesLabel, timeLabel } from '@/data/appointments';
import { AppointmentStatus, ChangedBy, TermsCategory } from '@/data/enums';
import { publishedTerms } from '@/data/terms';
import { currentUser, setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';
import { formatDate } from '@/lib/format';

/**
 * Customer Flow 7 — Cancel Appointment.
 *
 * Ported from `customer/appointments/cancel.blade.php` and
 * `Customer\CancelAppointmentController`. The PATCH became an in-place update
 * of the reactive appointment followed by the same `session('status')` flash.
 */
const route = useRoute();
const router = useRouter();

/** `CancelAppointmentController::REASONS` — quick picks; the field also takes free text. */
const REASONS = [
    'Schedule conflict',
    'Feeling unwell',
    'Change of plans',
    'Found another salon',
    'Booked by mistake',
    'Other',
];

const reasonOptions = Object.fromEntries(REASONS.map((reason) => [reason, reason]));

const appointment = computed(
    () => appointmentsForUser(currentUser.value?.id).find((row) => String(row.id) === String(route.params.id)) ?? null,
);

const policy = computed(() => publishedTerms(TermsCategory.Cancellation.value));

const form = createForm({
    initial: {
        reason_preset: '',
        reason: '',
        agree_cancellation_policy: false,
    },
    rules: {
        reason_preset: ['nullable', `in:${REASONS.join(',')}`],
        reason: ['nullable', 'string', 'max:500'],
        agree_cancellation_policy: ['accepted'],
    },
    messages: {
        accepted: 'You must agree to the Cancellation Policy to proceed.',
    },
});

provide('form-errors', form.errors);

// `edit()` refused to render an appointment that is no longer actionable.
if (appointment.value && !canBeCancelled(appointment.value)) {
    form.errors.status = `A ${AppointmentStatus[appointment.value.status].label} appointment cannot be cancelled.`;
}

function submit() {
    if (!form.validate()) return;

    // `update()` re-checked the state before writing anything.
    if (!canBeCancelled(appointment.value)) {
        form.errors.status = 'This appointment can no longer be cancelled.';

        return;
    }

    const preset = form.values.reason_preset ? String(form.values.reason_preset) : null;
    const note = form.values.reason ? String(form.values.reason).trim() : '';

    // The preset and the free-text note are both optional; combine them.
    const reason = [...new Set([preset, note].filter(Boolean))].join(' — ') || null;

    const previous = appointment.value.status;

    appointment.value.status = AppointmentStatus.Cancelled.value;
    appointment.value.cancellation_reason = reason;
    appointment.value.cancelled_at = new Date();

    appointment.value.history.push({
        id: Math.max(0, ...appointments.flatMap((row) => row.history.map((entry) => entry.id))) + 1,
        from_status: previous,
        to_status: AppointmentStatus.Cancelled.value,
        changed_by: ChangedBy.Customer.value,
        changed_by_id: currentUser.value?.id ?? null,
        changed_by_name: appointment.value.customer_name,
        note: `Cancelled by customer.${reason ? ` Reason: ${reason}` : ''}`,
    });

    setFlash(`Appointment ${appointment.value.reference_number} has been cancelled.`);

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
            eyebrow="Cancellation"
            title="Cancel Appointment"
            description="Tell us why you'd like to cancel. This cannot be undone."
        />

        <ErrorSummary />

        <!-- Reference auto-filled from context (read-only) -->
        <div class="bta-card mb-6 p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Appointment Reference</p>
                    <p class="mt-1 font-mono text-sm font-semibold text-primary">{{ appointment.reference_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Scheduled</p>
                    <p class="mt-1 text-sm font-medium text-primary">{{ dateTimeLabel(appointment) }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Service(s)</p>
                    <p class="mt-1 text-sm text-ink">{{ serviceNamesLabel(appointment) }}</p>
                </div>
            </div>
        </div>

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <CardPanel title="Reason for Cancellation">
                <!-- Optional: dropdown of quick reasons + free text -->
                <FormSelect
                    v-model="form.values.reason_preset"
                    name="reason_preset"
                    label="Reason (optional)"
                    include-blank
                    blank-label="Choose a reason…"
                    :options="reasonOptions"
                />

                <div class="mt-4">
                    <FormTextarea
                        v-model="form.values.reason"
                        name="reason"
                        label="Or tell us more"
                        :rows="3"
                        placeholder="Add any detail that would help us improve…"
                    />
                </div>

                <p class="mt-4 rounded-xl bg-linen/70 px-4 py-3 text-xs text-ink-muted">
                    Cancelling free of charge up to 24 hours before your appointment. Later cancellations
                    may forfeit the down payment because your slot and products are reserved for you.
                </p>
            </CardPanel>

            <CardPanel title="Cancellation Policy" accent="maroon">
                <div
                    v-if="policy"
                    class="max-h-64 space-y-3 overflow-y-auto pr-2 text-sm leading-relaxed text-ink [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-primary [&_h3]:mt-4 [&_h3]:font-semibold [&_h3]:text-primary [&_li]:ml-4 [&_li]:list-disc [&_ol]:list-decimal [&_p]:mb-2"
                    v-html="policy.content"
                />
                <p v-if="policy" class="mt-4 text-xs text-ink-muted">
                    Version {{ policy.version }} &middot; published {{ formatDate(policy.published_at, 'M j, Y') }}
                </p>

                <p v-else class="text-sm text-ink-muted">
                    Our cancellation policy has not been published yet.
                    <router-link
                        :to="{ name: 'terms.show', params: { category: 'cancellation' } }"
                        target="_blank"
                        rel="noopener"
                        class="font-medium text-primary underline underline-offset-2"
                    >Read the policy</router-link>.
                </p>
            </CardPanel>

            <CardPanel title="Confirmation">
                <FormCheckbox
                    v-model="form.values.agree_cancellation_policy"
                    name="agree_cancellation_policy"
                    :value="1"
                    required
                    hint="I understand the consequences of cancelling this appointment."
                >
                    <span>
                        I agree to the
                        <router-link
                            :to="{ name: 'terms.show', params: { category: 'cancellation' } }"
                            target="_blank"
                            rel="noopener"
                            class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark"
                        >Cancellation Policy</router-link>.
                    </span>
                </FormCheckbox>
            </CardPanel>

            <div class="flex flex-col gap-3 sm:flex-row-reverse">
                <button type="submit" class="btn-danger sm:min-w-44">Cancel Appointment</button>
                <router-link
                    :to="{ name: 'appointments.show', params: { id: appointment.id } }"
                    class="btn-ghost sm:min-w-44"
                >Keep Appointment</router-link>
            </div>
        </form>
    </div>
</template>
