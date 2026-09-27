<script setup>
import { computed, onBeforeUnmount, provide, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormTextarea from '@/components/ui/form/FormTextarea.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { findPromo, promos } from '@/data/promos';
import { toDateInput } from '@/lib/dates';
import { setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';

/**
 * Admin Flow 13 (part 2) — Promo & Announcements editor.
 *
 * Ported from `admin/promos/_form.blade.php` (included by both the `create` and
 * the `edit` wrapper) plus `Admin\PromoController::store()` and `update()`.
 * The route decides the mode: `/admin/promos/create` carries no `:id`,
 * `/admin/promos/:id/edit` does — which is what `$promo->exists` keyed off.
 *
 * Promos are date-windowed, so `starts_at` / `ends_at` are `<input type="date">`
 * values, and `is_active` is a checkbox that is checked by default on create
 * (`old('is_active', $promo->exists ? $promo->is_active : true)`).
 */
const route = useRoute();
const router = useRouter();

const promoId = computed(() => route.params.id ?? null);
const promo = computed(() => (promoId.value ? findPromo(promoId.value) : null));
const isEditing = computed(() => Boolean(promo.value));

/* ------------------------------------------------------------------ */
/* Form                                                                 */
/* ------------------------------------------------------------------ */

const form = createForm({
    initial: {
        title: promo.value?.title ?? '',
        description: promo.value?.description ?? '',
        starts_at: promo.value ? toDateInput(promo.value.starts_at) : '',
        ends_at: promo.value ? toDateInput(promo.value.ends_at) : '',
        // A new promo defaults to active; an existing one keeps its flag.
        is_active: promo.value ? Boolean(promo.value.is_active) : true,
    },
    rules: {
        title: ['required', 'string', 'max:180'],
        description: ['required', 'string', 'max:3000'],
        starts_at: ['required', 'date_format:Y-m-d'],
        ends_at: ['required', 'date_format:Y-m-d'],
        is_active: ['nullable'],
    },
    // `after_or_equal:starts_at`, which `validateField` has no declarative rule
    // for — the Blade-era `ends_at.after_or_equal` message is reproduced here.
    after(errors, values) {
        if (values.ends_at && values.starts_at && values.ends_at < values.starts_at) {
            errors.ends_at = 'The end date cannot be before the start date.';
        }
    },
});

provide('form-errors', form.errors);

/* ------------------------------------------------------------------ */
/* Image                                                                */
/* ------------------------------------------------------------------ */

/**
 * There is no storage behind the SPA, so the chosen file is held as an object
 * URL and becomes the promo's `image_path`, the way `ProfileEditPage` does it.
 */
const imageFile = ref(null);
const imagePreview = ref(null);
let objectUrl = null;

const hasImage = computed(() => Boolean(imagePreview.value || promo.value?.image_path));
const imageSource = computed(() => imagePreview.value ?? promo.value?.image_path ?? '');

function onImageChange(event) {
    const file = event.target.files?.[0] ?? null;

    imageFile.value = file;

    if (objectUrl) URL.revokeObjectURL(objectUrl);

    objectUrl = file ? URL.createObjectURL(file) : null;
    imagePreview.value = objectUrl;
}

onBeforeUnmount(() => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
});

/** `image` + `mimes:jpg,jpeg,png,webp` + `max:3072` — checked against the file. */
function imageProblem() {
    if (!imageFile.value) return null;

    if (!String(imageFile.value.type ?? '').startsWith('image/')) {
        return 'The promo image must be an image.';
    }

    if (imageFile.value.size > 3 * 1024 * 1024) {
        return 'The promo image may not be larger than 3 MB.';
    }

    return null;
}

/* ------------------------------------------------------------------ */
/* Saving                                                               */
/* ------------------------------------------------------------------ */

const submitLabel = computed(() => (isEditing.value ? 'Save Changes' : 'Create Promo'));

function clearImageOverride() {
    if (objectUrl) URL.revokeObjectURL(objectUrl);

    objectUrl = null;
    imagePreview.value = null;
    imageFile.value = null;
}

/** `PromoController::store()` */
function store() {
    const row = reactive({
        id: promos.reduce((highest, item) => Math.max(highest, item.id), 0) + 1,
        title: form.values.title,
        description: form.values.description,
        starts_at: new Date(`${form.values.starts_at}T00:00:00`),
        ends_at: new Date(`${form.values.ends_at}T00:00:00`),
        image_path: imageFile.value ? imagePreview.value : null,
        is_active: Boolean(form.values.is_active),
        notified: false,
    });

    promos.push(row);

    clearImageOverride();

    setFlash(`Promo "${row.title}" created.`);

    router.push({ name: 'admin.promos.index' });
}

/** `PromoController::update()` */
function update(target) {
    Object.assign(target, {
        title: form.values.title,
        description: form.values.description,
        starts_at: new Date(`${form.values.starts_at}T00:00:00`),
        ends_at: new Date(`${form.values.ends_at}T00:00:00`),
        image_path: imageFile.value ? imagePreview.value : target.image_path,
        is_active: Boolean(form.values.is_active),
    });

    clearImageOverride();

    setFlash(`Promo "${target.title}" updated.`);

    router.push({ name: 'admin.promos.index' });
}

function submit() {
    if (!form.validate()) return;

    const problem = imageProblem();

    if (problem) {
        form.errors.image = problem;

        return;
    }

    if (isEditing.value) {
        update(promo.value);

        return;
    }

    store();
}

/**
 * Create and edit share one component, so moving between the two routes keeps
 * the instance alive — the form and the image preview are re-seeded here.
 */
watch(promoId, () => {
    form.reset({
        title: promo.value?.title ?? '',
        description: promo.value?.description ?? '',
        starts_at: promo.value ? toDateInput(promo.value.starts_at) : '',
        ends_at: promo.value ? toDateInput(promo.value.ends_at) : '',
        is_active: promo.value ? Boolean(promo.value.is_active) : true,
    });

    clearImageOverride();
});
</script>

<template>
    <div>
        <router-link
            :to="{ name: 'admin.promos.index' }"
            class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to promos
        </router-link>

        <PageHeader
            eyebrow="Marketing"
            :title="isEditing ? 'Edit Promo' : 'New Promo'"
            description="Active promos surface as a site-wide banner. Use the promo list to push them to customers as notifications."
        />

        <ErrorSummary />

        <form novalidate @submit.prevent="submit">
            <div class="grid gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <CardPanel title="Promo Details">
                        <FormInput
                            v-model="form.values.title"
                            name="title"
                            label="Promo Title"
                            required
                            placeholder="e.g. Glow Package Discount"
                        />

                        <div class="mt-5">
                            <FormTextarea
                                v-model="form.values.description"
                                name="description"
                                label="Description"
                                required
                                :rows="5"
                                placeholder="What is the offer, and what does the customer get?"
                            />
                        </div>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <FormInput v-model="form.values.starts_at" name="starts_at" type="date" label="Valid From" required />
                            <FormInput v-model="form.values.ends_at" name="ends_at" type="date" label="Valid Until" required />
                        </div>
                    </CardPanel>
                </div>

                <aside class="space-y-6">
                    <CardPanel title="Promo Image">
                        <img
                            v-if="hasImage"
                            :src="imageSource"
                            alt=""
                            class="mb-4 aspect-[16/9] w-full rounded-xl object-cover"
                        >

                        <div>
                            <label for="image" class="label">
                                Upload Image
                                <span class="text-status-cancelled">*</span>
                            </label>

                            <input
                                id="image"
                                name="image"
                                type="file"
                                class="input"
                                :class="form.errors.image ? 'input-error' : ''"
                                accept="image/jpeg,image/png,image/webp"
                                :aria-invalid="form.errors.image ? 'true' : undefined"
                                @change="onImageChange"
                            >
                        </div>

                        <p class="input-hint">JPG, PNG or WEBP. Max 3 MB.</p>

                        <p v-if="form.errors.image" class="input-error-text">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.image }}
                        </p>
                    </CardPanel>

                    <CardPanel title="Visibility">
                        <FormCheckbox
                            v-model="form.values.is_active"
                            name="is_active"
                            :value="1"
                            label="Active"
                            hint="Must also fall within the validity period to be shown."
                        />
                    </CardPanel>

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-primary w-full">{{ submitLabel }}</button>
                        <router-link :to="{ name: 'admin.promos.index' }" class="btn-ghost w-full">Cancel</router-link>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</template>
