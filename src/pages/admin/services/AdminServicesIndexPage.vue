<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { findAdmin } from '@/data/admins';
import { roleCan } from '@/data/enums';
import { inventoryForService } from '@/data/inventory';
import { durationLabel, services } from '@/data/services';
import { formatMoney, truncate } from '@/lib/format';
import { currentAdmin, setFlash } from '@/lib/session';

/**
 * Admin Flow 5 — Service Management (list).
 *
 * Ported from `admin/services/index.blade.php` and
 * `Admin\ServiceController::index()`. The query-string filters, the fixed
 * category/name ordering and the paginator all ran on the server; they are now
 * a `computed` over the reactive `services` collection, with `route.query` as
 * the single source of truth for the filter state.
 */

const route = useRoute();
const router = useRouter();

/** Rows per page. The controller used `paginate(15)`. */
const PAGE_SIZE = 10;

/** The signed-in demo admin is Maia Arjud — `admins` id 1, role `super_admin`. */
const role = computed(() => findAdmin(currentAdmin.value?.id)?.role ?? 'super_admin');

/** `admin.catalog.manage`, the ability `admin.services.*` was gated behind. */
const canManage = computed(() => roleCan(role.value, 'catalog.manage'));

/* ------------------------------------------------------------------ */
/* Filters — `Service::scopeSearch()` + `Service::scopeCategory()`      */
/* ------------------------------------------------------------------ */

const search = computed(() => String(route.query.search ?? ''));
const category = computed(() => String(route.query.category ?? ''));

const hasFilters = computed(() => Boolean(search.value || category.value));

/** `Service::query()->distinct()->orderBy('category')->pluck('category')` */
const categories = computed(() => [...new Set(services.map((service) => service.category))].sort());

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return services
        .filter((service) => {
            if (category.value && service.category !== category.value) return false;

            if (!needle) return true;

            // The scope matched name, description and category.
            return [service.name, service.description, service.category].some((field) =>
                String(field ?? '').toLowerCase().includes(needle),
            );
        })
        .sort((a, b) => a.category.localeCompare(b.category) || a.name.localeCompare(b.name));
});

/* ------------------------------------------------------------------ */
/* Paginator — `->paginate(15)` + `$services->links()`                 */
/* ------------------------------------------------------------------ */

const page = computed(() => Math.max(1, Number(route.query.page) || 1));
const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)));
const rows = computed(() => filtered.value.slice((page.value - 1) * PAGE_SIZE, page.value * PAGE_SIZE));
const rangeStart = computed(() => (filtered.value.length ? ((page.value - 1) * PAGE_SIZE) + 1 : 0));
const rangeEnd = computed(() => Math.min(page.value * PAGE_SIZE, filtered.value.length));

/**
 * `withQueryString()` — the filter state lives in the URL, so a copy-pasted or
 * bookmarked link reproduces the same view. Empty values are dropped instead of
 * being written back as `?search=`, which is what `array_filter($filters)` did.
 */
function updateQuery(patch) {
    const merged = { search: search.value, category: category.value, page: '', ...patch };
    const query = {};

    Object.entries(merged).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) return;

        query[key] = String(value);
    });

    router.replace({ name: 'admin.services.index', query });
}

function goToPage(target) {
    updateQuery({ page: target });
}

/** The search box is free text, so it commits on a short debounce. */
const term = ref(search.value);

watch(search, (value) => {
    term.value = value;
});

let timer = null;

watch(term, (value) => {
    if (value === search.value) return;

    if (timer) clearTimeout(timer);

    timer = setTimeout(() => updateQuery({ search: value }), 250);
});

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
});

/* ------------------------------------------------------------------ */
/* Row actions                                                         */
/* ------------------------------------------------------------------ */

/** `withCount('inventoryItems'])` — the usage map replaced the pivot table. */
function itemCount(service) {
    return inventoryForService(service.name).length;
}

/** Soft delete, so historical appointment lines keep a resolvable service. */
function destroy(service) {
    if (!window.confirm(`Delete “${service.name}”? Historical bookings keep their saved details.`)) return;

    const index = services.findIndex((row) => row.id === service.id);

    if (index === -1) return;

    services.splice(index, 1);

    setFlash(`Service "${service.name}" deleted.`);
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Catalogue"
            title="Services"
            description="Add, edit and remove services, including short/long hair style variants."
        >
            <template #actions>
                <router-link v-if="canManage" :to="{ name: 'admin.services.create' }" class="btn-primary btn-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Add Service
                </router-link>
            </template>
        </PageHeader>

        <form class="bta-card mb-6 p-5" @submit.prevent="updateQuery({ search: term })">
            <div class="grid gap-4 md:grid-cols-12">
                <div class="md:col-span-6">
                    <FormInput
                        :model-value="term"
                        name="search"
                        label="Search"
                        placeholder="Name, description or category"
                        @update:model-value="onSearch"
                    />
                </div>
                <div class="md:col-span-4">
                    <FormSelect
                        :model-value="category"
                        name="category"
                        label="Category"
                        include-blank
                        blank-label="All Categories"
                        :options="Object.fromEntries(categories.map((value) => [value, value]))"
                        @update:model-value="updateQuery({ category: $event })"
                    />
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="btn-primary flex-1">Filter</button>
                    <router-link v-if="hasFilters" :to="{ name: 'admin.services.index' }" class="btn-ghost">Clear</router-link>
                </div>
            </div>
        </form>

        <EmptyState
            v-if="filtered.length === 0"
            title="No services found"
            description="Add your first service to get started."
        >
            <router-link v-if="canManage" :to="{ name: 'admin.services.create' }" class="btn-primary">Add Service</router-link>
        </EmptyState>

        <template v-else>
            <div class="bta-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="bta-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Category</th>
                                <th>Variants</th>
                                <th class="text-right">Price</th>
                                <th class="text-right">Duration</th>
                                <th class="text-center">Items</th>
                                <th class="text-center">Status</th>
                                <th v-if="canManage" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="service in rows" :key="service.id">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="service.photo_path"
                                            :src="service.photo_path"
                                            alt=""
                                            class="h-10 w-10 shrink-0 rounded-lg object-cover"
                                        >
                                        <span
                                            v-else
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-linen text-gold-dark"
                                        >
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M9.75 6.75 4.5 12l5.25 5.25M14.25 6.75 19.5 12l-5.25 5.25"/></svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-primary">{{ service.name }}</p>
                                            <p class="truncate text-xs text-ink-muted">{{ truncate(service.description, 46) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap"><span class="badge badge-gold">{{ service.category }}</span></td>
                                <td class="text-center">
                                    <span class="text-sm font-medium text-primary">{{ service.variants.length }}</span>
                                </td>
                                <td class="whitespace-nowrap text-right font-medium text-primary">{{ formatMoney(service.price) }}</td>
                                <td class="whitespace-nowrap text-right text-ink">{{ durationLabel(service.duration_minutes) }}</td>
                                <td class="text-center text-ink">{{ itemCount(service) }}</td>
                                <td class="text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <Badge
                                            :status="service.is_active ? 'confirmed' : 'cancelled'"
                                            :label="service.is_active ? 'Active' : 'Hidden'"
                                        />
                                        <Badge v-if="service.is_featured" status="best_seller" label="Featured" />
                                    </div>
                                </td>
                                <td v-if="canManage">
                                    <div class="flex justify-end gap-1.5">
                                        <router-link
                                            :to="{ name: 'admin.services.variants', params: { id: service.id } }"
                                            class="btn-ghost btn-sm"
                                            title="Variants"
                                        >Variants</router-link>
                                        <router-link
                                            :to="{ name: 'admin.services.edit', params: { id: service.id } }"
                                            class="btn-secondary btn-sm"
                                        >Edit</router-link>
                                        <button type="button" class="btn-danger btn-sm" @click="destroy(service)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-ink-muted">
                    Showing {{ rangeStart }}–{{ rangeEnd }} of {{ filtered.length }}
                </p>

                <div class="flex items-center gap-2">
                    <button type="button" class="btn-ghost btn-sm" :disabled="page <= 1" @click="goToPage(page - 1)">
                        Previous
                    </button>
                    <span class="text-sm text-ink-muted">Page {{ page }} of {{ pageCount }}</span>
                    <button type="button" class="btn-ghost btn-sm" :disabled="page >= pageCount" @click="goToPage(page + 1)">
                        Next
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
