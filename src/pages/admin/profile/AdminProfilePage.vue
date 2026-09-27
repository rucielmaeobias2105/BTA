<script setup>
import { computed, provide } from 'vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormPassword from '@/components/ui/form/FormPassword.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { admins, findAdmin, findAdminByEmail } from '@/data/admins';
import { AdminRole } from '@/data/enums';
import { formatDate } from '@/lib/format';
import { currentAdmin, setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';

/**
 * Admin — My Profile.
 *
 * Ported from `admin/profile/edit.blade.php` and
 * `Admin\ProfileController::edit()` / `update()`. Every admin role may edit its
 * own account here — the routes carried no `admin.role` middleware — so there is
 * nothing to gate.
 *
 * `currentAdmin` is a plain session object rather than a store record, so the
 * form edits a local copy and writes it back on save. `username` and the machine
 * `role` live on the `admins` record, so those two are seeded from and written
 * back to it — that is what keeps `roleCan()` in the rest of the panel honest
 * after a role change.
 */
const admin = currentAdmin;

const record = computed(() => findAdmin(admin.value?.id) ?? null);

/** `AdminRole::options()` — a value => label map, as Blade received it. */
const ROLE_VALUES = Object.values(AdminRole).map((meta) => meta.value);

const roleOptions = computed(() =>
    Object.fromEntries(Object.values(AdminRole).map((meta) => [meta.value, meta.label])),
);

const form = createForm({
    initial: {
        first_name: admin.value?.first_name ?? '',
        last_name: admin.value?.last_name ?? '',
        email: admin.value?.email ?? '',
        username: record.value?.username ?? '',
        role: record.value?.role ?? AdminRole.SuperAdmin.value,
        // Optional password change — only validated when supplied.
        password: '',
        password_confirmation: '',
    },
    rules: {
        first_name: ['required', 'string', 'max:100'],
        last_name: ['required', 'string', 'max:100'],
        email: ['required', 'string', 'email', 'max:255'],
        // `alpha_dash`
        username: ['required', 'string', 'max:64', 'regex:^[A-Za-z0-9_-]+$'],
        role: ['required', `in:${ROLE_VALUES.join(',')}`],
        password: ['nullable', 'string', 'min:8', 'confirmed'],
    },
    messages: {
        regex: 'The username may only contain letters, numbers, dashes, and underscores.',
    },
    /**
     * The two `Rule::unique('admins', …)->ignore($admin->id)` checks. They only
     * run once the declarative rules pass, which is where `withValidator()` sat.
     */
    after(errors, values) {
        if (!admin.value) return;

        const owner = findAdminByEmail(values.email);

        if (owner && String(owner.id) !== String(admin.value.id)) {
            errors.email = 'The email has already been taken.';
        }

        const taken = admins.some(
            (row) => String(row.id) !== String(admin.value.id)
                && String(row.username).toLowerCase() === String(values.username).toLowerCase(),
        );

        if (taken) errors.username = 'The username has already been taken.';
    },
});

provide('form-errors', form.errors);

/** The session object carries no login stamp, so the Blade's fallback is what shows. */
const lastSignedIn = computed(() => {
    const stamp = admin.value?.last_login_at;

    return stamp ? formatDate(stamp, 'M j, Y g:i A') : 'this is your first session';
});

function submit() {
    if (!admin.value) return;

    if (!form.validate()) return;

    const fullName = `${form.values.first_name} ${form.values.last_name}`.trim();
    const roleLabel = AdminRole[form.values.role]?.label ?? form.values.role;

    Object.assign(admin.value, {
        first_name: form.values.first_name,
        last_name: form.values.last_name,
        full_name: fullName,
        email: form.values.email,
        // The session object carries the role *label*; the `admins` record
        // carries the machine value, which is what `roleCan()` matches on.
        role: roleLabel,
        role_label: roleLabel,
    });

    if (record.value) {
        Object.assign(record.value, {
            first_name: form.values.first_name,
            last_name: form.values.last_name,
            full_name: fullName,
            email: form.values.email,
            username: form.values.username,
            role: form.values.role,
        });
    }

    form.values.password = '';
    form.values.password_confirmation = '';

    setFlash('Your admin profile has been updated.');
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Account"
            title="My Profile"
            description="Update the credentials you use to sign in to the admin panel."
        />

        <ErrorSummary />

        <form class="max-w-3xl space-y-6" novalidate @submit.prevent="submit">
            <CardPanel title="Personal Details">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormInput v-model="form.values.first_name" name="first_name" label="First Name" required />
                    <FormInput v-model="form.values.last_name" name="last_name" label="Last Name" required />
                    <FormInput v-model="form.values.email" name="email" type="email" label="Email" required />
                    <FormInput v-model="form.values.username" name="username" label="Username" required />
                    <FormSelect v-model="form.values.role" name="role" label="Role" required :options="roleOptions" />
                </div>

                <div class="mt-5 rounded-xl bg-linen/70 px-4 py-3 text-xs text-ink-muted">
                    Last signed in: <span class="font-medium text-primary">{{ lastSignedIn }}</span>
                </div>
            </CardPanel>

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
