<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { findAdmin } from '@/data/admins';
import { ASSIGNABLE_TAGS, ItemTag, roleCan } from '@/data/enums';
import { applyTag, inventoryForService, inventoryItems } from '@/data/inventory';
import { activeServices } from '@/data/services';
import { formatNumber, plural } from '@/lib/format';
import { currentAdmin, setFlash } from '@/lib/session';

/**
 * Admin Flow 7 — Low-Stock / Not-Available tagging.
 *
 * Ported from `admin/tags/index.blade.php` and `Admin\ItemTagController`. The
 * filter pills were a `?filter=` query param, the bulk form was an Alpine
 * `bulkTagger()` component and the "suggested" column was the model's
 * `suggestedTag()` accessor — deliberately computed from the quantity alone so
 * a manual override stays detectable as an override.
 */

const route = useRoute();
const router = useRouter();

/** Rows per page. The controller used `paginate(20)`. */
const PAGE_SIZE = 10;

/**
 * The signed-in demo admin is Maia Arjud (`admins` id 1, role `super_admin`).
 * Resolved through `findAdmin()` because the session record stores the human
 * label while `roleCan()` compares enum values.
 */
const role = computed(() => findAdmin(currentAdmin.value?.id)?.role ?? 'super_admin');

const canManage = computed(() => roleCan(role.value, 'tags.manage'));

/** `ItemTag::assignableOptions()` — the four tags an admin may hand out. */
const tagOptions = computed(() =>
    Object.fromEntries(ASSIGNABLE_TAGS.map((tag) => [tag.value, tag.label])),
);

/* ------------------------------------------------------------------ */
/* Model accessors — `InventoryItem`                                  */
/* ------------------------------------------------------------------ */

/** `isLowOnStock()` — `quantity <= reorder_threshold`. */
function isLowOnStock(item) {
    return Number(item.quantity) <= Number(item.reorder_threshold);
}

/**
 * `suggestedTag()` — the tag implied purely by the stock level.
 *
 * It never consults `status_tag`, otherwise a manual "Sold Out" tag would make
 * itself its own suggestion and an override could never be spotted.
 */
function suggestedTag(item) {
    const quantity = Number(item.quantity);

    if (quantity <= 0) return ItemTag.SoldOut;

    if (quantity <= Number(item.reorder_threshold)) return ItemTag.LowStock;

    return item.status_tag === ItemTag.BestSeller.value ? ItemTag.BestSeller : ItemTag.Available;
}

/** `hasManualOverride()` — the stored tag no longer matches the suggestion. */
function hasManualOverride(item) {
    return item.status_tag !== suggestedTag(item).value;
}

/**
 * `stock_label` — `number_format($quantity, 2)` with the trailing zeroes and
 * the point trimmed off, then the unit.
 *
 * Trimming is done on the fractional half only: a raw `rtrim('0')` on a
 * thousands-separated number would eat the separators ("1,200.00" -> "1,2").
 */
function stockLabel(item) {
    const [whole, fraction] = formatNumber(item.quantity, 2).split('.');
    const trimmed = (fraction ?? '').replace(/0+$/, '');

    return `${whole}${trimmed ? `.${trimmed}` : ''} ${item.unit}`;
}

/* ------------------------------------------------------------------ */
/* Filters — the `?filter=` pills                                      */
/* ------------------------------------------------------------------ */

const FILTERS = [
    { value: 'attention', label: 'Needs Attention' },
    { value: 'low_stock', label: 'Low Stock' },
    { value: 'sold_out', label: 'Sold Out' },
    { value: 'best_seller', label: 'Best Seller' },
    { value: 'override', label: 'Manually Overridden' },
    { value: 'all', label: 'All Items' },
];

const activeFilter = computed(() => {
    const requested = String(route.query.filter ?? 'attention');

    return FILTERS.some((row) => row.value === requested) ? requested : 'attention';
});

const filtered = computed(() => {
    let rows;

    switch (activeFilter.value) {
        case 'low_stock':
            rows = inventoryItems.filter((item) => isLowOnStock(item));
            break;

        case 'sold_out':
            rows = inventoryItems.filter((item) => item.status_tag === ItemTag.SoldOut.value);
            break;

        case 'best_seller':
            rows = inventoryItems.filter((item) => item.status_tag === ItemTag.BestSeller.value);
            break;

        // `whereNotNull('status_tag')` — the column is a cast enum, so in the
        // demo every item carries one and this is the full list.
        case 'override':
            rows = inventoryItems.filter((item) => item.status_tag != null);
            break;

        // Everything that needs a decision: low stock, sold out or best seller.
        case 'attention':
            rows = inventoryItems.filter(
                (item) =>
                    isLowOnStock(item)
                    || item.status_tag === ItemTag.SoldOut.value
                    || item.status_tag === ItemTag.BestSeller.value,
            );
            break;

        default:
            rows = [...inventoryItems];
            break;
    }

    // `->orderBy('quantity')`
    return rows.sort((a, b) => Number(a.quantity) - Number(b.quantity));
});

/* ------------------------------------------------------------------ */
/* Pagination — `->paginate(20)`                                      */
/* ------------------------------------------------------------------ */

const page = computed(() => Math.max(1, Number(route.query.page) || 1));
const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)));
const currentPage = computed(() => Math.min(page.value, pageCount.value));
const rows = computed(() => filtered.value.slice((currentPage.value - 1) * PAGE_SIZE, currentPage.value * PAGE_SIZE));

function goToPage(target) {
    if (target < 1 || target > pageCount.value) return;

    router.replace({
        name: 'admin.tags.index',
        query: { filter: activeFilter.value, page: target === 1 ? undefined : String(target) },
    });
}

/* ------------------------------------------------------------------ */
/* Selection — the Alpine `bulkTagger()` state                        */
/* ------------------------------------------------------------------ */

/** Ids, not the string values a real checkbox would post. */
const selected = ref([]);
const bulkTag = ref('');
const bulkError = ref('');

const all = computed({
    get: () => rows.value.length > 0 && rows.value.every((item) => selected.value.includes(item.id)),
    set: (value) => {
        selected.value = value ? rows.value.map((item) => item.id) : [];
    },
});

/** `bulkUpdate()` — the validation the request ran before the mass update. */
function applyBulkTag() {
    bulkError.value = '';

    if (!bulkTag.value) {
        bulkError.value = 'Please choose a tag to apply.';

        return;
    }

    if (!Object.keys(tagOptions.value).includes(bulkTag.value)) {
        bulkError.value = 'The selected status tag is invalid.';

        return;
    }

    const targets = inventoryItems.filter((item) => selected.value.includes(item.id));

    if (targets.length === 0) {
        bulkError.value = 'Please choose at least one item.';

        return;
    }

    applyTag(targets, bulkTag.value);

    setFlash(`${targets.length} item(s) tagged as ${ItemTag[bulkTag.value].label}.`);

    selected.value = [];
    bulkTag.value = '';
}

/** `update()` — the inline per-row select. */
function changeTag(item, value) {
    if (!Object.keys(tagOptions.value).includes(value)) return;

    applyTag([item], value);

    setFlash(`"${item.name}" tagged as ${ItemTag[value].label}.`);
}

/* ------------------------------------------------------------------ */
/* Per-service impact — `ItemTagController::buildSuggestions()`        */
/* ------------------------------------------------------------------ */

const suggestions = computed(() =>
    [...activeServices.value]
        .sort((a, b) => a.name.localeCompare(b.name))
        .map((service) => ({
            service: service.name,
            items: inventoryForService(service.name)
                .filter((row) => row.item.status_tag === ItemTag.LowStock.value || row.item.status_tag === ItemTag.SoldOut.value)
                .map((row) => ({ name: row.item.name, status: row.item.status_tag })),
        }))
        .filter((row) => row.items.length > 0),
);
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Stock Status"
            title="Low-Stock &amp; Availability Tags"
            description="Tag items as Low Stock, Sold Out or Best Seller. Low Stock is suggested automatically when quantity drops to the reorder threshold."
        >
            <template #actions>
                <router-link :to="{ name: 'admin.inventory.index' }" class="btn-secondary btn-sm">
                    Manage Items
                </router-link>
            </template>
        </PageHeader>

        <Alert type="info" class="mb-6">
            The suggestion below compares each item's quantity against its reorder threshold. Choosing a
            tag explicitly overrides that — useful when an item is temporarily unavailable for another reason.
        </Alert>

        <!-- Filter pills -->
        <div class="mb-6 flex flex-wrap gap-2">
            <router-link
                v-for="filter in FILTERS"
                :key="filter.value"
                :to="{ name: 'admin.tags.index', query: { filter: filter.value } }"
                class="rounded-pill px-3.5 py-1.5 text-sm font-medium transition"
                :class="activeFilter === filter.value ? 'bg-primary text-cream' : 'bg-cream text-ink hover:bg-linen'"
            >
                {{ filter.label }}
            </router-link>
        </div>

        <div class="grid gap-6 2xl:grid-cols-3">
            <div class="2xl:col-span-2">
                <CardPanel title="Items" subtitle="Select rows to tag several at once, or tag an item inline.">
                    <template #actions>
                        <div v-if="canManage && selected.length > 0" class="flex items-end gap-2">
                            <select
                                v-model="bulkTag"
                                name="status_tag"
                                class="input w-40 py-1.5 text-xs"
                            >
                                <option value="">Choose tag…</option>
                                <option v-for="(label, value) in tagOptions" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                            <button
                                type="button"
                                class="btn-primary btn-sm"
                                :disabled="bulkTag === ''"
                                @click="applyBulkTag"
                            >Apply to {{ selected.length }}</button>
                        </div>
                    </template>

                    <EmptyState
                        v-if="filtered.length === 0"
                        title="No items in this view"
                        description="Try a different filter."
                    />

                    <template v-else>
                        <div class="overflow-x-auto">
                            <table class="bta-table">
                                <thead>
                                    <tr>
                                        <th v-if="canManage" class="w-10">
                                            <input
                                                v-model="all"
                                                type="checkbox"
                                                class="checkbox"
                                                aria-label="Select all"
                                            >
                                        </th>
                                        <th>Item</th>
                                        <th class="text-right">Quantity</th>
                                        <th class="text-right">Reorder At</th>
                                        <th>Suggested</th>
                                        <th>Current Tag</th>
                                        <th v-if="canManage">Change To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in rows"
                                        :key="item.id"
                                        :class="isLowOnStock(item) ? 'bg-status-low-stock-bg/25' : ''"
                                    >
                                        <td v-if="canManage">
                                            <input
                                                v-model="selected"
                                                type="checkbox"
                                                class="checkbox"
                                                :value="item.id"
                                                :aria-label="`Select ${item.name}`"
                                            >
                                        </td>
                                        <td>
                                            <p class="font-medium text-primary">{{ item.name }}</p>
                                            <p class="font-mono text-xs text-ink-muted">{{ item.sku }}</p>
                                        </td>
                                        <td class="whitespace-nowrap text-right font-medium text-primary">
                                            {{ stockLabel(item) }}
                                        </td>
                                        <td class="whitespace-nowrap text-right text-ink">
                                            {{ item.reorder_threshold }} {{ item.unit }}
                                        </td>
                                        <td>
                                            <Badge :status="suggestedTag(item).badge" :label="suggestedTag(item).label" />
                                        </td>
                                        <td>
                                            <Badge
                                                :status="ItemTag[item.status_tag]?.badge"
                                                :label="ItemTag[item.status_tag]?.label"
                                            />
                                            <span v-if="hasManualOverride(item)" class="mt-1 block text-[10px] italic text-ink-muted">override</span>
                                        </td>
                                        <td v-if="canManage">
                                            <select
                                                :value="item.status_tag"
                                                class="input w-36 py-1.5 text-xs"
                                                :aria-label="`Change tag for ${item.name}`"
                                                @change="changeTag(item, $event.target.value)"
                                            >
                                                <option
                                                    v-for="(label, value) in tagOptions"
                                                    :key="value"
                                                    :value="value"
                                                    :selected="item.status_tag === value"
                                                >{{ label }}</option>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
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

                        <p v-if="bulkError" class="input-error-text mt-3">{{ bulkError }}</p>
                    </template>
                </CardPanel>
            </div>

            <!-- Per-service impact -->
            <aside class="space-y-6">
                <CardPanel title="Service Impact" subtitle="Services that depend on a low-stock or sold-out item.">
                    <p v-if="suggestions.length === 0" class="text-sm text-ink-muted">
                        Nothing critical right now — no service depends on a flagged item.
                    </p>

                    <ul v-else class="space-y-3">
                        <li
                            v-for="row in suggestions"
                            :key="row.service"
                            class="rounded-xl border border-status-low-stock/25 bg-status-low-stock-bg/30 p-3.5"
                        >
                            <p class="text-sm font-medium text-primary">{{ row.service }}</p>
                            <ul class="mt-1.5 space-y-1">
                                <li
                                    v-for="linked in row.items"
                                    :key="linked.name"
                                    class="flex items-center justify-between gap-2 text-xs"
                                >
                                    <span class="text-ink">{{ linked.name }}</span>
                                    <Badge :status="linked.status" />
                                </li>
                            </ul>
                        </li>
                    </ul>
                </CardPanel>

                <CardPanel title="How the suggestion works">
                    <ul class="space-y-2.5 text-sm text-ink-muted">
                        <li class="flex gap-2.5">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold" />
                            <span><span class="font-medium text-primary">quantity &le; 0</span> &rarr; Sold Out</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold" />
                            <span><span class="font-medium text-primary">quantity &le; reorder threshold</span> &rarr; Low Stock</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold" />
                            <span>otherwise &rarr; Available</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold" />
                            <span>A <span class="font-medium text-primary">Best Seller</span> tag survives quantity changes until the item runs out.</span>
                        </li>
                    </ul>
                </CardPanel>
            </aside>
        </div>
    </div>
</template>
