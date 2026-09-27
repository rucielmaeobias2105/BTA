<script setup>
import { computed, onBeforeUnmount, provide, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormTextarea from '@/components/ui/form/FormTextarea.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { services } from '@/data/services';
import { createForm } from '@/lib/validation';
import { setFlash } from '@/lib/session';

/**
 * Admin Flow 5 — Service create / edit.
 *
 * Ported from `admin/services/_form.blade.php` (both `create` and `edit` merely
 * included it) plus `Admin\ServiceRequest` and `ServiceController::store()` /
 * `update()` / `syncVariants()`. The same component serves both routes; the
 * presence of `route.params.id` is what `Service::exists` used to decide.
 *
 * The variant rows were an Alpine `variantEditor()` with real `name` attributes
 * so the browser posted a normal `variants[]` array. They are local reactive
 * rows here and are reconciled into `service.variants` on save, following
 * `syncVariants()` row for row.
 */

const route = useRoute();
const router = useRouter();

/** `$service->exists` — the create route has no id, the edit route always does. */
const service = computed(() => (route.params.id ? services.find((row) => String(row.id) === String(route.params.id)) ?? null : null));
const editing = computed(() => Boolean(service.value));

const description = computed(() =>
    editing.value
        ? `Update the details, photo and variants for “${service.value.name}”.`
        : 'Create a new service for the customer-facing menu.',
);

/** `ServiceController::categorySuggestions()` — existing categories plus the seed list. */
const SUGGESTED_CATEGORIES = [
    'Hair Care', 'Hair Styling', 'Nail Care', 'Lash & Brow', 'Skincare',
    'Massage & Spa', 'Facial', 'Waxing & Threading', 'Makeup', 'Packages',
];

const categories = computed(() => {
    const existing = [...new Set(services.map((row) => row.category))].sort();

    return [...new Set([...existing, ...SUGGESTED_CATEGORIES])];
});

/* ------------------------------------------------------------------ */
/* Form                                                                */
/* ------------------------------------------------------------------ */

const form = createForm({
    initial: {
        name: service.value?.name ?? '',
        slug: service.value?.slug ?? '',
        category: service.value?.category ?? '',
        price: service.value?.price ?? '',
        // `old('duration_minutes', $service->duration_minutes ?: 60)`
        duration_minutes: service.value?.duration_minutes || 60,
        description: service.value?.description ?? '',
        is_active: service.value ? service.value.is_active : true,
        is_featured: service.value?.is_featured ?? false,
    },
    rules: {
        name: ['required', 'string', 'max:150'],
        // Always populated by `prepareForValidation()`, so `alpha_dash` only
        // fails for a name with nothing sluggable in it.
        slug: ['nullable', 'string', 'max:180', 'regex:^[A-Za-z0-9_\\-]+$'],
        category: ['required', 'string', 'max:80'],
        price: ['required'],
        duration_minutes: ['required'],
        description: ['nullable', 'string', 'max:3000'],
        is_active: ['nullable'],
        is_featured: ['nullable'],
    },
    after(errors) {
        // `numeric|min:0|max:999999` and `integer|min:5|max:1440`. The shared
        // validator reads `min`/`max` as string lengths, so the numeric bounds
        // are checked here in Laravel's own wording.
        checkNumber('price', { min: 0, max: 999999 });

        if (!errors.duration_minutes) {
            checkNumber('duration_minutes', { min: 5, max: 1440, integer: true });
        }

        // `Rule::unique('services', 'slug')->ignore($serviceId)`
        if (!errors.slug) {
            const clash = services.find((row) => row.slug === form.values.slug && row.id !== service.value?.id);

            if (clash) errors.slug = 'The slug has already been taken.';
        }

        // `variants.*`
        const problem = variantProblem();

        if (problem) errors.variants = problem;
    },
});

// Lets the form components read their error without every call site threading
// the bag through by hand — the replacement for Blade's global `$errors`.
provide('form-errors', form.errors);

/**
 * `min` / `max` on a numeric column, reported the way Laravel would.
 *
 * `label` is the snake_cased attribute rendered the way `getAttribute()` does
 * it, so `duration_minutes` reads "duration minutes".
 */
function checkNumber(field, { min = null, max = null, integer = false } = {}) {
    const label = field.replaceAll('_', ' ');
    const raw = String(form.values[field] ?? '').trim();
    const value = Number(raw);

    if (raw === '' || !Number.isFinite(value)) {
        form.errors[field] = `The ${label} field must be ${integer ? 'an integer' : 'a number'}.`;

        return false;
    }

    if (integer && !Number.isInteger(value)) {
        form.errors[field] = `The ${label} field must be an integer.`;

        return false;
    }

    if (min !== null && value < min) {
        form.errors[field] = `The ${label} field must be at least ${min}.`;

        return false;
    }

    if (max !== null && value > max) {
        form.errors[field] = `The ${label} field may not be greater than ${max}.`;

        return false;
    }

    return true;
}

/* ------------------------------------------------------------------ */
/* Variants — the Alpine `variantEditor()` rows                         */
/* ------------------------------------------------------------------ */

/** Same shape the Blade seed used: `key`, `id`, `name`, `price`, `duration`, `isDefault`. */
const variantRows = ref(
    (service.value?.variants ?? []).map((variant) => ({
        key: `existing-${variant.id}`,
        id: variant.id,
        name: variant.name,
        price: variant.price,
        duration: variant.duration_minutes,
        isDefault: Boolean(variant.is_default),
    })),
);

/** The inline `x-text="error"` line under the rows. */
const variantError = ref('');

function addVariant() {
    variantRows.value.push({
        key: `new-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
        id: null,
        name: '',
        price: 0,
        duration: null,
        isDefault: variantRows.value.length === 0,
    });

    validateVariants();
}

function removeVariant(index) {
    variantRows.value.splice(index, 1);

    validateVariants();
}

function makeDefault(index) {
    variantRows.value.forEach((row, position) => {
        row.isDefault = position === index;
    });
}

/**
 * The first `variants.*` failure, or null.
 *
 * The Blade form also ran a client-side duplicate-name guard on every input;
 * that check leads here, ahead of the request's per-row rules.
 */
function variantProblem() {
    const seen = new Set();

    for (const [index, row] of variantRows.value.entries()) {
        const name = String(row.name ?? '').trim();
        const price = String(row.price ?? '').trim();
        const duration = String(row.duration ?? '').trim();

        if (!name) return `The variants.${index}.name field is required.`;

        if (seen.has(name.toLowerCase())) return 'Variant names must be unique.';

        seen.add(name.toLowerCase());

        if (!price || !Number.isFinite(Number(price))) {
            return `The variants.${index}.price field must be a number.`;
        }

        if (Number(price) < 0) return `The variants.${index}.price field must be at least 0.`;
        if (Number(price) > 999999) return `The variants.${index}.price field may not be greater than 999999.`;

        if (duration) {
            const minutes = Number(duration);

            if (!Number.isInteger(minutes)) return `The variants.${index}.duration_minutes field must be an integer.`;
            if (minutes < 5) return `The variants.${index}.duration_minutes field must be at least 5.`;
            if (minutes > 1440) return `The variants.${index}.duration_minutes field may not be greater than 1440.`;
        }
    }

    return null;
}

function validateVariants() {
    variantError.value = variantProblem() ?? '';
}

/* ------------------------------------------------------------------ */
/* Photo — no storage behind the demo, so the choice is a preview URL   */
/* ------------------------------------------------------------------ */

const photoFile = ref(null);
const photoPreview = ref(null);
let objectUrl = null;

function onPhotoChange(event) {
    const file = event.target.files?.[0] ?? null;

    photoFile.value = file;

    if (objectUrl) URL.revokeObjectURL(objectUrl);

    objectUrl = file ? URL.createObjectURL(file) : null;
    photoPreview.value = objectUrl;
}

onBeforeUnmount(() => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
});

/** `image|mimes:jpg,jpeg,png,webp|max:3072` with the request's own messages. */
function photoProblem() {
    if (!photoFile.value) return null;

    if (!String(photoFile.value.type ?? '').startsWith('image/')) {
        return 'The service photo must be an image.';
    }

    if (photoFile.value.size > 3 * 1024 * 1024) {
        return 'The service photo may not be larger than 3 MB.';
    }

    return null;
}

/* ------------------------------------------------------------------ */
/* Save                                                                */
/* ------------------------------------------------------------------ */

/** `ServiceRequest::prepareForValidation()` — `Str::slug()` over slug-or-name. */
function prepare() {
    form.values.name = String(form.values.name ?? '').trim();
    form.values.category = String(form.values.category ?? '').trim();
    form.values.slug = slugify(String(form.values.slug ?? '').trim() || form.values.name);
}

function slugify(value) {
    return String(value)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}

function nextServiceId() {
    return Math.max(0, ...services.map((row) => row.id)) + 1;
}

function nextVariantId() {
    return Math.max(0, ...services.flatMap((row) => row.variants.map((variant) => variant.id))) + 1;
}

/**
 * `ServiceController::syncVariants()`.
 *
 * Blank-named rows are skipped, a row that asks to be the default clears the
 * others, and anything the form did not submit is removed.
 */
function syncVariants(target) {
    const keep = [];

    variantRows.value.forEach((row) => {
        if (!String(row.name ?? '').trim()) return;

        const attributes = {
            name: row.name,
            price: Number(row.price),
            // Blank inputs are simply absent from the payload.
            duration_minutes: Number(row.duration) || null,
            is_default: Boolean(row.isDefault),
        };

        const existing = row.id ? target.variants.find((variant) => variant.id === row.id) : null;

        if (existing) {
            if (attributes.is_default) {
                target.variants.forEach((variant) => {
                    if (variant.id !== existing.id) variant.is_default = false;
                });
            }

            Object.assign(existing, attributes);
            keep.push(existing.id);

            return;
        }

        if (attributes.is_default) {
            target.variants.forEach((variant) => {
                variant.is_default = false;
            });
        }

        const created = { id: nextVariantId(), service_id: target.id, ...attributes };

        target.variants.push(created);
        keep.push(created.id);
    });

    for (let index = target.variants.length - 1; index >= 0; index -= 1) {
        if (!keep.includes(target.variants[index].id)) target.variants.splice(index, 1);
    }
}

function submit() {
    if (editing.value && !service.value) return;

    prepare();

    if (!form.validate()) return;

    const problem = photoProblem();

    if (problem) {
        form.errors.photo = problem;

        return;
    }

    const data = {
        name: form.values.name,
        slug: form.values.slug,
        category: form.values.category,
        price: Number(form.values.price),
        duration_minutes: Number(form.values.duration_minutes),
        description: form.values.description,
        photo_path: photoFile.value
            ? photoPreview.value
            : (service.value?.photo_path ?? null),
        is_active: Boolean(form.values.is_active),
        is_featured: Boolean(form.values.is_featured),
    };

    if (editing.value) {
        Object.assign(service.value, data);
        syncVariants(service.value);

        setFlash(`Service "${data.name}" updated.`);
    } else {
        services.push({ id: nextServiceId(), ...data, variants: [] });

        // Pushed through the array so the new record is the reactive proxy.
        syncVariants(services[services.length - 1]);

        setFlash(`Service "${data.name}" created.`);
    }

    router.push({ name: 'admin.services.index' });
}
</script>

<template>
    <div>
        <router-link
            :to="{ name: 'admin.services.index' }"
            class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to services
        </router-link>

        <PageHeader
            eyebrow="Catalogue"
            :title="editing ? 'Edit Service' : 'Add Service'"
            :description="description"
        />

        <ErrorSummary />

        <form novalidate @submit.prevent="submit">
            <div class="grid gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <CardPanel title="Service Details">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <FormInput
                                v-model="form.values.name"
                                name="name"
                                label="Service Name"
                                required
                            />
                            <FormInput
                                v-model="form.values.slug"
                                name="slug"
                                label="Slug"
                                hint="Leave blank to generate from the name."
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
                                        list="bta-service-categories"
                                        placeholder="e.g. Nail Care"
                                        :value="form.values.category"
                                        required
                                        class="input"
                                        :class="form.errors.category ? 'input-error' : ''"
                                        :aria-invalid="form.errors.category ? 'true' : undefined"
                                        @input="form.values.category = $event.target.value"
                                    >
                                </div>

                                <datalist id="bta-service-categories">
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

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="price" class="label">
                                    Price (₱)
                                    <span class="text-status-cancelled">*</span>
                                </label>

                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm text-ink-muted">₱</span>
                                    <input
                                        id="price"
                                        name="price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :value="form.values.price"
                                        required
                                        class="input pl-10"
                                        :class="form.errors.price ? 'input-error' : ''"
                                        :aria-invalid="form.errors.price ? 'true' : undefined"
                                        @input="form.values.price = $event.target.value"
                                    >
                                </div>

                                <p v-if="form.errors.price" class="input-error-text">
                                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                    </svg>
                                    {{ form.errors.price }}
                                </p>
                            </div>

                            <div>
                                <label for="duration_minutes" class="label">
                                    Duration (minutes)
                                    <span class="text-status-cancelled">*</span>
                                </label>

                                <div class="relative">
                                    <input
                                        id="duration_minutes"
                                        name="duration_minutes"
                                        type="number"
                                        min="5"
                                        max="1440"
                                        :value="form.values.duration_minutes"
                                        required
                                        class="input"
                                        :class="form.errors.duration_minutes ? 'input-error' : ''"
                                        :aria-invalid="form.errors.duration_minutes ? 'true' : undefined"
                                        @input="form.values.duration_minutes = $event.target.value"
                                    >
                                </div>

                                <p v-if="form.errors.duration_minutes" class="input-error-text">
                                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                    </svg>
                                    {{ form.errors.duration_minutes }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <FormTextarea
                                v-model="form.values.description"
                                name="description"
                                label="Description"
                                :rows="4"
                                placeholder="What does this service include?"
                            />
                        </div>
                    </CardPanel>

                    <!-- Variants (short/long hair style pricing) -->
                    <CardPanel title="Variants" subtitle="Optional — e.g. Short Hair, Long Hair with different pricing.">
                        <div id="variant-rows" class="space-y-3">
                            <div
                                v-for="(variant, index) in variantRows"
                                :key="variant.key"
                                class="flex flex-wrap items-end gap-3 rounded-xl border border-primary/12 bg-linen/50 p-3.5"
                            >
                                <input type="hidden" :name="`variants[${index}][id]`" :value="variant.id ?? ''">

                                <div class="min-w-40 flex-1">
                                    <label class="label" :for="`variant-name-${index}`">Variant Name</label>
                                    <input
                                        :id="`variant-name-${index}`"
                                        :name="`variants[${index}][name]`"
                                        v-model="variant.name"
                                        type="text"
                                        placeholder="Short Hair"
                                        maxlength="100"
                                        class="input"
                                        @input="validateVariants"
                                    >
                                </div>

                                <div class="w-32">
                                    <label class="label" :for="`variant-price-${index}`">Price (₱)</label>
                                    <input
                                        :id="`variant-price-${index}`"
                                        :name="`variants[${index}][price]`"
                                        v-model="variant.price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="input"
                                        @input="validateVariants"
                                    >
                                </div>

                                <div class="w-32">
                                    <label class="label" :for="`variant-duration-${index}`">Duration</label>
                                    <input
                                        :id="`variant-duration-${index}`"
                                        :name="`variants[${index}][duration_minutes]`"
                                        v-model="variant.duration"
                                        type="number"
                                        min="5"
                                        max="1440"
                                        class="input"
                                        placeholder="inherit"
                                        @input="validateVariants"
                                    >
                                </div>

                                <label class="flex cursor-pointer items-center gap-2 pb-2.5 text-xs font-medium text-ink">
                                    <input
                                        :name="`variants[${index}][is_default]`"
                                        v-model="variant.isDefault"
                                        type="checkbox"
                                        value="1"
                                        class="checkbox"
                                        @change="makeDefault(index)"
                                    >
                                    Default
                                </label>

                                <button type="button" class="btn-danger btn-sm mb-1" @click="removeVariant(index)">Remove</button>
                            </div>
                        </div>

                        <p v-if="variantError" class="input-error-text mt-3">{{ variantError }}</p>

                        <button type="button" class="btn-secondary btn-sm mt-4" @click="addVariant">+ Add Variant</button>
                    </CardPanel>
                </div>

                <!-- Sidebar -->
                <aside class="space-y-6">
                    <CardPanel title="Photo">
                        <img
                            v-if="service?.photo_path"
                            :src="service.photo_path"
                            alt=""
                            class="mb-4 aspect-[4/3] w-full rounded-xl object-cover"
                        >

                        <div>
                            <label for="photo" class="label">Upload Photo</label>

                            <div class="relative">
                                <input
                                    id="photo"
                                    name="photo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="input"
                                    :class="form.errors.photo ? 'input-error' : ''"
                                    :aria-invalid="form.errors.photo ? 'true' : undefined"
                                    @change="onPhotoChange"
                                >
                            </div>
                        </div>

                        <p class="input-hint">JPG, PNG or WEBP. Max 3 MB.</p>

                        <p v-if="form.errors.photo" class="input-error-text">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.photo }}
                        </p>

                        <img
                            v-if="photoPreview"
                            :src="photoPreview"
                            alt=""
                            class="mt-4 aspect-[4/3] w-full rounded-xl object-cover"
                        >
                    </CardPanel>

                    <CardPanel title="Visibility">
                        <div class="space-y-4">
                            <FormCheckbox
                                v-model="form.values.is_active"
                                name="is_active"
                                :value="1"
                                label="Active (visible to customers)"
                            />
                            <FormCheckbox
                                v-model="form.values.is_featured"
                                name="is_featured"
                                :value="1"
                                label="Featured on the home page"
                            />
                        </div>
                    </CardPanel>

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-primary w-full">
                            {{ editing ? 'Save Changes' : 'Create Service' }}
                        </button>
                        <router-link :to="{ name: 'admin.services.index' }" class="btn-ghost w-full">Cancel</router-link>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</template>
