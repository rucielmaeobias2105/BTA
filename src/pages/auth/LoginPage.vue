<script setup>
import { provide, ref } from 'vue';
import { useRouter } from 'vue-router';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormPassword from '@/components/ui/form/FormPassword.vue';
import { currentUser, setFlash, signInCustomer } from '@/lib/session';
import { firstName } from '@/lib/format';
import { createForm } from '@/lib/validation';

/**
 * Customer Flow 2 — Login.
 *
 * Ported from `auth/login.blade.php` and `Auth\LoginRequest`. The controller
 * matched the single "Username or Email" field against either the `email` or
 * the `username` column and verified the hash server-side, so there is nothing
 * left to check here: any accepted credentials sign the demo customer in via
 * `signInCustomer()`, which stands in for `Auth::attempt()` plus the session
 * regeneration.
 *
 * The photo panel keeps `GuestLayout`'s defaults — `AuthenticatedSessionController`
 * was the one auth screen that did not supply its own `$panelTitle`.
 */
const router = useRouter();

const form = createForm({
    initial: { login: '', password: '', remember: false },
    rules: {
        login: ['required', 'string', 'max:255'],
        password: ['required', 'string'],
        remember: ['nullable', 'boolean'],
    },
});

provide('form-errors', form.errors);

const submitting = ref(false);

function submit() {
    // `LoginRequest::prepareForValidation()` lowercased and trimmed the
    // identifier before the lookup.
    form.values.login = form.values.login.trim().toLowerCase();

    form.validate();

    // `LoginRequest::messages()` replaced the required text for `login` alone,
    // while the shared validator keeps one message per rule, so the override is
    // applied here rather than through `messages`.
    if (!form.values.login) {
        form.errors.login = 'Please enter your username or email address.';
    }

    if (form.hasErrors.value) return;

    submitting.value = true;

    signInCustomer();

    setFlash(`Welcome back, ${firstName(currentUser.value?.full_name)}.`);
    router.push({ name: 'dashboard' });
}
</script>

<template>
    <div>
        <!-- Tabs: the reference puts sign-in and registration on one surface. -->
        <nav class="auth-tabs" aria-label="Authentication">
            <router-link :to="{ name: 'login' }" class="auth-tab auth-tab-active" aria-current="page">Login</router-link>
            <router-link :to="{ name: 'register' }" class="auth-tab">Register</router-link>
        </nav>

        <h1 class="font-display text-2xl font-bold tracking-tight text-primary">Welcome Back</h1>
        <p class="mt-1.5 text-sm text-ink-muted">
            Log in to your account to book appointments and manage your bookings.
        </p>

        <ErrorSummary />

        <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
            <FormInput
                v-model="form.values.login"
                name="login"
                label="Username or Email"
                placeholder="you@example.com"
                required
            />

            <FormPassword v-model="form.values.password" name="password" label="Password" required autocomplete="current-password" />

            <div class="flex items-center justify-between gap-3 pt-1">
                <FormCheckbox v-model="form.values.remember" name="remember" label="Remember me" wrapper-class="mb-0" />
                <router-link :to="{ name: 'password.request' }" class="toggle-link">Forgot your password?</router-link>
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="submitting">
                Log In
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-muted">
            Don't have an account?
            <router-link :to="{ name: 'register' }" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Register</router-link>
        </p>
    </div>
</template>
