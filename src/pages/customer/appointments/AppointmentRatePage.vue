<script setup>
import { computed, provide } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormTextarea from '@/components/ui/form/FormTextarea.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StarRating from '@/components/ui/StarRating.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import { appointments, appointmentsForUser, canBeRated, serviceNamesLabel, timeLabel } from '@/data/appointments';
import { AppointmentStatus } from '@/data/enums';
import { findAdmin } from '@/data/admins';
import { currentUser, setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';
import { formatDate } from '@/lib/format';

/**
 * Customer Flow 10 — Rate Service.
 *
 * Ported from `customer/appointments/rate.blade.php` and
 * `Customer\RateServiceController`. `ensureRateable()` refused anything that
 * was not completed, or that already had a review; those messages surface in
 * the error summary exactly as they did in the re-rendered form.
 */
const route = useRoute();
const router = useRouter();

const appointment = computed(
    () => appointmentsForUser(currentUser.value?.id).find((row) => String(row.id) === String(route.params.id)) ?? null,
);

const form = createForm({
    initial: {
        rating: 0,
        message: '',
    },
    rules: {
        rating: ['required', 'integer', 'min:1', 'max:5'],
        message: ['required', 'string', 'min:5', 'max:2000'],
    },
});

provide('form-errors', form.errors);

// `create()` ran `ensureRateable()` before the view ever rendered.
if (appointment.value && !canBeRated(appointment.value)) {
    form.errors.rating = appointment.value.status !== AppointmentStatus.Completed.value
        ? 'Only completed appointments can be rated.'
        : 'You have already reviewed this appointment.';
}

const stylistName = computed(() => {
    if (!appointment.value?.preferred_stylist_id) return null;

    return findAdmin(appointment.value.preferred_stylist_id)?.full_name ?? null;
});

function submit() {
    if (!form.validate()) return;

    // `min:1` in `createForm` is a string-length rule, so a 0 rating would slip
    // past it — the server's `integer|min:1` still has to be asserted.
    if (!Number(form.values.rating)) {
        form.errors.rating = 'This field is required.';

        return;
    }

    if (!canBeRated(appointment.value)) {
        form.errors.rating = appointment.value.review
            ? 'You have already reviewed this appointment.'
            : 'Only completed appointments can be rated.';

        return;
    }

    appointment.value.review = {
        id: Math.max(0, ...appointments.map((row) => row.review?.id ?? 0)) + 1,
        appointment_id: appointment.value.id,
        user_id: appointment.value.user_id,
        service_id: appointment.value.lines[0]?.service_id ?? null,
        rating: Number(form.values.rating),
        message: form.values.message,
        customer_name: appointment.value.customer_name,
        created_at: new Date(),
    };

    setFlash('Thank you! Your review has been submitted.');

    router.push({ name: 'appointments.show', params: { id: appointment.value.id } });
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
            eyebrow="Feedback"
            title="Rate Service"
            description="Your feedback helps our team keep improving."
        />

        <ErrorSummary />

        <!-- Appointment recap -->
        <div class="bta-card mb-6 border-l-4 border-l-gold p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Your Visit</p>
            <h2 class="mt-1.5 font-display text-lg font-semibold text-primary">{{ serviceNamesLabel(appointment) }}</h2>
            <p class="mt-0.5 text-sm text-ink-muted">
                {{ formatDate(appointment.preferred_date, 'M j, Y') }} at {{ timeLabel(appointment.preferred_time) }}
                <template v-if="stylistName">&middot; with {{ stylistName }}</template>
            </p>
        </div>

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <CardPanel title="Star Rating" subtitle="Tap a star to rate your experience (1–5).">
                <StarRating v-model="form.values.rating" size="lg" />
            </CardPanel>

            <CardPanel title="Your Review">
                <FormTextarea
                    v-model="form.values.message"
                    name="message"
                    label="Review Message"
                    required
                    :rows="5"
                    placeholder="What did you enjoy? Anything we could improve?"
                    hint="Minimum of 5 characters. Reviews are visible to our team."
                />
            </CardPanel>

            <div class="flex flex-col gap-3 sm:flex-row-reverse">
                <button type="submit" class="btn-primary sm:min-w-44">Submit Review</button>
                <router-link
                    :to="{ name: 'appointments.show', params: { id: appointment.id } }"
                    class="btn-ghost sm:min-w-44"
                >Maybe Later</router-link>
            </div>
        </form>
    </div>
</template>
