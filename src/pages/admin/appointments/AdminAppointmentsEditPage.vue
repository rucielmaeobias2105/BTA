<script setup>
import { computed, provide } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import { appointments, findAppointment, serviceNamesLabel, timeLabel } from '@/data/appointments';
import { timeProblems } from '@/data/availability';
import {
    AdminRole,
    AppointmentStatus,
    ChangedBy,
    roleCan,
} from '@/data/enums';
import { currentAdmin, setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';
import { fromDateInput, toDateInput } from '@/lib/dates';
import { formatDate } from '@/lib/format';

/**
 * Admin Flow 3 — Reschedule / update an appointment.
 *
 * Ported from `admin/appointments/edit.blade.php` and
 * `Admin\AppointmentController::update()`. The `BookingAvailability` guard
 * became the `after` callback on `createForm`, and `BookingService::reschedule()`
 * became a local move that writes the same status-history row.
 */
const route = useRoute();
const router = useRouter();

const appointment = computed(() => findAppointment(route.params.id));

const role = computed(() => currentAdmin.value?.role ?? AdminRole.SuperAdmin.value);
const canManage = computed(() => roleCan(role.value, 'appointments.manage'));

/** `AppointmentStatus::options()`. */
const statusOptions = computed(() =>
    Object.fromEntries(Object.values(AppointmentStatus).map((meta) => [meta.value, meta.label])),
);

/** `edit()` read the first service line to check that service's own closures. */
const serviceId = computed(() => appointment.value?.lines[0]?.service_id ?? null);

const form = createForm({
    initial: {
        preferred_date: appointment.value ? toDateInput(appointment.value.preferred_date) : '',
        preferred_time: appointment.value?.preferred_time ?? '',
        status: appointment.value?.status ?? AppointmentStatus.Pending.value,
        admin_notes: appointment.value?.admin_notes ?? '',
    },
    rules: {
        preferred_date: ['required', 'date'],
        preferred_time: ['required'],
        status: ['required', `in:${Object.keys(statusOptions.value).join(',')}`],
        admin_notes: ['nullable', 'string', 'max:2000'],
    },
    // The controller bailed out with `withErrors(['preferred_date' => $problems[0]])`.
    after(errors, values) {
        if (!appointment.value) return;

        const problems = timeProblems(
            fromDateInput(values.preferred_date),
            values.preferred_time,
            serviceId.value,
            appointment.value.id,
        );

        if (problems.length) errors.preferred_date = problems[0];
    },
});

provide('form-errors', form.errors);

/** `Appointment::getDateTimeLabelAttribute()`. */
const dateTimeLabel = computed(() => {
    if (!appointment.value) return '';

    return `${formatDate(appointment.value.preferred_date, 'M j, Y')} at ${timeLabel(appointment.value.preferred_time)}`;
});

function submit() {
    if (!appointment.value) return;

    if (!form.validate()) return;

    const row = appointment.value;
    const previous = row.status;
    const date = form.values.preferred_date;
    const time = form.values.preferred_time;
    const target = form.values.status;
    const notes = form.values.admin_notes ? String(form.values.admin_notes).trim() : '';
    const previousDate = toDateInput(row.preferred_date);
    const previousTime = row.preferred_time;
    const dateChanged = previousDate !== date;
    const now = new Date();

    // `BookingService::reschedule()` — the date move, its history row included.
    if (dateChanged) {
        row.preferred_date = fromDateInput(date);
        row.preferred_time = time;
        row.reschedule_reason = 'Moved by the salon.';

        row.history.push({
            id: Math.max(0, ...appointments.flatMap((item) => item.history.map((entry) => entry.id))) + 1,
            from_status: row.status,
            to_status: row.status,
            changed_by: ChangedBy.Customer.value,
            changed_by_id: row.user_id,
            changed_by_name: row.customer_name,
            note: `Rescheduled from ${previousDate} ${previousTime} to ${date} ${time}. Reason: Moved by the salon.`,
        });
    } else {
        row.preferred_time = time;
    }

    if (notes !== (row.admin_notes ?? '')) row.admin_notes = notes || null;

    if (target === AppointmentStatus.Confirmed.value && !row.confirmed_at) row.confirmed_at = now;
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

    setFlash(`Appointment ${row.reference_number} updated.`);

    router.push({ name: 'admin.appointments.show', params: { id: row.id } });
}
</script>

<template>
    <NotFoundPage v-if="!appointment" />

    <div v-else>
        <router-link
            :to="{ name: 'admin.appointments.show', params: { id: appointment.id } }"
            class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to appointment
        </router-link>

        <PageHeader
            :eyebrow="appointment.reference_number"
            title="Reschedule / Update"
            :description="`Currently ${dateTimeLabel} — ${serviceNamesLabel(appointment)}`"
        />

        <ErrorSummary />

        <form class="max-w-3xl space-y-6" novalidate @submit.prevent="submit">
            <CardPanel title="New Schedule" subtitle="The new slot is re-validated against operating hours and blocked dates.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormInput
                        v-model="form.values.preferred_date"
                        name="preferred_date"
                        type="date"
                        label="Preferred Date"
                        required
                    />
                    <FormInput
                        v-model="form.values.preferred_time"
                        name="preferred_time"
                        type="time"
                        label="Preferred Time"
                        required
                    />
                </div>

                <div class="mt-5">
                    <FormSelect
                        v-model="form.values.status"
                        name="status"
                        label="Status"
                        required
                        :options="statusOptions"
                    />
                </div>

                <div class="mt-5">
                    <label for="admin_notes" class="label">
                        Admin Notes
                        <span class="ml-1.5 rounded-pill bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-primary">Internal only</span>
                    </label>
                    <textarea
                        id="admin_notes"
                        name="admin_notes"
                        rows="3"
                        class="input"
                        placeholder="Only visible to staff."
                        v-model="form.values.admin_notes"
                    />
                    <p v-if="form.errors.admin_notes" class="input-error-text">{{ form.errors.admin_notes }}</p>
                </div>
            </CardPanel>

            <div class="flex flex-col gap-3 sm:flex-row-reverse">
                <button type="submit" class="btn-primary sm:min-w-44" :disabled="!canManage">Save Changes</button>
                <router-link
                    :to="{ name: 'admin.appointments.show', params: { id: appointment.id } }"
                    class="btn-ghost sm:min-w-44"
                >Cancel</router-link>
            </div>
        </form>
    </div>
</template>
