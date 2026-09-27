<script setup>
import { provide, ref } from 'vue';
import { useRouter } from 'vue-router';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormPassword from '@/components/ui/form/FormPassword.vue';
import { signInAdmin } from '@/lib/session';
import { createForm } from '@/lib/validation';

/**
 * Admin Flow 1 — Admin Login.
 *
 * Ported from `admin/auth/login.blade.php` and `Auth\AdminLoginRequest`. The
 * controller kept this guard completely separate from customer auth — its own
 * `admins` table, rate-limit bucket and session namespace — which is why the
 * field is `username` rather than the customer form's `login`, and why the hand
 * off is `signInAdmin()` rather than `signInCustomer()`. The hash check was
 * server-side, so any accepted credentials are accepted.
 *
 * The photo panel's `$panelTitle` / `$panelScript` cannot reach `GuestLayout`
 * from here, so the same words open the form panel instead.
 */
const router = useRouter();

const form = createForm({
    initial: { username: '', password: '', remember: false },
    rules: {
        username: ['required', 'string', 'max:64'],
        password: ['required', 'string'],
        remember: ['nullable', 'boolean'],
    },
});

provide('form-errors', form.errors);

const submitting = ref(false);

function submit() {
    if (!form.validate()) return;

    submitting.value = true;

    signInAdmin();

    router.push({ name: 'admin.dashboard' });
}
</script>

<template>
    <div>
        <div class="mb-7 text-center lg:text-left">
            <p class="mb-2 inline-flex items-center gap-1.5 rounded-pill bg-gold/15 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-gold-dark">
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                Staff Portal
            </p>

            <h1 class="font-display text-3xl font-bold tracking-tight text-primary">Admin Login</h1>
            <p class="mt-2 text-sm text-ink-muted">Sign in with your staff credentials.</p>

            <p class="mt-3 text-xs font-medium text-ink-muted">
                Salon Management, At Your Fingertips. — every screen in one place.
            </p>
        </div>

        <ErrorSummary />

        <form class="space-y-4" novalidate @submit.prevent="submit">
            <FormInput
                v-model="form.values.username"
                name="username"
                label="Username"
                placeholder="admin"
                required
            />

            <FormPassword v-model="form.values.password" name="password" label="Password" required />

            <FormCheckbox v-model="form.values.remember" name="remember" label="Remember me" />

            <button type="submit" class="btn-primary w-full" :disabled="submitting">
                Log In
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-muted">
            <router-link :to="{ name: 'home' }" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Back to the website</router-link>
        </p>
    </div>
</template>
