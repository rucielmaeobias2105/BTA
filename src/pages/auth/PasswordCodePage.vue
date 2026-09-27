<script setup>
import { provide, ref } from 'vue';
import { useRouter } from 'vue-router';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import { findUserByEmail } from '@/data/users';
import { setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';
import { passwordReset } from './PasswordEmailPage.vue';

/** The four labels the shared `auth.passwords.steps` indicator walked through. */
const STEPS = [
    { number: 1, label: 'Email' },
    { number: 2, label: 'Verify Code' },
    { number: 3, label: 'New Password' },
    { number: 4, label: 'Confirm Password' },
];

const currentStep = 2;

/** `PasswordResetService::EXPIRY_MINUTES`. */
const EXPIRY_MINUTES = 15;

/**
 * Customer Flow 3, step 2 of 4 — verify the emailed code.
 *
 * Ported from `auth/passwords/code.blade.php` and
 * `PasswordResetLinkController::verifyCode()`. There is no notification service
 * and no code store left, so `digits:6` is the whole check: any six digits
 * verify, which is what the demo's `signInCustomer()` sign-off assumes.
 *
 * The field is a bare `<input>` rather than `FormInput` because the Blade passed
 * `text-center font-display text-2xl tracking-[0.5em]` down onto the control
 * itself, and that component deliberately owns its own class list.
 */
const router = useRouter();

const form = createForm({
    initial: { code: '' },
    rules: {
        code: ['required', 'string', 'min:6', 'max:6', 'regex:/^[0-9]{6}$/'],
    },
    messages: {
        regex: 'The code must be 6 digits.',
    },
});

provide('form-errors', form.errors);

const submitting = ref(false);

/** The Blade stripped anything that was not a digit and capped it at six. */
function onCodeInput(event) {
    form.values.code = event.target.value.replace(/\D/g, '').slice(0, 6);
}

function submit() {
    if (!form.validate()) return;

    submitting.value = true;

    passwordReset.code = form.values.code;
    passwordReset.verified = true;

    router.push({ name: 'password.reset' });
}

/** The "Resend code" form re-posted the stored address to `password.email`. */
function resend() {
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
            Confirm It Is You — a quick code, then you are in.
        </p>

        <div class="mb-6 text-center lg:text-left">
            <h1 class="font-display text-2xl font-bold tracking-tight text-primary">Verify Email</h1>
            <p class="mt-2 text-sm text-ink-muted">
                We sent a 6-digit code to <span class="font-medium text-primary">{{ passwordReset.email }}</span>.
                It expires in {{ EXPIRY_MINUTES }} minutes.
            </p>
        </div>

        <ErrorSummary />

        <form class="space-y-4" novalidate @submit.prevent="submit">
            <div>
                <label for="code" class="label">
                    Verification Code
                    <span class="text-status-cancelled">*</span>
                </label>

                <input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    autocomplete="one-time-code"
                    placeholder="000000"
                    required
                    :value="form.values.code"
                    :aria-invalid="form.errors.code ? 'true' : undefined"
                    :aria-describedby="form.errors.code ? 'code-error' : undefined"
                    class="input text-center font-display text-2xl tracking-[0.5em]"
                    :class="form.errors.code ? 'input-error' : ''"
                    @input="onCodeInput"
                >

                <p v-if="form.errors.code" id="code-error" class="input-error-text">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                    {{ form.errors.code }}
                </p>
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="submitting">
                Verify
            </button>
        </form>

        <div class="mt-6 flex flex-col items-center justify-between gap-2 text-sm">
            <router-link :to="{ name: 'password.request' }" class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Change email address</router-link>
            <button type="button" class="toggle-link" @click="resend">Resend code</button>
        </div>
    </div>
</template>
