<script setup>
import { computed, onBeforeUnmount, provide, ref } from 'vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormPassword from '@/components/ui/form/FormPassword.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { findUser, findUserByEmail } from '@/data/users';
import { currentUser, setFlash } from '@/lib/session';
import { CONTACT_NUMBER_MESSAGES, CONTACT_NUMBER_RULES, createForm } from '@/lib/validation';
import { initials } from '@/lib/format';

/**
 * Customer Flow 11 — Profile Management.
 *
 * Ported from `customer/profile/edit.blade.php`, `Customer\ProfileController`
 * and `Customer\UpdateProfileRequest`. `currentUser` is a plain session object
 * rather than a store record, so the form edits a local copy and writes it back
 * on save. The file upload has no storage behind it, so the chosen file is held
 * as an object URL and becomes the session's `profile_photo_path`.
 */
const user = currentUser;

/**
 * Seeded once from the signed-in customer, exactly as the controller filled it;
 * `form.values` is the local reactive copy the fields bind to.
 */
const form = createForm({
    initial: {
        first_name: user.value?.first_name ?? '',
        last_name: user.value?.last_name ?? '',
        email: user.value?.email ?? '',
        contact_number: user.value?.contact_number ?? '',
        password: '',
        password_confirmation: '',
        remove_photo: 0,
    },
    rules: {
        first_name: ['required', 'string', 'max:100'],
        last_name: ['required', 'string', 'max:100'],
        email: ['required', 'string', 'email', 'max:255'],
        contact_number: CONTACT_NUMBER_RULES,
        // Optional password change — only validated when supplied.
        password: ['nullable', 'string', 'min:8', 'confirmed'],
    },
    messages: {
        ...CONTACT_NUMBER_MESSAGES,
    },
});

provide('form-errors', form.errors);

const photoFile = ref(null);
const photoPreview = ref(null);
let objectUrl = null;

const monogram = computed(() => initials(form.values.first_name, form.values.last_name));
const hasPhoto = computed(() => Boolean(photoPreview.value || user.value?.profile_photo_path));
const photoSource = computed(() => photoPreview.value ?? user.value?.profile_photo_path ?? '');

/** `currentUser` carries no username; it is a column on the users table. */
const username = computed(() => findUser(user.value?.id)?.username ?? '');

function onPhotoChange(event) {
    const file = event.target.files?.[0] ?? null;

    photoFile.value = file;
    form.values.remove_photo = 0;

    if (objectUrl) URL.revokeObjectURL(objectUrl);

    objectUrl = file ? URL.createObjectURL(file) : null;
    photoPreview.value = objectUrl;
}

function removePhoto() {
    if (objectUrl) URL.revokeObjectURL(objectUrl);

    objectUrl = null;
    photoPreview.value = null;
    photoFile.value = null;
    form.values.remove_photo = 1;
}

onBeforeUnmount(() => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
});

/** `UpdateProfileRequest::prepareForValidation()`. */
function prepare() {
    form.values.email = form.values.email.toLowerCase().trim();
    form.values.contact_number = form.values.contact_number.trim();
}

/** `image` and `max:2048` are enforced by the native control and the file size. */
function photoProblem() {
    if (!photoFile.value) return null;

    if (!String(photoFile.value.type ?? '').startsWith('image/')) {
        return 'Your profile picture must be an image.';
    }

    if (photoFile.value.size > 2 * 1024 * 1024) {
        return 'Your profile picture may not be larger than 2 MB.';
    }

    return null;
}

function submit() {
    if (!user.value) return;

    prepare();

    if (!form.validate()) return;

    // `Rule::unique('users', 'email')->ignore($userId)`.
    const owner = findUserByEmail(form.values.email);

    if (owner && String(owner.id) !== String(user.value.id)) {
        form.errors.email = 'The email has already been taken.';

        return;
    }

    const problem = photoProblem();

    if (problem) {
        form.errors.profile_photo = problem;

        return;
    }

    Object.assign(user.value, {
        first_name: form.values.first_name,
        last_name: form.values.last_name,
        full_name: `${form.values.first_name} ${form.values.last_name}`.trim(),
        email: form.values.email,
        contact_number: form.values.contact_number,
        profile_photo_path: photoFile.value
            ? photoPreview.value
            : (form.values.remove_photo ? null : user.value.profile_photo_path),
    });

    form.values.password = '';
    form.values.password_confirmation = '';

    setFlash('Your profile has been updated.');
}
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <PageHeader
            eyebrow="Account"
            title="Profile Management"
            description="Keep your contact details and profile picture up to date."
        />

        <ErrorSummary />

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <!-- Profile picture -->
            <CardPanel title="Profile Picture">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <img
                        v-if="hasPhoto"
                        :src="photoSource"
                        alt="Profile picture"
                        class="h-24 w-24 rounded-full border-2 border-gold object-cover shadow-card"
                    >
                    <span
                        v-else
                        class="flex h-24 w-24 items-center justify-center rounded-full bg-primary font-display text-2xl font-semibold text-cream ring-2 ring-gold ring-offset-2 ring-offset-cream"
                    >
                        {{ monogram }}
                    </span>

                    <div class="flex-1">
                        <div>
                            <label for="profile_photo" class="label">
                                Upload a new picture
                                <span class="text-status-cancelled">*</span>
                            </label>

                            <input
                                id="profile_photo"
                                name="profile_photo"
                                type="file"
                                class="input"
                                :class="form.errors.profile_photo ? 'input-error' : ''"
                                accept="image/jpeg,image/png,image/webp"
                                :aria-invalid="form.errors.profile_photo ? 'true' : undefined"
                                @change="onPhotoChange"
                            >
                        </div>

                        <p class="input-hint">JPG, PNG or WEBP. Maximum 2 MB.</p>

                        <p v-if="form.errors.profile_photo" class="input-error-text">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.profile_photo }}
                        </p>

                        <button
                            v-if="hasPhoto"
                            type="button"
                            class="toggle-link mt-2"
                            @click="removePhoto"
                        >
                            Remove current picture
                        </button>
                    </div>
                </div>
            </CardPanel>

            <!-- Personal details -->
            <CardPanel title="Personal Details">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormInput v-model="form.values.first_name" name="first_name" label="First Name" required />
                    <FormInput v-model="form.values.last_name" name="last_name" label="Last Name" required />
                    <FormInput v-model="form.values.email" name="email" type="email" label="Email" required />
                    <FormInput v-model="form.values.contact_number" name="contact_number" label="Contact Number" required />
                </div>

                <div class="mt-5 rounded-xl bg-linen/70 px-4 py-3 text-xs text-ink-muted">
                    Username: <span class="font-medium text-primary">{{ username }}</span>
                    — you can use either your username or email to log in.
                </div>
            </CardPanel>

            <!-- Optional password change -->
            <CardPanel title="Change Password" subtitle="Leave both fields blank to keep your current password.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormPassword
                        v-model="form.values.password"
                        name="password"
                        label="New Password"
                        autocomplete="new-password"
                        hint="Minimum of 8 characters."
                    />
                    <FormPassword
                        v-model="form.values.password_confirmation"
                        name="password_confirmation"
                        label="Confirm New Password"
                        autocomplete="new-password"
                    />
                </div>
            </CardPanel>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary sm:min-w-44">Save Changes</button>
            </div>
        </form>
    </div>
</template>
