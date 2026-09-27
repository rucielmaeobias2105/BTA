<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { findAdmin } from '@/data/admins';
import { ItemTag, roleCan } from '@/data/enums';
import { inventoryItems } from '@/data/inventory';
import { durationLabel, services } from '@/data/services';
import { formatMoney, plural } from '@/lib/format';
import { currentAdmin } from '@/lib/session';

/**
 * Admin Flow 4 — Services & Items overview.
 *
 * Ported from `admin/catalog/index.blade.php` and
 * `Admin\CatalogController::index()`. Two independent queries (services and
 * inventory items) shared one filter form, one `page` query param and one
 * `?type=` switcher that hid a whole list with `whereRaw('1 = 0')`; all of that
 * is now a `computed` per collection reading `route.query`.
 */

/** Rows per page. The controller used `paginate(20)` for both lists. */
const PAGE_SIZE = 10;

const route = useRoute();
const router = useRouter();

/**
 * The signed-in demo admin is Maia Arjud (`admins` id 1, role `super_admin`).
 * The role is resolved through `findAdmin()` because the session record stores
 * the human label, while `roleCan()` compares enum values.
 */
const role = computed(() => findAdmin(currentAdmin.value?.id)?.role ?? 'super_admin');

const canManageCatalog = computed(() => roleCan(role.value, 'catalog.manage'));
const canManageInventory = computed(() => roleCan(role.value, 'inventory.manage'));

/* ------------------------------------------------------------------ */
/* Filters — the query string the Blade form submitted                 */
/* ------------------------------------------------------------------ */

/** The `type` select, which is really a list switcher rather than a filter. */
const TYPE_OPTIONS = {
    all: 'Services & Items',
    service: 'Services only',
    item: 'Items only',
};

const type = computed(() => {
    const requested = String(route.query.type ?? 'all');

    return requested in TYPE_OPTIONS ? requested : 'all';
});

const search = computed(() => String(route.query.search ?? ''));
const category = computed(() => String(route.query.category ?? ''));

const hasFilters = computed(() => Boolean(search.value || category.value || type.value !== 'all'));

/**
 * `withQueryString()` — the filter state lives in the URL, so a bookmarked link
 * reproduces the same view. Empty values are dropped rather than written back
 * as `?search=`, which is what `array_filter($filters)` did in the view.
 */
function updateQuery(patch) {
    const merged = { search: search.value, category: category.value, type: type.value, page: '', ...patch };
    const query = {};

    Object.entries(merged).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) return;

        // "all" is the controller's own default, so it never needs to travel.
        if (key === 'type' && value === 'all') return;

        query[key] = String(value);
    });

    router.replace({ name: 'admin.catalog.index', query });
}

function searchNow() {
    updateQuery({ search: term.value, category: categoryTerm.value });
}

function clearFilters() {
    term.value = '';

    router.replace({ name: 'admin.catalog.index', query: {} });
}

/* ------------------------------------------------------------------ */
/* The two lists                                                      */
/* ------------------------------------------------------------------ */

/** `Service::scopeSearch()` + `scopeCategory()` + `->orderBy('name')`. */
const matchedServices = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return services
        .filter((service) => {
            if (type.value === 'item') return false;

            if (category.value && service.category !== category.value) return false;

            if (!needle) return true;

            return [service.name, service.description, service.category].some((field) =>
                String(field ?? '').toLowerCase().includes(needle),
            );
        })
        .sort((a, b) => a.name.localeCompare(b.name));
});

/** `InventoryItem::scopeSearch()` + `scopeCategory()` + `->orderBy('name')`. */
const matchedItems = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return inventoryItems
        .filter((item) => {
            if (type.value === 'service') return false;

            if (category.value && item.category !== category.value) return false;

            if (!needle) return true;

            return [item.name, item.sku, item.supplier, item.category].some((field) =>
                String(field ?? '').toLowerCase().includes(needle),
            );
        })
        .sort((a, b) => a.name.localeCompare(b.name));
});

/* ------------------------------------------------------------------ */
/* Pagination — `->paginate(20)` on both lists, sharing `?page=`        */
/* ------------------------------------------------------------------ */

const page = computed(() => Math.max(1, Number(route.query.page) || 1));

const servicePageCount = computed(() => Math.max(1, Math.ceil(matchedServices.value.length / PAGE_SIZE)));
const itemPageCount = computed(() => Math.max(1, Math.ceil(matchedItems.value.length / PAGE_SIZE)));

/** A page beyond the end of a shrunken result set would render nothing at all. */
const servicePage = computed(() => Math.min(page.value, servicePageCount.value));
const itemPage = computed(() => Math.min(page.value, itemPageCount.value));

const serviceRows = computed(() =>
    matchedServices.value.slice((servicePage.value - 1) * PAGE_SIZE, servicePage.value * PAGE_SIZE),
);

const itemRows = computed(() =>
    matchedItems.value.slice((itemPage.value - 1) * PAGE_SIZE, itemPage.value * PAGE_SIZE),
);

function goToServicePage(target) {
    if (target < 1 || target > servicePageCount.value) return;

    updateQuery({ page: target === 1 ? '' : String(target) });
}

function goToItemPage(target) {
    if (target < 1 || target > itemPageCount.value) return;

    updateQuery({ page: target === 1 ? '' : String(target) });
}

/* ------------------------------------------------------------------ */
/* The two free-text boxes are debounced so typing does not spam the   */
/* history stack.                                                     */
/* ------------------------------------------------------------------ */

const term = ref(search.value);
const categoryTerm = ref(category.value);

watch(search, (value) => {
    if (value !== term.value) term.value = value;
});

watch(category, (value) => {
    if (value !== categoryTerm.value) categoryTerm.value = value;
});

let searchTimer = null;
let categoryTimer = null;

watch(term, (value) => {
    if (value === search.value) return;

    window.clearTimeout(searchTimer);

    searchTimer = window.setTimeout(() => updateQuery({ search: value }), 300);
});

watch(categoryTerm, (value) => {
    if (value === category.value) return;

    window.clearTimeout(categoryTimer);

    categoryTimer = window.setTimeout(() => updateQuery({ category: value }), 300);
});

onBeforeUnmount(() => {
    window.clearTimeout(searchTimer);
    window.clearTimeout(categoryTimer);
});

/* ------------------------------------------------------------------ */
/* Rows                                                               */
/* ------------------------------------------------------------------ */

function itemTagMeta(item) {
    return ItemTag[item.status_tag] ?? ItemTag.Available;
}

const variantLabel = (service) => plural(service.variants.length, 'variant');
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Catalogue Overview"
            title="Services &amp; Items"
            description="One combined view of everything on your menu and in your stockroom."
        >
            <template #actions>
                <router-link v-if="canManageCatalog" :to="{ name: 'admin.services.create' }" class="btn-secondary btn-sm">
                    Add Service
                </router-link>
                <router-link v-if="canManageInventory" :to="{ name: 'admin.inventory.create' }" class="btn-primary btn-sm">
                    Add Item
                </router-link>
            </template>
        </PageHeader>

        <form class="bta-card mb-6 p-5" @submit.prevent="searchNow">
            <div class="grid gap-4 md:grid-cols-12">
                <div class="md:col-span-5">
                    <FormInput
                        :model-value="term"
                        name="search"
                        label="Search"
                        placeholder="Search services and items at once"
                        @update:model-value="term = $event"
                    />
                </div>
                <div class="md:col-span-3">
                    <FormSelect
                        :model-value="type"
                        name="type"
                        label="Show"
                        :options="TYPE_OPTIONS"
                        @update:model-value="updateQuery({ type: $event })"
                    />
                </div>
                <div class="md:col-span-2">
                    <FormInput
                        :model-value="categoryTerm"
                        name="category"
                        label="Category"
                        placeholder="Exact match"
                        @update:model-value="categoryTerm = $event"
                    />
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="btn-primary flex-1">Search</button>
                    <router-link v-if="hasFilters" :to="{ name: 'admin.catalog.index' }" class="btn-ghost">Clear</router-link>
                </div>
            </div>
        </form>

        <div class="grid gap-6 2xl:grid-cols-2">
            <!-- Services -->
            <CardPanel title="Services" :subtitle="`${matchedServices.length} matching`">
                <template #actions>
                    <router-link
                        :to="{ name: 'admin.services.index' }"
                        class="text-xs font-medium text-primary underline underline-offset-2"
                    >Manage</router-link>
                </template>

                <p v-if="matchedServices.length === 0" class="text-sm text-ink-muted">No services match.</p>

                <ul v-else class="divide-y divide-primary/8">
                    <li
                        v-for="service in serviceRows"
                        :key="service.id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-primary">{{ service.name }}</p>
                            <p class="truncate text-xs text-ink-muted">
                                {{ service.category }} &middot; {{ durationLabel(service.duration_minutes) }}
                                <template v-if="service.variants.length > 0">
                                    &middot; {{ variantLabel(service) }}
                                </template>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="text-sm font-semibold text-primary">{{ formatMoney(service.price) }}</span>
                            <router-link
                                v-if="canManageCatalog"
                                :to="{ name: 'admin.catalog.edit', params: { type: 'service', id: service.id } }"
                                class="btn-ghost btn-sm"
                            >Edit</router-link>
                        </div>
                    </li>
                </ul>

                <div v-if="matchedServices.length > 0" class="mt-4 flex items-center justify-between gap-3">
                    <p class="text-sm text-ink-muted">
                        {{ (servicePage - 1) * PAGE_SIZE + 1 }}–{{ Math.min(servicePage * PAGE_SIZE, matchedServices.length) }}
                        of {{ matchedServices.length }}
                    </p>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="btn-ghost btn-sm"
                            :disabled="servicePage <= 1"
                            @click="goToServicePage(servicePage - 1)"
                        >Previous</button>
                        <span class="text-sm text-ink-muted">Page {{ servicePage }} of {{ servicePageCount }}</span>
                        <button
                            type="button"
                            class="btn-ghost btn-sm"
                            :disabled="servicePage >= servicePageCount"
                            @click="goToServicePage(servicePage + 1)"
                        >Next</button>
                    </div>
                </div>
            </CardPanel>

            <!-- Items -->
            <CardPanel title="Inventory Items" :subtitle="`${matchedItems.length} matching`">
                <template #actions>
                    <router-link
                        :to="{ name: 'admin.inventory.index' }"
                        class="text-xs font-medium text-primary underline underline-offset-2"
                    >Manage</router-link>
                </template>

                <p v-if="matchedItems.length === 0" class="text-sm text-ink-muted">No items match.</p>

                <ul v-else class="divide-y divide-primary/8">
                    <li
                        v-for="item in itemRows"
                        :key="item.id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-primary">{{ item.name }}</p>
                            <p class="truncate text-xs text-ink-muted">
                                {{ item.category }} &middot; reorder at {{ item.reorder_threshold }} {{ item.unit }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Badge :status="itemTagMeta(item).badge" :label="itemTagMeta(item).label" />
                            <router-link
                                v-if="canManageInventory"
                                :to="{ name: 'admin.catalog.edit', params: { type: 'item', id: item.id } }"
                                class="btn-ghost btn-sm"
                            >Edit</router-link>
                        </div>
                    </li>
                </ul>

                <div v-if="matchedItems.length > 0" class="mt-4 flex items-center justify-between gap-3">
                    <p class="text-sm text-ink-muted">
                        {{ (itemPage - 1) * PAGE_SIZE + 1 }}–{{ Math.min(itemPage * PAGE_SIZE, matchedItems.length) }}
                        of {{ matchedItems.length }}
                    </p>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="btn-ghost btn-sm"
                            :disabled="itemPage <= 1"
                            @click="goToItemPage(itemPage - 1)"
                        >Previous</button>
                        <span class="text-sm text-ink-muted">Page {{ itemPage }} of {{ itemPageCount }}</span>
                        <button
                            type="button"
                            class="btn-ghost btn-sm"
                            :disabled="itemPage >= itemPageCount"
                            @click="goToItemPage(itemPage + 1)"
                        >Next</button>
                    </div>
                </div>
            </CardPanel>
        </div>
    </div>
</template>
