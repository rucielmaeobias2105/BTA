<script setup>
import { computed, inject, ref } from 'vue';

/**
 * Password field with a Show/Hide text link.
 *
 * Ported from `components/ui/form/password.blade.php`. The toggle used to be
 * wired up by the `initPasswordToggles()` helper in `resources/js/app.js`,
 * scanning for `[data-password-toggle-for]`; with the field owning its own
 * state that helper is no longer needed.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    name: { type: String, required: true },
    id: { type: String, default: null },
    label: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: null },
    autocomplete: { type: String, default: 'current-password' },
    toggleLabel: { type: String, default: 'Show' },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const injected = inject('form-errors', null);

const fieldId = computed(() => props.id ?? props.name);
const message = computed(() => props.error ?? injected?.[props.name] ?? null);

const revealed = ref(false);

function toggle() {
    revealed.value = !revealed.value;
}

function onInput(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
    <div>
        <label v-if="label" :for="fieldId" class="label">
            {{ label }}
            <span v-if="required" class="text-status-cancelled">*</span>
        </label>

        <div class="relative">
            <input
                :id="fieldId"
                :name="name"
                :type="revealed ? 'text' : 'password'"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :required="required"
                :aria-invalid="message ? 'true' : undefined"
                class="input pr-16"
                :class="message ? 'input-error' : ''"
                :value="modelValue"
                @input="onInput"
            >

            <button
                type="button"
                class="toggle-link absolute inset-y-0 right-3 my-auto h-fit"
                :aria-pressed="revealed ? 'true' : 'false'"
                @click="toggle"
            >
                {{ revealed ? 'Hide' : toggleLabel }}
            </button>
        </div>

        <p v-if="hint" class="input-hint">{{ hint }}</p>

        <p v-if="message" :id="`${fieldId}-error`" class="input-error-text">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
            {{ message }}
        </p>
    </div>
</template>
