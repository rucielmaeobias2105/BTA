<script setup>
import { computed, provide, reactive } from 'vue';
import { useRoute } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import { findAdmin } from '@/data/admins';
import { roleCan } from '@/data/enums';
import { findService } from '@/data/services';
import { currentAdmin, setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';

/**
 * Admin Flow 5 — Service variants (short hair / long hair pricing).
 *
 * Ported from `admin/services/variants.blade.php` and the three variant actions
 * on `Admin\ServiceController` (`storeVariant`, `updateVariant`,
 * `destroyVariant`). Every row on the left was its own `<form>` posting to a
 * single variant, and the add form on the right posted to the service; both are
 * local mutations of `service.variants` here.
 *
 * The "exactly one default" rule is enforced the way the controller enforced
 * it: asking to be the default clears every sibling *first*, and un-ticking the
 * box is left alone — the server never forced a replacement default.
 */

const route = useRoute();

const service = computed(() => findService(route.params.id));

/**
 * The signed-in demo admin is Maia Arjud (`admins` id 1, role `super_admin`).
 * Resolved through `findAdmin()` because the session record stores the human
 * label while `roleCan()` compares enum values.
 */
const role = computed(() => findAdmin(currentAdmin.value?.id)?.role ?? 'super_admin');

const canManage = computed(() => roleCan(role.value, 'catalog.manage'));

/* ------------------------------------------------------------------ */
/* Shared request rules                                               */
/* ------------------------------------------------------------------ */

/**
 * A numeric column bounded the way `storeVariant()` / `updateVariant()` bounded
 * it, reported in Laravel's wording.
 *
 * The shared validator reads `min`/`max` as string lengths, so the numeric
 * bounds live here. `label` is the attribute name as `getAttribute()` renders
 * it, so `duration_minutes` reads "duration minutes".
 */
function numberProblem(values, field, { min = null, max = null, integer = false, label = null } = {}) {
    const name = label ?? field.replaceAll('_', ' ');
    const raw = String(values[field] ?? '').trim();

    if (raw === '') return `The ${name} field is required.`;

    const value = Number(raw);

    if (!Number.isFinite(value)) return `The ${name} field must be ${integer ? 'an integer' : 'a number'}.`;
    if (integer && !Number.isInteger(value)) return `The ${name} field must be an integer.`;
    if (min !== null && value < min) return `The ${name} field must be at least ${min}.`;
    if (max !== null && value > max) return `The ${name} field may not be greater than ${max}.`;

    return null;
}

const PRICE_RULES = { min: 0, max: 999999 };
const DURATION_RULES = { min: 5, max: 1440, integer: true };

/** `Rule::unique('service_variants', 'name')->where('service_id', …)`. */
function nameClash(name, ignoreId) {
    if (!service.value) return null;

    const needle = String(name ?? '').trim();

    if (!needle) return null;

    return service.value.variants.some(
        (variant) => variant.id !== ignoreId && String(variant.name).trim().toLowerCase() === needle.toLowerCase(),
    );
}

function nextVariantId() {
    if (!service.value) return 1;

    return Math.max(0, ...service.value.variants.map((variant) => variant.id)) + 1;
}

/** The two `->update(['is_default' => false])` calls the controller ran. */
function clearDefaults(keepId = null) {
    if (!service.value) return;

    service.value.variants.forEach((variant) => {
        if (variant.id !== keepId) variant.is_default = false;
    });
}

/* ------------------------------------------------------------------ */
/* The "add variant" panel — `storeVariant()`                         */
/* ------------------------------------------------------------------ */

const addForm = createForm({
    initial: { name: '', price: '', duration_minutes: '', is_default: false },
    rules: {
        name: ['required', 'string', 'max:100'],
        price: ['required'],
        duration_minutes: ['nullable'],
        is_default: ['nullable'],
    },
    after(errors) {
        errors.price = numberProblem(addForm.values, 'price', PRICE_RULES) ?? '';

        if (!errors.duration_minutes) {
            errors.duration_minutes = numberProblem(addForm.values, 'duration_minutes', DURATION_RULES) ?? '';
        }

        if (!errors.name && nameClash(addForm.values.name, null)) {
            errors.name = 'The name has already been taken.';
        }

        // `after` must leave a clean bag clean, or the form never passes.
        Object.keys(errors).forEach((key) => {
            if (errors[key] === '') delete errors[key];
        });
    },
});

// Lets the form components read their error without every call site threading
// the bag through by hand — the replacement for Blade's global `$errors`.
provide('form-errors', addForm.errors);

function addVariant() {
    if (!service.value) return;

    if (!addForm.validate()) return;

    // A row that asks to be the default takes the flag from every sibling first.
    if (addForm.values.is_default) clearDefaults();

    const created = {
        id: nextVariantId(),
        service_id: service.value.id,
        name: String(addForm.values.name).trim(),
        price: Number(addForm.values.price),
        // Blank inputs are simply absent from the payload.
        duration_minutes: addForm.values.duration_minutes ? Number(addForm.values.duration_minutes) : null,
        is_default: Boolean(addForm.values.is_default),
    };

    service.value.variants.push(created);

    setFlash(`Variant "${created.name}" added to ${service.value.name}.`);

    addForm.reset({ name: '', price: '', duration_minutes: '', is_default: false });
}

/* ------------------------------------------------------------------ */
/* The per-row editors — `updateVariant()` / `destroyVariant()`        */
/* ------------------------------------------------------------------ */

/**
 * Draft values are held per variant id, so a row can be edited and then
 * abandoned, and the saved row stays the single source of truth.
 */
const drafts = reactive({});

/** Per-row messages, keyed the same way, since each row was its own form. */
const rowErrors = reactive({});

function draftFor(variant) {
    if (!drafts[variant.id]) {
        drafts[variant.id] = {
            name: variant.name,
            price: variant.price,
            duration_minutes: variant.duration_minutes ?? '',
            is_default: Boolean(variant.is_default),
        };
    }

    return drafts[variant.id];
}

/** `updateVariant()` — validate the draft, then apply it to the row in place. */
function saveVariant(variant) {
    if (!service.value) return;

    const draft = draftFor(variant);
    const errors = {};

    const name = String(draft.name ?? '').trim();

    if (!name) errors.name = 'The name field is required.';
    else if (name.length > 100) errors.name = 'The name may not be greater than 100 characters.';
    else if (nameClash(draft.name, variant.id)) errors.name = 'The name has already been taken.';

    const price = numberProblem(draft, 'price', PRICE_RULES);

    if (price) errors.price = price;

    // `nullable`, so a blank input simply inherits the service duration.
    if (String(draft.duration_minutes ?? '').trim() !== '') {
        const duration = numberProblem(draft, 'duration_minutes', DURATION_RULES);

        if (duration) errors.duration_minutes = duration;
    }

    rowErrors[variant.id] = errors;

    if (Object.keys(errors).length > 0) return;

    if (draft.is_default) clearDefaults(variant.id);

    Object.assign(variant, {
        name,
        price: Number(draft.price),
        duration_minutes: draft.duration_minutes ? Number(draft.duration_minutes) : null,
        is_default: Boolean(draft.is_default),
    });

    setFlash(`Variant "${variant.name}" updated.`);
}

/** `destroyVariant()` — the row's own `confirm()` then the delete. */
function destroyVariant(variant) {
    if (!service.value) return;

    if (!window.confirm(`Remove the “${variant.name}” variant?`)) return;

    const index = service.value.variants.findIndex((row) => row.id === variant.id);

    if (index === -1) return;

    const name = variant.name;

    service.value.variants.splice(index, 1);

    delete drafts[variant.id];
    delete rowErrors[variant.id];

    setFlash(`Variant "${name}" removed.`);
}

/** The row's messages, flattened the way a summary list reads. */
function rowMessages(variant) {
    return Object.values(rowErrors[variant.id] ?? {});
}
</script>

<template>
    <NotFoundPage v-if="!service" />

    <div v-else>
        <router-link
            :to="{ name: 'admin.services.index' }"
            class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to services
        </router-link>

        <PageHeader
            :eyebrow="service.category"
            :title="`Variants · ${service.name}`"
            description="Style-based pricing such as Short Hair / Long Hair. Exactly one variant can be the default."
        >
            <template #actions>
                <router-link :to="{ name: 'admin.services.edit', params: { id: service.id } }" class="btn-secondary btn-sm">
                    Edit Service
                </router-link>
            </template>
        </PageHeader>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <CardPanel title="Existing Variants">
                    <EmptyState
                        v-if="service.variants.length === 0"
                        title="No variants yet"
                        description="Add one on the right, e.g. Short Hair / Long Hair."
                    />

                    <ul v-else class="space-y-3">
                        <li
                            v-for="variant in service.variants"
                            :key="variant.id"
                            class="rounded-xl border border-primary/12 bg-linen/50 p-4"
                        >
                            <form class="flex flex-wrap items-end gap-3" novalidate @submit.prevent="saveVariant(variant)">
                                <div class="min-w-40 flex-1">
                                    <label class="label" :for="`variant-name-${variant.id}`">Name</label>
                                    <input
                                        :id="`variant-name-${variant.id}`"
                                        v-model="draftFor(variant).name"
                                        name="name"
                                        type="text"
                                        maxlength="100"
                                        class="input"
                                        :class="rowErrors[variant.id]?.name ? 'input-error' : ''"
                                        required
                                    >
                                </div>

                                <div class="w-32">
                                    <label class="label" :for="`variant-price-${variant.id}`">Price (₱)</label>
                                    <input
                                        :id="`variant-price-${variant.id}`"
                                        v-model="draftFor(variant).price"
                                        name="price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="input"
                                        :class="rowErrors[variant.id]?.price ? 'input-error' : ''"
                                        required
                                    >
                                </div>

                                <div class="w-32">
                                    <label class="label" :for="`variant-duration-${variant.id}`">Duration</label>
                                    <input
                                        :id="`variant-duration-${variant.id}`"
                                        v-model="draftFor(variant).duration_minutes"
                                        name="duration_minutes"
                                        type="number"
                                        min="5"
                                        max="1440"
                                        class="input"
                                        :class="rowErrors[variant.id]?.duration_minutes ? 'input-error' : ''"
                                        :placeholder="service.duration_minutes"
                                    >
                                </div>

                                <label class="flex cursor-pointer items-center gap-2 pb-2.5 text-xs font-medium text-ink">
                                    <input
                                        v-model="draftFor(variant).is_default"
                                        name="is_default"
                                        type="checkbox"
                                        value="1"
                                        class="checkbox"
                                    >
                                    Default
                                </label>

                                <button type="submit" class="btn-primary btn-sm mb-1" :disabled="!canManage">Save</button>
                            </form>

                            <p v-if="rowMessages(variant).length" class="input-error-text">
                                {{ rowMessages(variant).join(' ') }}
                            </p>

                            <div class="mt-2.5 flex justify-end">
                                <button
                                    type="button"
                                    class="toggle-link text-status-cancelled"
                                    :disabled="!canManage"
                                    @click="destroyVariant(variant)"
                                >Remove variant</button>
                            </div>
                        </li>
                    </ul>
                </CardPanel>
            </div>

            <aside>
                <CardPanel title="Add Variant">
                    <ErrorSummary />

                    <form class="space-y-4" novalidate @submit.prevent="addVariant">
                        <FormInput
                            v-model="addForm.values.name"
                            name="name"
                            label="Variant Name"
                            required
                            placeholder="Long Hair"
                        />
                        <FormInput
                            v-model="addForm.values.price"
                            name="price"
                            type="number"
                            label="Price (₱)"
                            required
                            prefix="₱"
                        />
                        <FormInput
                            v-model="addForm.values.duration_minutes"
                            name="duration_minutes"
                            type="number"
                            label="Duration (minutes)"
                            hint="Leave blank to inherit the service duration."
                        />
                        <FormCheckbox
                            v-model="addForm.values.is_default"
                            name="is_default"
                            :value="1"
                            label="Make this the default variant"
                        />

                        <button type="submit" class="btn-primary w-full" :disabled="!canManage">Add Variant</button>
                    </form>
                </CardPanel>
            </aside>
        </div>
    </div>
</template>
