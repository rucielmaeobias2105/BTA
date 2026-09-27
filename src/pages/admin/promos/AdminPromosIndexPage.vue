<script setup>
import { computed, onBeforeUnmount, provide, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { findAdmin } from '@/data/admins';
import { roleCan } from '@/data/enums';
import { isCurrentlyValid, promos, validityLabel } from '@/data/promos';
import { users } from '@/data/users';
import { addDays, addMinutes, today } from '@/lib/dates';
import { stripTags, truncate } from '@/lib/format';
import { currentAdmin, setFlash } from '@/lib/session';

/**
 * Admin Flow 13 (part 1) — Promo & Announcements list.
 *
 * Ported from `admin/promos/index.blade.php` and `Admin\PromoController`'s
 * `index()`, `announce()` and `destroy()`. The `search` / `filter` query params
 * and the `paginate(12)` were server-side; they are a `computed` over the
 * reactive `promos` collection with `route.query` as the filter state.
 */
const route = useRoute();
const router = useRouter();

/** Cards per page. The controller used `paginate(12)`. */
const PER_PAGE = 9;

/**
 * `currentAdmin.role` holds the *label*, so the machine value comes off the
 * `admins` record — the same resolution `AdminServicesIndexPage` uses.
 *
 * `admin.promos.manage` guarded the whole resource plus the announce route, so
 * every write control on this screen is behind it.
 */
const role = computed(() => findAdmin(currentAdmin.value?.id)?.role ?? 'super_admin');

const canManage = computed(() => roleCan(role.value, 'promos.manage'));

/** The global error bag Blade read through `<x-ui.errors />`. */
const errors = reactive({});

provide('form-errors', errors);

/* ------------------------------------------------------------------ */
/* Filters — the query string the Blade form submitted                 */
/* ------------------------------------------------------------------ */

const FILTER_OPTIONS = {
    '': 'All Promos',
    active: 'Currently active',
    expired: 'Expired',
};

const search = computed(() => String(route.query.search ?? ''));

const filter = computed(() => {
    const requested = String(route.query.filter ?? '');

    return requested in FILTER_OPTIONS ? requested : '';
});

const hasFilters = computed(() => Boolean(search.value || filter.value));

function applyQuery(patch) {
    const next = { ...route.query, ...patch };

    Object.keys(next).forEach((key) => {
        if (next[key] === '' || next[key] === null || next[key] === undefined) delete next[key];
    });

    router.replace({ name: 'admin.promos.index', query: next });
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
/* Headline counts                                                      */
/* ------------------------------------------------------------------ */

/** `Promo::query()->active()->count()` — the `scopeActive()` window. */
const activeCount = computed(() => promos.filter(isCurrentlyValid).length);

/** `User::query()->where('is_active', true)->count()` */
const audienceSize = computed(() => users.filter((user) => user.is_active).length);

/* ------------------------------------------------------------------ */
/* The list                                                             */
/* ------------------------------------------------------------------ */

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return [...promos]
        .filter((promo) => {
            // `->active()` — enabled and inside its window.
            if (filter.value === 'active' && !isCurrentlyValid(promo)) return false;

            // `->whereDate('ends_at', '<', today())`
            if (filter.value === 'expired' && !(new Date(promo.ends_at) < today())) return false;

            if (!needle) return true;

            // The query matched title and description.
            return [promo.title, promo.description].some((field) =>
                String(field ?? '').toLowerCase().includes(needle),
            );
        })
        // `->orderByDesc('starts_at')`
        .sort((a, b) => new Date(b.starts_at) - new Date(a.starts_at));
});

/* ------------------------------------------------------------------ */
/* Pagination                                                           */
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

    return `Showing ${first}–${Math.min(first + PER_PAGE - 1, filtered.value.length)} of ${filtered.value.length}`;
});

function goToPage(target) {
    if (target < 1 || target > lastPage.value) return;

    applyQuery({ page: target === 1 ? '' : String(target) });
}

/* ------------------------------------------------------------------ */
/* Card helpers                                                         */
/* ------------------------------------------------------------------ */

/** `! $promo->is_active || $promo->starts_at->isFuture()` */
function isUpcoming(promo) {
    return !promo.is_active || new Date(promo.starts_at) > today();
}

function isActive(promo) {
    return isCurrentlyValid(promo);
}

/* ------------------------------------------------------------------ */
/* Announce                                                             */
/* ------------------------------------------------------------------ */

/** One `<select>` per card, so the choice is held per promo. */
const audiences = reactive({});

function audienceFor(promo) {
    return audiences[promo.id] ?? 'all';
}

/**
 * The demo `users` records carry no `created_at` column, so the "last 90 days"
 * audience is derived from the id on the same relative-to-today basis the
 * seeders use for every other generated date.
 */
function joinedAt(id) {
    return addMinutes(addDays(today(), -((id * 37) + 26)), 540);
}

function audienceCount(audience) {
    if (audience === 'active') return audienceSize.value;

    if (audience === 'recent') {
        const cutoff = addDays(today(), -90).getTime();

        return users.filter((user) => user.is_active && joinedAt(user.id).getTime() >= cutoff).length;
    }

    return audienceSize.value;
}

/** `PromoController::announce()` — flag the promo and count the recipients. */
function announce(promo, audience) {
    audiences[promo.id] = audience;

    if (!window.confirm('Send this promo as a notification to your customers?')) return;

    const recipients = audienceCount(audience);

    // `$promo->forceFill(['notified' => true])->save()`
    promo.notified = true;

    setFlash(`Promo announced to ${recipients} customer(s).`);
}

/* ------------------------------------------------------------------ */
/* Delete                                                               */
/* ------------------------------------------------------------------ */

function destroy(promo) {
    if (!window.confirm(`Delete “${promo.title}”?`)) return;

    const index = promos.findIndex((row) => row.id === promo.id);

    if (index === -1) return;

    promos.splice(index, 1);

    delete errors.promo;

    setFlash(`"${promo.title}" deleted.`);
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Marketing"
            title="Promo & Announcements"
            description="Active promos appear as a banner on the site and can be pushed to customers as notifications."
        >
            <template #actions>
                <router-link v-if="canManage" :to="{ name: 'admin.promos.create' }" class="btn-primary btn-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    New Promo
                </router-link>
            </template>
        </PageHeader>

        <ErrorSummary />

        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <div class="bta-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Active Now</p>
                <p class="mt-1 font-display text-2xl font-bold text-status-confirmed">{{ activeCount }}</p>
            </div>
            <div class="bta-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Reachable Customers</p>
                <p class="mt-1 font-display text-2xl font-bold text-primary">{{ audienceSize }}</p>
            </div>
            <div class="bta-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Promos</p>
                <p class="mt-1 font-display text-2xl font-bold text-primary">{{ promos.length }}</p>
            </div>
        </div>

        <form class="bta-card mb-6 p-5" novalidate @submit.prevent="applyQuery({ search: term, page: '' })">
            <div class="grid gap-4 md:grid-cols-12">
                <div class="md:col-span-7">
                    <FormInput
                        :model-value="term"
                        name="search"
                        label="Search"
                        placeholder="Title or description"
                        @update:model-value="term = $event"
                    />
                </div>
                <div class="md:col-span-3">
                    <FormSelect
                        :model-value="filter"
                        name="filter"
                        label="Filter"
                        :options="FILTER_OPTIONS"
                        @update:model-value="applyQuery({ filter: $event, page: '' })"
                    />
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="btn-primary flex-1">Search</button>
                    <router-link v-if="hasFilters" :to="{ name: 'admin.promos.index' }" class="btn-ghost">Clear</router-link>
                </div>
            </div>
        </form>

        <EmptyState
            v-if="filtered.length === 0"
            title="No promos yet"
            description="Create a promo to announce an offer to your customers."
        >
            <router-link v-if="canManage" :to="{ name: 'admin.promos.create' }" class="btn-primary">New Promo</router-link>
        </EmptyState>

        <template v-else>
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="promo in rows"
                    :key="promo.id"
                    class="flex flex-col overflow-hidden rounded-card border bg-cream shadow-card"
                    :class="isActive(promo) ? 'border-gold/40' : 'border-primary/12'"
                >
                    <img
                        v-if="promo.image_path"
                        :src="promo.image_path"
                        :alt="promo.title"
                        class="aspect-[16/9] w-full object-cover"
                    >
                    <div v-else class="flex aspect-[16/9] w-full items-center justify-center bg-gradient-to-br from-linen to-gold-light/40">
                        <svg class="h-10 w-10 text-gold-dark/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V11.25m17.25 0a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5m17.25 0V4.5A1.5 1.5 0 0 0 18.75 3H5.25A1.5 1.5 0 0 0 3.75 4.5v6.75m14.25-6.75H12m6.75 0H12m0 0H5.25M12 3v1.5"/></svg>
                    </div>

                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <Badge v-if="isActive(promo)" status="completed" label="Active" />
                            <Badge v-else-if="isUpcoming(promo)" status="pending" label="Upcoming" />
                            <Badge v-else status="cancelled" label="Expired" />

                            <Badge v-if="!promo.is_active" status="cancelled" label="Disabled" />
                            <Badge v-if="promo.notified" status="gold" label="Announced" />
                        </div>

                        <h3 class="mt-2.5 font-display text-base font-semibold leading-snug text-primary">{{ promo.title }}</h3>
                        <p class="mt-1.5 line-clamp-3 flex-1 text-sm text-ink-muted">{{ truncate(stripTags(promo.description), 130) }}</p>
                        <p class="mt-3 text-xs font-medium text-gold-dark">{{ validityLabel(promo) }}</p>

                        <div v-if="canManage" class="mt-4 flex flex-wrap gap-1.5 border-t border-primary/10 pt-3">
                            <router-link
                                :to="{ name: 'admin.promos.edit', params: { id: promo.id } }"
                                class="btn-secondary btn-sm"
                            >Edit</router-link>

                            <select
                                :value="audienceFor(promo)"
                                class="input w-28 py-1.5 text-xs"
                                aria-label="Announce audience"
                                @change="announce(promo, $event.target.value)"
                            >
                                <option value="all">All ({{ audienceSize }})</option>
                                <option value="active">Active only</option>
                                <option value="recent">Last 90 days</option>
                            </select>

                            <button type="button" class="btn-danger btn-sm" @click="destroy(promo)">Delete</button>
                        </div>
                    </div>
                </article>
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
