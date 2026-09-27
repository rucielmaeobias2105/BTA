<script setup>
import { computed, onBeforeUnmount, provide, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { appointmentsForUser } from '@/data/appointments';
import { findAdmin } from '@/data/admins';
import { roleCan } from '@/data/enums';
import { users } from '@/data/users';
import { addDays, addMinutes, today } from '@/lib/dates';
import { formatDate, initials, plural } from '@/lib/format';
import { currentAdmin, setFlash } from '@/lib/session';

/**
 * Admin Flow 9 — Registered Users Management (list).
 *
 * Ported from `admin/users/index.blade.php` and
 * `Admin\UserController::index()`. View / search / deactivate / delete only —
 * there are deliberately no new-user fields. The `search` and `status` filters
 * and the `paginate(15)` were server-side; they are now a `computed` over the
 * reactive `users` collection with `route.query` as the filter state, so a
 * bookmarked `/admin/users?search=maria&status=active` still shows the same rows.
 */
const route = useRoute();
const router = useRouter();

/** Rows per page. The controller used `paginate(15)`. */
const PER_PAGE = 10;

/**
 * The signed-in demo admin is Maia Arjud — `admins` id 1, role `super_admin`.
 * `currentAdmin.role` holds the *label*, so the machine value comes off the
 * `admins` record instead.
 */
const role = computed(() => findAdmin(currentAdmin.value?.id)?.role ?? 'super_admin');

const canManage = computed(() => roleCan(role.value, 'users.manage'));
const canDelete = computed(() => roleCan(role.value, 'users.delete'));

/** The global error bag Blade read through `<x-ui.errors />`. */
const errors = reactive({});

provide('form-errors', errors);

/* ------------------------------------------------------------------ */
/* Filters — the query string the Blade form submitted                 */
/* ------------------------------------------------------------------ */

const STATUS_OPTIONS = {
    '': 'All Users',
    active: 'Active only',
    inactive: 'Deactivated only',
};

const search = computed(() => String(route.query.search ?? ''));

const status = computed(() => {
    const requested = String(route.query.status ?? '');

    return requested in STATUS_OPTIONS ? requested : '';
});

const hasFilters = computed(() => Boolean(search.value || status.value));

/** `array_filter($filters)` — blank values are dropped, never written as `?search=`. */
function applyQuery(patch) {
    const next = { ...route.query, ...patch };

    Object.keys(next).forEach((key) => {
        if (next[key] === '' || next[key] === null || next[key] === undefined) delete next[key];
    });

    router.replace({ name: 'admin.users.index', query: next });
}

/** The search box holds a local copy and commits on a short debounce. */
const term = ref(search.value);

watch(search, (value) => {
    term.value = value;
});

let timer = null;

watch(term, (value) => {
    if (value === search.value) return;

    window.clearTimeout(timer);

    timer = window.setTimeout(() => applyQuery({ search: value, page: '' }), 300);
});

onBeforeUnmount(() => {
    window.clearTimeout(timer);
});

/* ------------------------------------------------------------------ */
/* The list                                                             */
/* ------------------------------------------------------------------ */

/** The headline counts ignore the filters, exactly like the two extra queries. */
const totalCount = computed(() => users.length);
const activeCount = computed(() => users.filter((user) => user.is_active).length);
const inactiveCount = computed(() => users.filter((user) => !user.is_active).length);

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return users
        .filter((user) => {
            if (status.value === 'active' && !user.is_active) return false;
            if (status.value === 'inactive' && user.is_active) return false;

            if (!needle) return true;

            // The query matched first name, last name, email and username.
            return [user.first_name, user.last_name, user.email, user.username, user.full_name].some(
                (field) => String(field ?? '').toLowerCase().includes(needle),
            );
        })
        // `->orderBy('last_name')->orderBy('first_name')`
        .sort((a, b) => a.last_name.localeCompare(b.last_name) || a.first_name.localeCompare(b.first_name));
});

/* ------------------------------------------------------------------ */
/* Pagination — `->paginate(15)` + `$users->links()`                    */
/* ------------------------------------------------------------------ */

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

    return `Showing ${first}–${Math.min(first + PER_PAGE - 1, filtered.value.length)} of ${plural(filtered.value.length, 'customer')}`;
});

function goToPage(target) {
    if (target < 1 || target > lastPage.value) return;

    applyQuery({ page: target === 1 ? '' : String(target) });
}

/* ------------------------------------------------------------------ */
/* Row helpers                                                          */
/* ------------------------------------------------------------------ */

/** `withCount('appointments')` — the count map replaced the pivot count. */
function bookingCount(user) {
    return appointmentsForUser(user.id).length;
}

/**
 * The demo `users` records carry no `created_at` column, so the "Joined" column
 * is derived from the id the same way the seeders derive every other relative
 * date — the value moves forward with the calendar instead of going stale.
 */
function joinedAt(user) {
    return addMinutes(addDays(today(), -((user.id * 37) + 26)), 540);
}

/* ------------------------------------------------------------------ */
/* Deactivate / Reactivate / Delete                                     */
/* ------------------------------------------------------------------ */

/** `UserController::toggleStatus()` */
function toggleStatus(user) {
    user.is_active = !user.is_active;

    setFlash(`${user.full_name}${user.is_active ? ' reactivated.' : ' deactivated.'}`);
}

/** `UserController::destroy()` — a soft delete; appointments are kept for reporting. */
function destroy(user) {
    if (!window.confirm(`Delete ${user.full_name}? Their appointment history is kept for reporting.`)) return;

    // The controller refused to delete the account linked to the signed-in admin.
    if (String(user.id) === String(currentAdmin.value?.id)) {
        errors.user = 'You cannot delete your own admin-linked account.';

        return;
    }

    const index = users.findIndex((row) => row.id === user.id);

    if (index === -1) return;

    users.splice(index, 1);

    delete errors.user;

    setFlash(`"${user.full_name}" has been deleted.`);

    router.push({ name: 'admin.users.index' });
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Customers"
            title="Registered Users"
            description="Search, review, deactivate or delete customer accounts. This screen is view/delete only — there are no new-user fields."
        />

        <ErrorSummary />

        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <div class="bta-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Users</p>
                <p class="mt-1 font-display text-2xl font-bold text-primary">{{ totalCount }}</p>
            </div>
            <div class="bta-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Active</p>
                <p class="mt-1 font-display text-2xl font-bold text-status-confirmed">{{ activeCount }}</p>
            </div>
            <div class="bta-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Deactivated</p>
                <p class="mt-1 font-display text-2xl font-bold text-status-cancelled">{{ inactiveCount }}</p>
            </div>
        </div>

        <form class="bta-card mb-6 p-5" novalidate @submit.prevent="applyQuery({ search: term, page: '' })">
            <div class="grid gap-4 md:grid-cols-12">
                <div class="md:col-span-7">
                    <FormInput
                        :model-value="term"
                        name="search"
                        label="Search"
                        placeholder="Name or email address"
                        @update:model-value="term = $event"
                    />
                </div>
                <div class="md:col-span-3">
                    <FormSelect
                        :model-value="status"
                        name="status"
                        label="Status"
                        :options="STATUS_OPTIONS"
                        @update:model-value="applyQuery({ status: $event, page: '' })"
                    />
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="btn-primary flex-1">Search</button>
                    <router-link v-if="hasFilters" :to="{ name: 'admin.users.index' }" class="btn-ghost">Clear</router-link>
                </div>
            </div>
        </form>

        <EmptyState
            v-if="filtered.length === 0"
            title="No users found"
            description="Try a different search term."
        />

        <template v-else>
            <div class="bta-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="bta-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Joined</th>
                                <th class="text-center">Bookings</th>
                                <th class="text-center">Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in rows" :key="user.id">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="user.profile_photo_path"
                                            :src="user.profile_photo_path"
                                            alt=""
                                            class="h-9 w-9 rounded-full object-cover"
                                        >
                                        <span
                                            v-else
                                            class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-cream"
                                        >{{ initials(user.first_name, user.last_name) }}</span>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-primary">{{ user.full_name }}</p>
                                            <p class="truncate text-xs text-ink-muted">{{ '@' + user.username }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-ink">{{ user.email }}</td>
                                <td class="whitespace-nowrap text-ink">{{ user.contact_number }}</td>
                                <td class="whitespace-nowrap text-ink">{{ formatDate(joinedAt(user), 'M j, Y') }}</td>
                                <td class="text-center text-ink">{{ bookingCount(user) }}</td>
                                <td class="text-center">
                                    <Badge
                                        :status="user.is_active ? 'confirmed' : 'cancelled'"
                                        :label="user.is_active ? 'Active' : 'Inactive'"
                                    />
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1.5">
                                        <router-link
                                            :to="{ name: 'admin.users.show', params: { id: user.id } }"
                                            class="btn-secondary btn-sm"
                                        >View</router-link>

                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="btn-gold btn-sm"
                                            @click="toggleStatus(user)"
                                        >{{ user.is_active ? 'Deactivate' : 'Reactivate' }}</button>

                                        <button
                                            v-if="canDelete"
                                            type="button"
                                            class="btn-danger btn-sm"
                                            @click="destroy(user)"
                                        >Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
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
        </template>
    </div>
</template>
