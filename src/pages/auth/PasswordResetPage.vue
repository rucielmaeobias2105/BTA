<script setup>
import { provide, ref } from 'vue';
import { useRouter } from 'vue-router';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormPassword from '@/components/ui/form/FormPassword.vue';
import { setFlash, signInCustomer } from '@/lib/session';
import { createForm } from '@/lib/validation';
import { passwordReset } from './PasswordEmailPage.vue';

/** The four labels the shared `auth.passwords.steps` indicator walked through. */
const STEPS = [
    { number: 1, label: 'Email' },
    { number: 2, label: 'Verify Code' },
    { number: 3, label: 'New Password' },
    { number: 4, label: 'Confirm Password' },
];

/** The reset page covers steps 3 and 4 — the new password and its confirmation. */
const currentStep = 3;

/**
 * Customer Flow 3, steps 3 & 4 of 4 — choose the new password.
 *
 * Ported from `auth/passwords/reset.blade.php` and
 * `PasswordResetLinkController::update()`. The controller hashed the new value,
 * cleared the `password_reset` session entry, signed the customer straight in
 * with `Auth::login()` and flashed the confirmation, so all that is left here
 * is the session hand-off.
 */
const router = useRouter();

const form = createForm({
    initial: { password: '', password_confirmation: '' },
    rules: {
        password: ['required', 'string', 'min:8', 'confirmed'],
    },
    messages: {
        confirmed: 'The password confirmation does not match.',
    },
});

provide('form-errors', form.errors);

const submitting = ref(false);

function submit() {
    if (!form.validate()) return;

    submitting.value = true;

    passwordReset.email = '';
    passwordReset.code = '';
    passwordReset.verified = false;

    signInCustomer();

    setFlash('Your password has been reset successfully.');
    router.push({ name: 'dashboard' });
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
            Choose a Stronger Secret — then you are back in.
        </p>

        <div class="mb-6 text-center lg:text-left">
            <h1 class="font-display text-2xl font-bold tracking-tight text-primary">New Password</h1>
            <p class="mt-2 text-sm text-ink-muted">Choose a strong password you haven't used before.</p>
        </div>

        <ErrorSummary />

        <form class="space-y-4" novalidate @submit.prevent="submit">
            <FormPassword
                v-model="form.values.password"
                name="password"
                label="New Password"
                required
                autocomplete="new-password"
                hint="Minimum of 8 characters."
            />

            <FormPassword
                v-model="form.values.password_confirmation"
                name="password_confirmation"
                label="Confirm New Password"
                required
                autocomplete="new-password"
            />

            <button type="submit" class="btn-primary w-full" :disabled="submitting">
                Reset Password
            </button>
        </form>
    </div>
</template>
