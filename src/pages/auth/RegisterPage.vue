<script setup>
import { provide, ref } from 'vue';
import { useRouter } from 'vue-router';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormPassword from '@/components/ui/form/FormPassword.vue';
import { findUserByEmail } from '@/data/users';
import { setFlash, signInCustomer } from '@/lib/session';
import { APP_NAME } from '@/lib/format';
import {
    CONTACT_NUMBER_MESSAGES,
    CONTACT_NUMBER_RULES,
    createForm,
} from '@/lib/validation';

/**
 * Customer Flow 1 — Register.
 *
 * Ported from `auth/register.blade.php` and `Auth\RegisterRequest`. The
 * controller created the user, fired `Registered` plus the welcome notification
 * and then called `Auth::login()`; with no server the only observable outcome
 * left is the session, so the form hands off to `signInCustomer()` and the
 * `session('status')` string the controller flashed.
 *
 * The photo panel's `$panelTitle` / `$panelScript` cannot reach `GuestLayout`
 * from here, so the same words open the form panel instead.
 */
const router = useRouter();

const form = createForm({
    initial: {
        first_name: '',
        last_name: '',
        email: '',
        contact_number: '',
        password: '',
        password_confirmation: '',
        terms: false,
    },
    rules: {
        first_name: ['required', 'string', 'max:100'],
        last_name: ['required', 'string', 'max:100'],
        email: ['required', 'string', 'email', 'max:255'],
        contact_number: CONTACT_NUMBER_RULES,
        password: ['required', 'string', 'min:8', 'confirmed'],
        terms: ['accepted'],
    },
    messages: {
        ...CONTACT_NUMBER_MESSAGES,
        accepted: 'You must agree to the Terms and Conditions to register.',
        confirmed: 'The password confirmation does not match.',
    },
    // `Rule::unique('users', 'email')` — the only rule with no client-side
    // equivalent, so it is checked here against the demo users instead.
    after(errors, values) {
        if (findUserByEmail(values.email)) {
            errors.email = 'The email has already been taken.';
        }
    },
});

provide('form-errors', form.errors);

const submitting = ref(false);

function submit() {
    // `RegisterRequest::prepareForValidation()` trimmed and lowercased the
    // email and trimmed the contact number, so the `unique` check below sees
    // the same address the controller would have stored.
    form.values.email = form.values.email.trim().toLowerCase();
    form.values.contact_number = form.values.contact_number.trim();

    if (!form.validate()) return;

    submitting.value = true;

    signInCustomer();

    setFlash(`Welcome to ${APP_NAME}, ${form.values.first_name}!`);
    router.push({ name: 'dashboard' });
}
</script>

<template>
    <div>
        <nav class="auth-tabs" aria-label="Authentication">
            <router-link :to="{ name: 'login' }" class="auth-tab">Login</router-link>
            <router-link :to="{ name: 'register' }" class="auth-tab auth-tab-active" aria-current="page">Register</router-link>
        </nav>

        <p class="mb-5 text-center text-xs font-medium text-ink-muted lg:text-left">
            Join Our Beauty Family — start glowing today.
        </p>

        <h1 class="font-display text-2xl font-bold tracking-tight text-primary">Create Account</h1>
        <p class="mt-1.5 text-sm text-ink-muted">
            Create your customer account and start booking.
        </p>

        <ErrorSummary />

        <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormInput v-model="form.values.first_name" name="first_name" label="First Name" placeholder="Juan" required />
                <FormInput v-model="form.values.last_name" name="last_name" label="Last Name" placeholder="Dela Cruz" required />
            </div>

            <FormInput v-model="form.values.email" name="email" type="email" label="Email" placeholder="you@example.com" required />

            <FormInput v-model="form.values.contact_number" name="contact_number" label="Contact Number" placeholder="09XX XXX XXXX" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <FormPassword v-model="form.values.password" name="password" label="Password" required autocomplete="new-password" hint="Minimum of 8 characters." />
                <FormPassword v-model="form.values.password_confirmation" name="password_confirmation" label="Confirm Password" required autocomplete="new-password" />
            </div>

            <FormCheckbox v-model="form.values.terms" name="terms" required hint="I agree to the Terms and Conditions and Cancellation Policy.">
                <router-link
                    :to="{ name: 'terms.show', params: { category: 'booking' } }"
                    class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark"
                >Terms and Conditions</router-link>
            </FormCheckbox>

            <button type="submit" class="btn-primary w-full" :disabled="submitting">
                Create Account
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-muted">
            Already have an account?
            <router-link :to="{ name: 'login' }" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Log in</router-link>
        </p>
    </div>
</template>
