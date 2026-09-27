<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import Badge from '@/components/ui/Badge.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StarRating from '@/components/ui/StarRating.vue';
import {
    appointmentsForUser,
    averageRating,
    canBeRated,
    reviews,
    reviewableAppointments,
    serviceNamesLabel,
    statsForUser,
    timeLabel,
    upcomingAppointments,
} from '@/data/appointments';
import { AppointmentStatus } from '@/data/enums';
import { unreadNotificationCount } from '@/data/notifications';
import { findAdmin } from '@/data/admins';
import { currentUser } from '@/lib/session';
import { formatDate, formatMoney, initialsFrom } from '@/lib/format';

/**
 * Customer Flow 13 — Customer Dashboard.
 *
 * Ported from `customer/dashboard.blade.php` and `Customer\DashboardController`.
 * Every `$user->appointments()` query became a lookup scoped to the signed-in
 * demo customer (`currentUser`), and each read-only accessor got a local helper
 * below so the copy matches the server-rendered version character for character.
 */

/** `Appointment::getDateTimeLabelAttribute()` — "Apr 18, 2026 at 9:30 AM". */
function dateTimeLabel(appointment) {
    return `${formatDate(appointment.preferred_date, 'M j, Y')} at ${timeLabel(appointment.preferred_time)}`;
}

const user = computed(() => currentUser.value);

const mine = computed(() => appointmentsForUser(currentUser.value?.id));

/** `scopeUpcoming()->orderByDate()->orderByTime()->first()`. */
const upcoming = computed(() => upcomingAppointments(mine.value)[0] ?? null);

/** Completed or cancelled visits, first four in insertion order, as the query returned them. */
const recent = computed(() =>
    mine.value
        .filter((appointment) =>
            [AppointmentStatus.Completed.value, AppointmentStatus.Cancelled.value].includes(appointment.status))
        .slice(0, 4),
);

const stats = computed(() => statsForUser(currentUser.value?.id));
const unreadCount = computed(() => unreadNotificationCount.value);
const awaitingRating = computed(() => reviewableAppointments(currentUser.value?.id).length);
const reviewCount = computed(() => reviews.value.length);

const statCards = computed(() => [
    { label: 'Upcoming', value: stats.value.upcoming, icon: 'calendar-days', text: 'text-status-confirmed', bg: 'bg-status-confirmed-bg' },
    { label: 'Pending', value: stats.value.pending, icon: 'clock', text: 'text-status-pending', bg: 'bg-status-pending-bg' },
    { label: 'Completed', value: stats.value.completed, icon: 'check-circle', text: 'text-status-completed', bg: 'bg-status-completed-bg' },
    { label: 'Unread Alerts', value: unreadCount.value, icon: 'bell', text: 'text-primary', bg: 'bg-gold/20' },
]);

const quickLinks = computed(() => [
    { label: 'Book Appointment', icon: 'calendar-days', to: { name: 'appointments.create' }, badge: 0 },
    { label: 'My Appointments', icon: 'calendar-days', to: { name: 'appointments.index' }, badge: 0 },
    { label: 'Browse Services', icon: 'sparkles', to: { name: 'services.index' }, badge: 0 },
    { label: 'Notifications', icon: 'bell', to: { name: 'notifications.index' }, badge: unreadCount.value },
    { label: 'Edit Profile', icon: 'user', to: { name: 'profile.edit' }, badge: 0 },
    { label: 'Contact Us', icon: 'chat-bubble-left-right', to: { name: 'contact.create' }, badge: 0 },
]);

const monogram = computed(() => initialsFrom(user.value?.full_name ?? ''));

const stylistName = computed(() => {
    if (!upcoming.value?.preferred_stylist_id) return null;

    return findAdmin(upcoming.value.preferred_stylist_id)?.full_name ?? null;
});
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <!-- Greeting -->
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <img
                    v-if="user?.profile_photo_path"
                    :src="user.profile_photo_path"
                    alt=""
                    class="h-14 w-14 rounded-full object-cover ring-2 ring-gold ring-offset-2 ring-offset-cream"
                >
                <span
                    v-else
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-primary font-display text-lg font-semibold text-cream ring-2 ring-gold ring-offset-2 ring-offset-cream"
                >
                    {{ monogram }}
                </span>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gold-dark">Welcome Back</p>
                    <h1 class="mt-0.5 font-display text-2xl font-bold tracking-tight text-primary sm:text-3xl">
                        {{ user?.first_name }}
                    </h1>
                </div>
            </div>

            <router-link :to="{ name: 'appointments.create' }" class="btn-primary">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Book Appointment
            </router-link>
        </div>

        <!-- Stat cards -->
        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="card in statCards" :key="card.label" class="bta-card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ card.label }}</p>
                        <p class="mt-2 font-display text-3xl font-bold text-primary">{{ card.value }}</p>
                    </div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-full" :class="[card.bg, card.text]">
                        <AppIcon :name="card.icon" class="h-5 w-5" />
                    </span>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Upcoming appointment snapshot -->
            <div class="space-y-6 lg:col-span-2">
                <CardPanel title="Your Next Appointment" accent="maroon">
                    <template #actions>
                        <router-link
                            :to="{ name: 'appointments.index' }"
                            class="text-xs font-medium text-primary underline underline-offset-2 hover:text-primary-dark"
                        >View all</router-link>
                    </template>

                    <div v-if="upcoming" class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    :status="AppointmentStatus[upcoming.status].badge"
                                    :label="AppointmentStatus[upcoming.status].label"
                                />
                                <span class="text-xs text-ink-muted">{{ upcoming.reference_number }}</span>
                            </div>

                            <h3 class="mt-3 font-display text-xl font-semibold text-primary">{{ serviceNamesLabel(upcoming) }}</h3>

                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                                    <dd class="text-ink">{{ dateTimeLabel(upcoming) }}</dd>
                                </div>

                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    <dd class="font-semibold text-primary">{{ formatMoney(upcoming.total_amount) }}</dd>
                                </div>

                                <div v-if="stylistName" class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                                    <dd class="text-ink">{{ stylistName }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2 sm:w-40 sm:flex-col">
                            <router-link
                                :to="{ name: 'appointments.show', params: { id: upcoming.id } }"
                                class="btn-secondary btn-sm flex-1 sm:w-full"
                            >View</router-link>
                            <router-link
                                :to="{ name: 'appointments.reschedule', params: { id: upcoming.id } }"
                                class="btn-gold btn-sm flex-1 sm:w-full"
                            >Reschedule</router-link>
                            <router-link
                                :to="{ name: 'appointments.cancel', params: { id: upcoming.id } }"
                                class="btn-danger btn-sm flex-1 sm:w-full"
                            >Cancel</router-link>
                        </div>
                    </div>

                    <EmptyState
                        v-else
                        title="No upcoming appointments"
                        description="Book your next treatment and we'll see you soon."
                    >
                        <router-link :to="{ name: 'appointments.create' }" class="btn-primary">Book an Appointment</router-link>
                    </EmptyState>
                </CardPanel>

                <!-- Recent activity -->
                <CardPanel title="Recent Activity">
                    <p v-if="recent.length === 0" class="text-sm text-ink-muted">No past visits on record yet.</p>

                    <ul v-else class="divide-y divide-primary/8">
                        <li
                            v-for="appointment in recent"
                            :key="appointment.id"
                            class="flex items-center justify-between gap-4 py-3.5 first:pt-0 last:pb-0"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink">{{ serviceNamesLabel(appointment) }}</p>
                                <p class="text-xs text-ink-muted">{{ formatDate(appointment.preferred_date, 'M j, Y') }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                <Badge
                                    :status="AppointmentStatus[appointment.status].badge"
                                    :label="AppointmentStatus[appointment.status].label"
                                />
                                <router-link
                                    v-if="canBeRated(appointment)"
                                    :to="{ name: 'appointments.rate.create', params: { id: appointment.id } }"
                                    class="text-xs font-medium text-primary underline underline-offset-2"
                                >Rate</router-link>
                            </div>
                        </li>
                    </ul>
                </CardPanel>
            </div>

            <!-- Quick links -->
            <aside class="space-y-6">
                <CardPanel title="Quick Links">
                    <div class="space-y-2.5">
                        <router-link
                            v-for="link in quickLinks"
                            :key="link.label"
                            :to="link.to"
                            class="flex items-center gap-3 rounded-xl border border-primary/12 bg-white/50 px-4 py-3 text-sm font-medium text-ink transition hover:border-gold hover:bg-gold/8"
                        >
                            <AppIcon :name="link.icon" class="h-5 w-5 shrink-0 text-primary" />
                            <span class="flex-1">{{ link.label }}</span>
                            <span
                                v-if="link.badge > 0"
                                class="rounded-pill bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-cream"
                            >{{ link.badge }}</span>
                            <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </router-link>
                    </div>
                </CardPanel>

                <div v-if="awaitingRating > 0" class="rounded-card bg-gold/12 p-5">
                    <p class="font-display text-base font-semibold text-primary">
                        {{ awaitingRating }} visit{{ awaitingRating === 1 ? '' : 's' }} awaiting your review
                    </p>
                    <p class="mt-1 text-sm text-ink-muted">Let others know how we did.</p>
                    <router-link
                        :to="{ name: 'appointments.index', query: { status: 'completed' } }"
                        class="btn-secondary btn-sm mt-4"
                    >Rate Now</router-link>
                </div>

                <CardPanel title="Your Rating">
                    <div class="flex items-center gap-4">
                        <p class="font-display text-4xl font-bold text-primary">{{ averageRating || '—' }}</p>
                        <div>
                            <StarRating :model-value="Math.round(averageRating)" :interactive="false" />
                            <p class="mt-1 text-xs text-ink-muted">{{ reviewCount }} review{{ reviewCount === 1 ? '' : 's' }} from our clients</p>
                        </div>
                    </div>
                </CardPanel>
            </aside>
        </div>
    </div>
</template>
