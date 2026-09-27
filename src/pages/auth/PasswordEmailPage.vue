<script>
import { reactive } from 'vue';

/**
 * Stand-in for the `password_reset` array the reset controller kept in the PHP
 * session (`email`, `verified`, `record_id`). It lives here because the first
 * wizard step is where the flow starts, and the later steps read it on arrival.
 */
export const passwordReset = reactive({
    email: '',
    code: '',
    verified: false,
});
</script>

<script setup>
import { provide, ref } from 'vue';
import { useRouter } from 'vue-router';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import { findUserByEmail } from '@/data/users';
import { setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';

/** The four labels the shared `auth.passwords.steps` indicator walked through. */
const STEPS = [
    { number: 1, label: 'Email' },
    { number: 2, label: 'Verify Code' },
    { number: 3, label: 'New Password' },
    { number: 4, label: 'Confirm Password' },
];

const currentStep = 1;

/**
 * Customer Flow 3, step 1 of 4 — request the reset code.
 *
 * Ported from `auth/passwords/email.blade.php` and
 * `PasswordResetLinkController::store()`. The controller always advanced to the
 * code screen, whether or not the address was registered, so the form cannot be
 * used to discover which emails exist — that branch is reproduced here against
 * the demo users.
 *
 * `PasswordResetService::EXPIRY_MINUTES` was 15; step 2 states it.
 */
const router = useRouter();

const form = createForm({
    initial: { email: '' },
    rules: {
        email: ['required', 'string', 'email', 'max:255'],
    },
});

provide('form-errors', form.errors);

const submitting = ref(false);

function submit() {
    form.values.email = form.values.email.trim().toLowerCase();

    if (!form.validate()) return;

    submitting.value = true;

    passwordReset.email = form.values.email;
    passwordReset.code = '';
    passwordReset.verified = false;

    setFlash(findUserByEmail(passwordReset.email)
        ? `We sent a 6-digit verification code to ${passwordReset.email}.`
        : 'If that email is registered, a 6-digit verification code is on its way.');

    router.push({ name: 'password.code' });
}
</script>

<template>
    <div>
        <div class="mb-8">
            <ol class="flex items-center gap-2" aria-label="Password reset progress">
                <template v-for="(step, index) in STEPS" :key="step.number">
                    <li class="flex flex-1 flex-col items-center gap-1.5">
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-semibold transition"
                            :class="step.number <= currentStep ? 'bg-primary text-cream' : 'bg-linen text-ink-muted ring-1 ring-primary/15'"
                        >
                            <svg v-if="step.number < currentStep" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            <template v-else>{{ step.number }}</template>
                        </span>
                        <span
                            class="text-center text-[10px] font-medium leading-tight"
                            :class="step.number === currentStep ? 'text-primary' : 'text-ink-muted'"
                        >{{ step.label }}</span>
                    </li>

                    <span
                        v-if="index < STEPS.length - 1"
                        aria-hidden="true"
                        class="-mt-5 h-px flex-1"
                        :class="step.number < currentStep ? 'bg-primary' : 'bg-primary/15'"
                    />
                </template>
            </ol>
        </div>

        <p class="mb-5 text-center text-xs font-medium text-ink-muted lg:text-left">
            Account Recovery — back to glowing in a moment.
        </p>

        <div class="mb-6 text-center lg:text-left">
            <h1 class="font-display text-2xl font-bold tracking-tight text-primary">Forgot Password</h1>
            <p class="mt-2 text-sm text-ink-muted">Enter your email and we'll send you a 6-digit verification code.</p>
        </div>

        <ErrorSummary />

        <form class="space-y-4" novalidate @submit.prevent="submit">
            <FormInput
                v-model="form.values.email"
                name="email"
                type="email"
                label="Email Address"
                placeholder="you@example.com"
                required
            />

            <button type="submit" class="btn-primary w-full" :disabled="submitting">
                Next Step
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-ink-muted">
            <router-link :to="{ name: 'login' }" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Back to log in</router-link>
        </p>
    </div>
</template>
