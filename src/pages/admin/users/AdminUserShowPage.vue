<script setup>
import { computed, provide, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StarRating from '@/components/ui/StarRating.vue';
import { appointmentsForUser, serviceNamesLabel } from '@/data/appointments';
import { findAdmin } from '@/data/admins';
import { AppointmentStatus, roleCan } from '@/data/enums';
import { findUser, users } from '@/data/users';
import { addDays, addMinutes, today } from '@/lib/dates';
import { formatDate, formatMoney, initials, sumBy } from '@/lib/format';
import { currentAdmin, setFlash } from '@/lib/session';

/**
 * Admin Flow 9 — Registered Users Management (detail).
 *
 * Ported from `admin/users/show.blade.php` and `Admin\UserController::show()`,
 * with the eager-loaded `appointments` and `reviews` relations replaced by
 * `appointmentsForUser()` and the review hanging off each appointment. The
 * aggregate `$stats` block is recomputed here, because there is no SQL to sum.
 */
const route = useRoute();
const router = useRouter();

const user = computed(() => findUser(route.params.id) ?? null);

/**
 * `currentAdmin.role` holds the *label*, so the machine value comes off the
 * `admins` record — the same resolution `AdminServicesIndexPage` uses.
 */
const role = computed(() => findAdmin(currentAdmin.value?.id)?.role ?? 'super_admin');

const canManage = computed(() => roleCan(role.value, 'users.manage'));
const canDelete = computed(() => roleCan(role.value, 'users.delete'));

const errors = reactive({});

provide('form-errors', errors);

/* ------------------------------------------------------------------ */
/* Relations                                                            */
/* ------------------------------------------------------------------ */

/** `$user->appointments->with('serviceLines')->latest()` */
const history = computed(() =>
    [...appointmentsForUser(user.value?.id)].sort(
        (a, b) => new Date(b.preferred_date) - new Date(a.preferred_date),
    ),
);

/** `$user->reviews` — a review is read straight off its appointment. */
const writtenReviews = computed(() => history.value.map((appointment) => appointment.review).filter(Boolean));

/* ------------------------------------------------------------------ */
/* `$stats`                                                             */
/* ------------------------------------------------------------------ */

const stats = computed(() => {
    const rows = history.value;
    const count = (status) => rows.filter((appointment) => appointment.status === status).length;

    return {
        total: rows.length,
        completed: count(AppointmentStatus.Completed.value),
        cancelled: count(AppointmentStatus.Cancelled.value),
        total_spent: sumBy(
            rows.filter((appointment) =>
                [
                    AppointmentStatus.Confirmed.value,
                    AppointmentStatus.InProgress.value,
                    AppointmentStatus.Completed.value,
                ].includes(appointment.status),
            ),
            'total_amount',
        ),
    };
});

/** The definition list the Blade view looped over for the stat cards. */
const statCards = computed(() => [
    { label: 'Total Bookings', value: stats.value.total },
    { label: 'Completed', value: stats.value.completed },
    { label: 'Cancelled', value: stats.value.cancelled },
    { label: 'Lifetime Value', value: formatMoney(stats.value.total_spent, 2) },
]);

/* ------------------------------------------------------------------ */
/* Dates                                                                */
/* ------------------------------------------------------------------ */

/**
 * The demo `users` records carry no `created_at` / `last_login_at` columns, so
 * both are derived from the id on the same "relative to today" basis the seeders
 * use. An inactive account has, by definition, not signed in recently.
 */
function joinedAt(id) {
    return addMinutes(addDays(today(), -((id * 37) + 26)), 540);
}

function lastLoginAt(record) {
    if (!record?.is_active) return null;

    return addMinutes(addDays(today(), -((record.id * 7) % 18)), 660);
}

const headerDescription = computed(() => {
    if (!user.value) return '';

    const lastLogin = lastLoginAt(user.value);

    return `Joined ${formatDate(joinedAt(user.value.id), 'F j, Y')} · Last login ${lastLogin ? formatDate(lastLogin, 'M j, Y') : 'never'}`;
});

/** The `dl` the Blade view looped over. */
const accountDetails = computed(() => {
    if (!user.value) return [];

    const joined = joinedAt(user.value.id);
    const lastLogin = lastLoginAt(user.value);

    return [
        { label: 'Full Name', value: user.value.full_name },
        { label: 'Email', value: user.value.email },
        { label: 'Username', value: `@${user.value.username}` },
        { label: 'Contact Number', value: user.value.contact_number },
        { label: 'Registered', value: formatDate(joined, 'M j, Y g:i A') },
        { label: 'Last Login', value: lastLogin ? formatDate(lastLogin, 'M j, Y g:i A') : 'Never' },
    ];
});

function statusMeta(appointment) {
    return AppointmentStatus[appointment.status] ?? null;
}

/* ------------------------------------------------------------------ */
/* Actions                                                              */
/* ------------------------------------------------------------------ */

/** `UserController::toggleStatus()` */
function toggleStatus() {
    const record = user.value;

    if (!record) return;

    record.is_active = !record.is_active;

    setFlash(`${record.full_name}${record.is_active ? ' reactivated.' : ' deactivated.'}`);
}

/** `UserController::destroy()` — a soft delete; appointments are kept for reporting. */
function destroy() {
    const record = user.value;

    if (!record) return;

    if (!window.confirm(`Delete ${record.full_name}? Their appointment history is kept for reporting.`)) return;

    if (String(record.id) === String(currentAdmin.value?.id)) {
        errors.user = 'You cannot delete your own admin-linked account.';

        return;
    }

    const index = users.findIndex((row) => row.id === record.id);

    if (index === -1) return;

    users.splice(index, 1);

    delete errors.user;

    setFlash(`"${record.full_name}" has been deleted.`);

    router.push({ name: 'admin.users.index' });
}
</script>

<template>
    <div>
        <router-link
            :to="{ name: 'admin.users.index' }"
            class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to users
        </router-link>

        <EmptyState
            v-if="!user"
            title="User not found"
            description="This customer account no longer exists."
        >
            <router-link :to="{ name: 'admin.users.index' }" class="btn-primary">Back to users</router-link>
        </EmptyState>

        <template v-else>
            <PageHeader
                eyebrow="Customer"
                :title="user.full_name"
                :description="headerDescription"
            >
                <template #actions>
                    <button
                        v-if="canManage"
                        type="button"
                        class="btn-gold btn-sm"
                        @click="toggleStatus"
                    >{{ user.is_active ? 'Deactivate Account' : 'Reactivate Account' }}</button>

                    <button
                        v-if="canDelete"
                        type="button"
                        class="btn-danger btn-sm"
                        @click="destroy"
                    >Delete</button>
                </template>
            </PageHeader>

            <ErrorSummary />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="card in statCards" :key="card.label" class="bta-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ card.label }}</p>
                    <p class="mt-1.5 font-display text-2xl font-bold text-primary">{{ card.value }}</p>
                </div>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <CardPanel title="Appointment History">
                        <p v-if="history.length === 0" class="text-sm text-ink-muted">This customer has no appointments yet.</p>

                        <div v-else class="overflow-x-auto">
                            <table class="bta-table">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Service(s)</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th class="text-right">Total</th>
                                        <th class="text-right"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="appointment in history" :key="appointment.id">
                                        <td class="whitespace-nowrap font-mono text-xs font-semibold text-primary">{{ appointment.reference_number }}</td>
                                        <td class="max-w-xs truncate text-ink">{{ serviceNamesLabel(appointment) }}</td>
                                        <td class="whitespace-nowrap text-ink">{{ formatDate(appointment.preferred_date, 'M j, Y') }}</td>
                                        <td>
                                            <Badge
                                                v-if="statusMeta(appointment)"
                                                :status="statusMeta(appointment).badge"
                                                :label="statusMeta(appointment).label"
                                            />
                                        </td>
                                        <td class="whitespace-nowrap text-right font-medium text-primary">{{ formatMoney(appointment.total_amount) }}</td>
                                        <td class="text-right">
                                            <router-link
                                                :to="{ name: 'admin.appointments.show', params: { id: appointment.id } }"
                                                class="btn-ghost btn-sm"
                                            >Open</router-link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardPanel>

                    <CardPanel title="Reviews" :subtitle="`${writtenReviews.length} review(s) written`">
                        <p v-if="writtenReviews.length === 0" class="text-sm text-ink-muted">No reviews written.</p>

                        <ul v-else class="space-y-3.5">
                            <li
                                v-for="review in writtenReviews"
                                :key="review.id"
                                class="border-b border-primary/8 pb-3.5 last:border-0 last:pb-0"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <StarRating :model-value="review.rating" :interactive="false" size="sm" />
                                    <span class="text-xs text-ink-muted">{{ formatDate(review.created_at, 'M j, Y') }}</span>
                                </div>
                                <p class="mt-1.5 text-sm text-ink">{{ review.message }}</p>
                            </li>
                        </ul>
                    </CardPanel>
                </div>

                <aside>
                    <CardPanel title="Account Details">
                        <div class="mb-5 flex items-center gap-4">
                            <img
                                v-if="user.profile_photo_path"
                                :src="user.profile_photo_path"
                                alt=""
                                class="h-16 w-16 rounded-full object-cover ring-2 ring-gold ring-offset-2 ring-offset-cream"
                            >
                            <span
                                v-else
                                class="flex h-16 w-16 items-center justify-center rounded-full bg-primary font-display text-lg font-semibold text-cream ring-2 ring-gold ring-offset-2 ring-offset-cream"
                            >{{ initials(user.first_name, user.last_name) }}</span>
                            <div>
                                <Badge
                                    :status="user.is_active ? 'confirmed' : 'cancelled'"
                                    :label="user.is_active ? 'Active' : 'Deactivated'"
                                />
                            </div>
                        </div>

                        <dl class="space-y-3.5 text-sm">
                            <div
                                v-for="row in accountDetails"
                                :key="row.label"
                                class="border-b border-primary/5 pb-3.5 last:border-0 last:pb-0"
                            >
                                <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ row.label }}</dt>
                                <dd class="mt-0.5 break-words text-ink">{{ row.value }}</dd>
                            </div>
                        </dl>
                    </CardPanel>
                </aside>
            </div>
        </template>
    </div>
</template>
