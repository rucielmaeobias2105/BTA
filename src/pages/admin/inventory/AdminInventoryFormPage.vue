<script setup>
import { computed, provide } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import FormTextarea from '@/components/ui/form/FormTextarea.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import { findAdmin } from '@/data/admins';
import { ASSIGNABLE_TAGS, ItemTag, ITEM_TAG_VALUES, roleCan } from '@/data/enums';
import { inventoryItems, SERVICE_INVENTORY_USAGE } from '@/data/inventory';
import { activeServices } from '@/data/services';
import { currentAdmin, setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';

/**
 * Admin Flow 6 — Inventory create / edit.
 *
 * Ported from `admin/inventory/_form.blade.php` (both `create` and `edit` merely
 * included it) plus `Admin\InventoryItemRequest` and
 * `InventoryController::store()` / `update()` / `syncServices()` /
 * `resolveTag()`. The same component serves both routes; the presence of
 * `route.params.id` is what `$item->exists` used to decide.
 *
 * The linked-service picker and the live "suggested tag" hint were an Alpine
 * `itemForm()` component; they are plain reactive state here, and the advice is
 * still advisory only — `resolveTag()` re-derives the answer on save.
 */

const route = useRoute();
const router = useRouter();

/** `$item->exists` — the create route has no id, the edit route always does. */
const item = computed(() => (route.params.id ? inventoryItems.find((row) => String(row.id) === String(route.params.id)) ?? null : null));
const editing = computed(() => Boolean(item.value));

/**
 * The signed-in demo admin is Maia Arjud (`admins` id 1, role `super_admin`).
 * Resolved through `findAdmin()` because the session record stores the human
 * label while `roleCan()` compares enum values.
 */
const role = computed(() => findAdmin(currentAdmin.value?.id)?.role ?? 'super_admin');

const canManage = computed(() => roleCan(role.value, 'inventory.manage'));

/** `InventoryItem::UNITS` */
const UNITS = ['pcs', 'ml', 'bottles', 'boxes', 'sachets', 'grams', 'sets', 'kits'];

/** `InventoryItem::CATEGORIES`, offered alongside whatever already exists. */
const SEED_CATEGORIES = [
    'Hair Care', 'Styling', 'Nail Care', 'Lash & Brow', 'Skincare',
    'Massage & Spa', 'Disinfectants', 'Consumables', 'Retail Products',
];

const categories = computed(() => [
    ...new Set([...inventoryItems.map((row) => row.category), ...SEED_CATEGORIES]),
]);

/** `ItemTag::assignableOptions()` */
const tagOptions = Object.fromEntries(ASSIGNABLE_TAGS.map((tag) => [tag.value, tag.label]));

/** `Service::active()->orderBy('name')` */
const allServices = computed(() => [...activeServices.value].sort((a, b) => a.name.localeCompare(b.name)));

/* ------------------------------------------------------------------ */
/* Linked services — the `service_inventory` pivot, in demo data form  */
/* ------------------------------------------------------------------ */

/**
 * The seeded `service_inventory` rows for one SKU: service id -> quantity used
 * per booking. Once an item is saved the chosen links are written onto the
 * record itself, so an edit round-trips the admin's own answer.
 */
function usageFor(sku) {
    const map = {};

    Object.entries(SERVICE_INVENTORY_USAGE).forEach(([serviceName, usage]) => {
        if (!(sku in usage)) return;

        const service = allServices.value.find((row) => row.name === serviceName);

        if (service) map[service.id] = usage[sku];
    });

    return map;
}

const seeded = computed(() => (item.value ? usageFor(item.value.sku) : {}));

/** The links the edit form opens with: the admin's saved choice, else the seed. */
const initialServiceIds = item.value?.service_ids
    ? [...item.value.service_ids]
    : Object.keys(seeded.value).map(Number);

/* ------------------------------------------------------------------ */
/* Form                                                               */
/* ------------------------------------------------------------------ */

const form = createForm({
    initial: {
        name: item.value?.name ?? '',
        // `prepareForValidation()` upper-cased and trimmed the SKU.
        sku: item.value?.sku ?? '',
        category: item.value?.category ?? '',
        quantity: item.value?.quantity ?? 0,
        // `old('unit', $item->unit ?? 'pcs')`
        unit: item.value?.unit ?? 'pcs',
        reorder_threshold: item.value?.reorder_threshold ?? 0,
        supplier: item.value?.supplier ?? '',
        status_tag: item.value?.status_tag ?? '',
        notes: item.value?.notes ?? '',
        is_active: item.value ? item.value.is_active : true,
        services: initialServiceIds,
        quantities: { ...seeded.value, ...(item.value?.quantities ?? {}) },
    },
    rules: {
        name: ['required', 'string', 'max:150'],
        sku: ['required', 'string', 'max:64'],
        category: ['required', 'string', 'max:80'],
        quantity: ['required'],
        // `Rule::in(InventoryItem::UNITS)` carries the request's own message,
        // which cannot be keyed through the declarative `in` rule, so the
        // membership test lives in `after` below.
        unit: ['required', 'string'],
        reorder_threshold: ['required'],
        supplier: ['nullable', 'string', 'max:150'],
        status_tag: ['nullable', 'string', `in:${ITEM_TAG_VALUES.join(',')}`],
        notes: ['nullable', 'string', 'max:2000'],
        is_active: ['nullable'],
        services: ['nullable', 'array'],
        quantities: ['nullable', 'array'],
    },
    after(errors) {
        // `numeric|min:0|max:9999999`
        if (!errors.quantity) {
            errors.quantity = numberProblem('quantity', 0, 9999999);
        }

        if (!errors.reorder_threshold) {
            errors.reorder_threshold = numberProblem('reorder_threshold', 0, 9999999);
        }

        // `unit.in` — the one custom message the request declared.
        if (!errors.unit && !UNITS.includes(form.values.unit)) {
            errors.unit = 'Please choose a valid unit of measure.';
        }

        // `Rule::unique('inventory_items', 'sku')->ignore($itemId)`
        if (!errors.sku) {
            const clash = inventoryItems.find((row) => row.sku === form.values.sku && row.id !== item.value?.id);

            if (clash) errors.sku = 'The sku has already been taken.';
        }

        // `services.*: integer, Rule::exists('services', 'id')`
        form.values.services.forEach((serviceId, index) => {
            if (!Number.isInteger(Number(serviceId))) {
                errors[`services.${index}`] = `The services.${index} must be an integer.`;

                return;
            }

            if (!allServices.value.some((row) => row.id === Number(serviceId))) {
                errors[`services.${index}`] = `The selected services.${index} is invalid.`;
            }
        });

        // `quantities.*: nullable, numeric, min:0, max:9999`
        form.values.services.forEach((serviceId) => {
            const key = `quantities.${serviceId}`;
            const problem = quantityProblem(serviceId);

            if (problem) errors[key] = problem;
        });

        // `after` must leave a clean bag clean, or the form never passes.
        Object.keys(errors).forEach((key) => {
            if (!errors[key]) delete errors[key];
        });
    },
});

// Lets the form components read their error without every call site threading
// the bag through by hand — the replacement for Blade's global `$errors`.
provide('form-errors', form.errors);

/** `numeric|min:0|max:…`, reported the way Laravel would. */
function numberProblem(field, min, max, label = null) {
    const name = label ?? field.replaceAll('_', ' ');
    const raw = String(form.values[field] ?? '').trim();
    const value = Number(raw);

    if (raw === '' || !Number.isFinite(value)) return `The ${name} field must be a number.`;
    if (value < min) return `The ${name} field must be at least ${min}.`;
    if (value > max) return `The ${name} field may not be greater than ${max}.`;

    return null;
}

/** `quantities.<id>` — the pivot's own numeric bounds, keyed by service id. */
function quantityProblem(serviceId) {
    const key = `quantities.${serviceId}`;
    const raw = String(form.values.quantities[serviceId] ?? '').trim();

    if (raw === '') return null;

    const value = Number(raw);

    if (!Number.isFinite(value)) return `The ${key} must be a number.`;
    if (value < 0) return `The ${key} must be at least 0.`;
    if (value > 9999) return `The ${key} may not be greater than 9999.`;

    return null;
}

/* ------------------------------------------------------------------ */
/* Linked-service picker — `itemForm().selected` / `.quantities`       */
/* ------------------------------------------------------------------ */

function isSelected(serviceId) {
    return form.values.services.includes(serviceId);
}

function toggleService(serviceId, checked) {
    if (checked) {
        if (!isSelected(serviceId)) form.values.services.push(serviceId);

        // A newly linked service falls back to the pivot's default of 1.
        if (form.values.quantities[serviceId] === undefined) form.values.quantities[serviceId] = 1;

        return;
    }

    form.values.services = form.values.services.filter((id) => id !== serviceId);
}

/* ------------------------------------------------------------------ */
/* The advisory "suggested tag" hint — `itemForm().refresh()`         */
/* ------------------------------------------------------------------ */

const suggestion = computed(() => {
    const quantity = Number(form.values.quantity);
    const threshold = Number(form.values.reorder_threshold);

    if (!Number.isFinite(quantity) || !Number.isFinite(threshold)) return '';
    if (quantity <= 0) return 'Sold Out / Unavailable';
    if (quantity <= threshold) return 'Low Stock (quantity is at or below the reorder threshold)';

    return 'Available';
});

/* ------------------------------------------------------------------ */
/* Save                                                               */
/* ------------------------------------------------------------------ */

/** `InventoryItemRequest::prepareForValidation()`. */
function prepare() {
    form.values.name = String(form.values.name ?? '').trim();
    form.values.sku = String(form.values.sku ?? '').trim().toUpperCase();
}

function nextItemId() {
    return Math.max(0, ...inventoryItems.map((row) => row.id)) + 1;
}

/**
 * `InventoryController::resolveTag()` — an explicit choice is a manual override,
 * a Best Seller tag survives quantity changes, and anything else is derived
 * from the quantity against the reorder threshold.
 */
function resolveTag(quantity, threshold, current) {
    if (form.values.status_tag) return form.values.status_tag;

    if (current === ItemTag.BestSeller.value) {
        return quantity <= 0 ? ItemTag.SoldOut.value : ItemTag.BestSeller.value;
    }

    if (quantity <= 0) return ItemTag.SoldOut.value;

    return quantity <= threshold ? ItemTag.LowStock.value : ItemTag.Available.value;
}

/** `syncServices()` — the pivot rows, each floored at 0.01. */
function syncServices(target) {
    const serviceIds = form.values.services.map(Number);

    const links = serviceIds.reduce((carry, serviceId) => {
        carry[serviceId] = { quantity_per_service: Math.max(0.01, Number(form.values.quantities[serviceId]) || 1) };

        return carry;
    }, {});

    target.service_ids = serviceIds;
    target.quantities = links;
}

function submit() {
    if (editing.value && !item.value) return;

    prepare();

    if (!form.validate()) return;

    const quantity = Number(form.values.quantity);
    const threshold = Number(form.values.reorder_threshold);

    const data = {
        name: form.values.name,
        sku: form.values.sku,
        category: form.values.category,
        quantity,
        unit: form.values.unit,
        reorder_threshold: threshold,
        supplier: String(form.values.supplier ?? '').trim() || null,
        status_tag: resolveTag(quantity, threshold, item.value?.status_tag ?? null),
        notes: String(form.values.notes ?? '').trim() || null,
        is_active: Boolean(form.values.is_active),
    };

    if (editing.value) {
        Object.assign(item.value, data);
        syncServices(item.value);

        setFlash(`Item "${data.name}" updated.`);
    } else {
        // Pushed through the array so the new record is the reactive proxy.
        inventoryItems.push({ id: nextItemId(), ...data });
        syncServices(inventoryItems[inventoryItems.length - 1]);

        setFlash(`Item "${data.name}" created.`);
    }

    router.push({ name: 'admin.inventory.index' });
}
</script>

<template>
    <NotFoundPage v-if="editing && !item" />

    <div v-else>
        <router-link
            :to="{ name: 'admin.inventory.index' }"
            class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to inventory
        </router-link>

        <PageHeader
            eyebrow="Stock"
            :title="editing ? 'Edit Inventory Item' : 'Add Inventory Item'"
            :description="editing
                ? `Update stock levels, thresholds and linked services for “${item.name}”.`
                : 'Create a new tracked item and link it to the services that consume it.'"
        />

        <ErrorSummary />

        <form novalidate @submit.prevent="submit">
            <div class="grid gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <CardPanel title="Item Details">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <FormInput
                                v-model="form.values.name"
                                name="name"
                                label="Item Name"
                                required
                                placeholder="e.g. Gelish Top Coat"
                            />
                            <FormInput
                                v-model="form.values.sku"
                                name="sku"
                                label="SKU"
                                required
                                placeholder="INV-NC-003"
                            />
                        </div>

                        <div class="mt-5">
                            <!-- Datalist-backed free text so new categories are allowed -->
                            <div>
                                <label for="category" class="label">
                                    Category
                                    <span class="text-status-cancelled">*</span>
                                </label>

                                <div class="relative">
                                    <input
                                        id="category"
                                        name="category"
                                        type="text"
                                        list="bta-item-categories"
                                        placeholder="e.g. Nail Care"
                                        :value="form.values.category"
                                        required
                                        class="input"
                                        :class="form.errors.category ? 'input-error' : ''"
                                        :aria-invalid="form.errors.category ? 'true' : undefined"
                                        @input="form.values.category = $event.target.value"
                                    >
                                </div>

                                <datalist id="bta-item-categories">
                                    <option v-for="option in categories" :key="option" :value="option" />
                                </datalist>

                                <p v-if="form.errors.category" class="input-error-text">
                                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                    </svg>
                                    {{ form.errors.category }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-5 sm:grid-cols-3">
                            <FormInput
                                v-model="form.values.quantity"
                                name="quantity"
                                type="number"
                                label="Quantity / Stock Level"
                                required
                            />
                            <FormSelect
                                v-model="form.values.unit"
                                name="unit"
                                label="Unit"
                                required
                                :options="Object.fromEntries(UNITS.map((unit) => [unit, unit]))"
                            />
                            <FormInput
                                v-model="form.values.reorder_threshold"
                                name="reorder_threshold"
                                type="number"
                                label="Reorder Threshold"
                                required
                            />
                        </div>

                        <p v-if="suggestion" class="mt-3 text-xs text-ink-muted">
                            <span class="font-medium text-status-low-stock">Suggested tag:</span>
                            <span>{{ suggestion }}</span>
                        </p>

                        <div class="mt-5">
                            <FormInput
                                v-model="form.values.supplier"
                                name="supplier"
                                label="Supplier (optional)"
                                placeholder="e.g. Beauty Depot PH"
                            />
                        </div>

                        <div class="mt-5">
                            <FormTextarea v-model="form.values.notes" name="notes" label="Notes" :rows="3" />
                        </div>
                    </CardPanel>

                    <!-- Linked services (many-to-many) -->
                    <CardPanel title="Linked Services" subtitle="Which services consume this item. Booking them reduces the quantity automatically.">
                        <p v-if="allServices.length === 0" class="text-sm text-ink-muted">No active services yet.</p>

                        <div v-else class="space-y-2">
                            <label
                                v-for="service in allServices"
                                :key="service.id"
                                class="flex flex-wrap items-center gap-3 rounded-xl border border-primary/12 bg-linen/50 px-4 py-3 transition hover:border-gold"
                            >
                                <input
                                    type="checkbox"
                                    class="checkbox"
                                    name="services[]"
                                    :value="service.id"
                                    :checked="isSelected(service.id)"
                                    @change="toggleService(service.id, $event.target.checked)"
                                >

                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-ink">{{ service.name }}</span>
                                    <span class="block text-xs text-ink-muted">{{ service.category }}</span>
                                </span>

                                <span v-if="isSelected(service.id)" class="flex items-center gap-2">
                                    <label class="text-xs text-ink-muted" :for="`qty-${service.id}`">Used per booking</label>
                                    <input
                                        :id="`qty-${service.id}`"
                                        v-model="form.values.quantities[service.id]"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        class="input w-24 py-1.5 text-sm"
                                    >
                                    <span class="text-xs text-ink-muted">{{ item?.unit || 'pcs' }}</span>
                                </span>
                            </label>
                        </div>
                    </CardPanel>
                </div>

                <aside class="space-y-6">
                    <CardPanel title="Status Tag" subtitle="Auto-suggested from quantity; you can override.">
                        <FormSelect
                            v-model="form.values.status_tag"
                            name="status_tag"
                            label="Tag"
                            include-blank
                            blank-label="Auto (derive from quantity)"
                            :options="tagOptions"
                        />
                    </CardPanel>

                    <CardPanel title="Visibility">
                        <FormCheckbox
                            v-model="form.values.is_active"
                            name="is_active"
                            :value="1"
                            label="Active"
                        />
                    </CardPanel>

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-primary w-full" :disabled="!canManage">
                            {{ editing ? 'Save Changes' : 'Create Item' }}
                        </button>
                        <router-link :to="{ name: 'admin.inventory.index' }" class="btn-ghost w-full">Cancel</router-link>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</template>
