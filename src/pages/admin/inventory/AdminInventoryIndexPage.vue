<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { findAdmin } from '@/data/admins';
import { ASSIGNABLE_TAGS, ItemTag, roleCan } from '@/data/enums';
import { inventoryItems, SERVICE_INVENTORY_USAGE } from '@/data/inventory';
import { formatNumber, plural } from '@/lib/format';
import { currentAdmin, setFlash } from '@/lib/session';

/**
 * Admin Flow 6 — Inventory / Item Management (list).
 *
 * Ported from `admin/inventory/index.blade.php` and
 * `Admin\InventoryController::index()`. The three stat cards came from separate
 * counts on the unfiltered table, the filters were a `?search=&category=&tag=`
 * query string, and `withCount('services')` became a count over the usage map
 * the demo data keeps in place of the `service_inventory` pivot.
 */

const route = useRoute();
const router = useRouter();

/** Rows per page. The controller used `paginate(15)`. */
const PAGE_SIZE = 10;

/**
 * The signed-in demo admin is Maia Arjud (`admins` id 1, role `super_admin`).
 * Resolved through `findAdmin()` because the session record stores the human
 * label while `roleCan()` compares enum values.
 */
const role = computed(() => findAdmin(currentAdmin.value?.id)?.role ?? 'super_admin');

const canView = computed(() => roleCan(role.value, 'inventory.view'));
const canManage = computed(() => roleCan(role.value, 'inventory.manage'));

/* ------------------------------------------------------------------ */
/* Model accessors — `InventoryItem`                                  */
/* ------------------------------------------------------------------ */

/** `isLowOnStock()` — `quantity <= reorder_threshold`. */
function isLowOnStock(item) {
    return Number(item.quantity) <= Number(item.reorder_threshold);
}

/** `isSoldOut()` — the manual tag, or a genuinely empty shelf. */
function isSoldOut(item) {
    return item.status_tag === ItemTag.SoldOut.value || Number(item.quantity) <= 0;
}

/**
 * `stock_label` — `number_format($quantity, 2)` with the trailing zeroes and the
 * point trimmed off, then the unit.
 *
 * Trimming is done on the fractional half only: a raw `rtrim('0')` on a
 * thousands-separated number would eat the separators ("1,200.00" -> "1,2").
 */
function stockLabel(item) {
    const [whole, fraction] = formatNumber(item.quantity, 2).split('.');
    const trimmed = (fraction ?? '').replace(/0+$/, '');

    return `${whole}${trimmed ? `.${trimmed}` : ''} ${item.unit}`;
}

/**
 * `suggestedTag()` — the tag implied purely by the stock level, never consulting
 * `status_tag`, which is what makes a manual override detectable.
 */
function suggestedTag(item) {
    const quantity = Number(item.quantity);

    if (quantity <= 0) return ItemTag.SoldOut;
    if (quantity <= Number(item.reorder_threshold)) return ItemTag.LowStock;

    return item.status_tag === ItemTag.BestSeller.value ? ItemTag.BestSeller : ItemTag.Available;
}

/** `hasManualOverride()` */
function hasManualOverride(item) {
    return item.status_tag !== suggestedTag(item).value;
}

/** `withCount('services')` — the usage map is the demo's stand-in for the pivot. */
function servicesCount(item) {
    if (Array.isArray(item.service_ids)) return item.service_ids.length;

    return Object.values(SERVICE_INVENTORY_USAGE).filter((usage) => item.sku in usage).length;
}

function tagMeta(item) {
    return ItemTag[item.status_tag] ?? ItemTag.Available;
}

/* ------------------------------------------------------------------ */
/* Stat cards — unfiltered counts, exactly as the controller passed them */
/* ------------------------------------------------------------------ */

const lowStockCount = computed(() => inventoryItems.filter((item) => isLowOnStock(item)).length);
const soldOutCount = computed(() => inventoryItems.filter((item) => item.status_tag === ItemTag.SoldOut.value).length);

/* ------------------------------------------------------------------ */
/* Filters — `scopeSearch()` + `scopeCategory()` + `scopeTagged()`     */
/* ------------------------------------------------------------------ */

/** `ItemTag::assignableOptions()` */
const tagOptions = computed(() => Object.fromEntries(ASSIGNABLE_TAGS.map((tag) => [tag.value, tag.label])));

/** `InventoryItem::query()->distinct()->orderBy('category')->pluck('category')` */
const categories = computed(() => Object.fromEntries([...new Set(inventoryItems.map((item) => item.category))].sort().map((value) => [value, value])));

const search = computed(() => String(route.query.search ?? ''));
const category = computed(() => String(route.query.category ?? ''));
const tag = computed(() => {
    const requested = String(route.query.tag ?? '');

    // `in_array($request->input('tag'), ItemTag::values(), true) ? … : null`
    return Object.keys(tagOptions.value).includes(requested) ? requested : '';
});

const hasFilters = computed(() => Boolean(search.value || category.value || tag.value));

/**
 * `withQueryString()` — the filter state lives in the URL, so a bookmarked link
 * reproduces the same view. Empty values are dropped rather than written back
 * as `?search=`, which is what `array_filter($filters)` did in the view.
 */
function updateQuery(patch) {
    const merged = { search: search.value, category: category.value, tag: tag.value, page: '', ...patch };
    const query = {};

    Object.entries(merged).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) return;

        query[key] = String(value);
    });

    router.replace({ name: 'admin.inventory.index', query });
}

function filter() {
    updateQuery({ search: term.value, category: categoryTerm.value, tag: tagTerm.value });
}

function clearFilters() {
    term.value = '';
    categoryTerm.value = '';
    tagTerm.value = '';

    router.replace({ name: 'admin.inventory.index', query: {} });
}

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return inventoryItems
        .filter((item) => {
            if (category.value && item.category !== category.value) return false;
            if (tag.value && item.status_tag !== tag.value) return false;

            if (!needle) return true;

            return [item.name, item.sku, item.supplier, item.category].some((field) =>
                String(field ?? '').toLowerCase().includes(needle),
            );
        })
        // `->orderBy('name')`
        .sort((a, b) => a.name.localeCompare(b.name));
});

/* ------------------------------------------------------------------ */
/* Pagination — `->paginate(15)`                                      */
/* ------------------------------------------------------------------ */

const page = computed(() => Math.max(1, Number(route.query.page) || 1));
const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)));
const currentPage = computed(() => Math.min(page.value, pageCount.value));
const rows = computed(() => filtered.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE));

function goToPage(target) {
    if (target < 1 || target > pageCount.value) return;

    updateQuery({ page: target === 1 ? '' : String(target) });
}

/* ------------------------------------------------------------------ */
/* The two free-text boxes are debounced so typing does not spam the   */
/* history stack; the selects commit immediately, as they did in Blade.  */
/* ------------------------------------------------------------------ */

const term = ref(search.value);
const categoryTerm = ref(category.value);
const tagTerm = ref(tag.value);

watch(search, (value) => {
    if (value !== term.value) term.value = value;
});

watch(category, (value) => {
    if (value !== categoryTerm.value) categoryTerm.value = value;
});

watch(tag, (value) => {
    if (value !== tagTerm.value) tagTerm.value = value;
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
/* Delete — `InventoryController::destroy()`                          */
/* ------------------------------------------------------------------ */

function destroy(item) {
    if (!window.confirm(`Delete “${item.name}”?`)) return;

    const index = inventoryItems.findIndex((row) => row.id === item.id);

    if (index === -1) return;

    inventoryItems.splice(index, 1);

    setFlash(`Item "${item.name}" deleted.`);
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Stock"
            title="Inventory Items"
            description="Track stock levels, reorder thresholds and which services consume each item."
        >
            <template #actions>
                <router-link :to="{ name: 'admin.tags.index' }" class="btn-gold btn-sm">
                    <span v-if="lowStockCount > 0" class="rounded-pill bg-primary px-1.5 py-0.5 text-[10px] text-cream">
                        {{ lowStockCount }}
                    </span>
                    Low-Stock Tags
                </router-link>
                <router-link v-if="canManage" :to="{ name: 'admin.inventory.create' }" class="btn-primary btn-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Add Item
                </router-link>
            </template>
        </PageHeader>

        <template v-if="canView">
            <!-- Stat cards -->
            <div class="mb-5 grid gap-4 sm:grid-cols-3">
                <div class="bta-card flex items-center gap-4 p-4">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-status-low-stock-bg text-status-low-stock">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126Z"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Low Stock</p>
                        <p class="font-display text-2xl font-bold text-primary">{{ lowStockCount }}</p>
                    </div>
                </div>

                <div class="bta-card flex items-center gap-4 p-4">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-status-sold-out-bg text-status-sold-out">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m18.36 5.64-12.72 12.72M6.34 5.64l12.72 12.72"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Sold Out</p>
                        <p class="font-display text-2xl font-bold text-primary">{{ soldOutCount }}</p>
                    </div>
                </div>

                <div class="bta-card flex items-center gap-4 p-4">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Items</p>
                        <p class="font-display text-2xl font-bold text-primary">{{ inventoryItems.length }}</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <form class="bta-card mb-6 p-5" @submit.prevent="filter">
                <div class="grid gap-4 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <FormInput
                            :model-value="term"
                            name="search"
                            label="Search"
                            placeholder="Name, SKU or supplier"
                            @update:model-value="term = $event"
                        />
                    </div>
                    <div class="md:col-span-3">
                        <FormSelect
                            :model-value="category"
                            name="category"
                            label="Category"
                            include-blank
                            blank-label="All Categories"
                            :options="categories"
                            @update:model-value="updateQuery({ category: $event })"
                        />
                    </div>
                    <div class="md:col-span-3">
                        <FormSelect
                            :model-value="tag"
                            name="tag"
                            label="Status Tag"
                            include-blank
                            blank-label="All Tags"
                            :options="tagOptions"
                            @update:model-value="updateQuery({ tag: $event })"
                        />
                    </div>
                    <div class="flex items-end gap-2 md:col-span-2">
                        <button type="submit" class="btn-primary flex-1">Filter</button>
                        <router-link v-if="hasFilters" :to="{ name: 'admin.inventory.index' }" class="btn-ghost">Clear</router-link>
                    </div>
                </div>
            </form>

            <EmptyState
                v-if="filtered.length === 0"
                title="No items found"
                description="Add your first inventory item to get started."
            >
                <router-link v-if="canManage" :to="{ name: 'admin.inventory.create' }" class="btn-primary">Add Item</router-link>
            </EmptyState>

            <template v-else>
                <div class="bta-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="bta-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th class="text-right">Quantity</th>
                                    <th class="text-right">Reorder At</th>
                                    <th>Supplier</th>
                                    <th>Tag</th>
                                    <th>Linked Services</th>
                                    <th v-if="canManage" class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in rows"
                                    :key="item.id"
                                    :class="isLowOnStock(item) ? 'bg-status-low-stock-bg/25' : ''"
                                >
                                    <td>
                                        <p class="font-medium text-primary">{{ item.name }}</p>
                                        <p class="font-mono text-xs text-ink-muted">{{ item.sku }}</p>
                                    </td>
                                    <td class="whitespace-nowrap"><span class="badge badge-gold">{{ item.category }}</span></td>
                                    <td class="whitespace-nowrap text-right">
                                        <span
                                            class="font-semibold"
                                            :class="isSoldOut(item)
                                                ? 'text-status-sold-out'
                                                : (isLowOnStock(item) ? 'text-status-low-stock' : 'text-primary')"
                                        >{{ stockLabel(item) }}</span>
                                        <span v-if="hasManualOverride(item)" class="block text-[10px] italic text-ink-muted">manual override</span>
                                    </td>
                                    <td class="whitespace-nowrap text-right text-ink">{{ item.reorder_threshold }} {{ item.unit }}</td>
                                    <td class="text-ink">{{ item.supplier || '—' }}</td>
                                    <td><Badge :status="tagMeta(item).badge" :label="tagMeta(item).label" /></td>
                                    <td>
                                        <span v-if="servicesCount(item) > 0" class="text-sm text-ink">
                                            {{ plural(servicesCount(item), 'service') }}
                                        </span>
                                        <span v-else class="text-sm text-ink-muted">—</span>
                                    </td>
                                    <td v-if="canManage">
                                        <div class="flex justify-end gap-1.5">
                                            <router-link
                                                :to="{ name: 'admin.inventory.edit', params: { id: item.id } }"
                                                class="btn-secondary btn-sm"
                                            >Edit</router-link>
                                            <button type="button" class="btn-danger btn-sm" @click="destroy(item)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-ink-muted">
                        Showing {{ (currentPage - 1) * PAGE_SIZE + 1 }}–{{ Math.min(currentPage * PAGE_SIZE, filtered.length) }}
                        of {{ plural(filtered.length, 'item') }}
                    </p>

                    <div class="flex items-center gap-2">
                        <button type="button" class="btn-ghost btn-sm" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">
                            Previous
                        </button>
                        <span class="text-sm text-ink-muted">Page {{ currentPage }} of {{ pageCount }}</span>
                        <button type="button" class="btn-ghost btn-sm" :disabled="currentPage >= pageCount" @click="goToPage(currentPage + 1)">
                            Next
                        </button>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>
