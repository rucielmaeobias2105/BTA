<script setup>
import { computed, inject } from 'vue';

/**
 * Labelled text input with hint and error text.
 *
 * Ported from `components/ui/form/input.blade.php`. The error is read from the
 * form's `errors` bag — provided by the page, or passed explicitly — which is
 * what replaced the `$errors->has($name)` lookup the Blade version did inline.
 */
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    name: { type: String, required: true },
    id: { type: String, default: null },
    label: { type: String, default: null },
    type: { type: String, default: 'text' },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    prefix: { type: String, default: null },
    error: { type: String, default: null },
    wrapperClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const injected = inject('form-errors', null);

const fieldId = computed(() => props.id ?? props.name);
const message = computed(() => props.error ?? injected?.[props.name] ?? null);

const describedBy = computed(() =>
    [props.hint ? `${fieldId.value}-hint` : null, message.value ? `${fieldId.value}-error` : null]
        .filter(Boolean)
        .join(' '),
);

function onInput(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
    <div :class="wrapperClass">
        <label v-if="label" :for="fieldId" class="label">
            {{ label }}
            <span v-if="required" class="text-status-cancelled">*</span>
        </label>

        <div class="relative">
            <span
                v-if="prefix || $slots.icon"
                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-ink-muted"
                :class="prefix ? 'text-sm' : ''"
            >
                <slot v-if="$slots.icon" name="icon" />
                <template v-else>{{ prefix }}</template>
            </span>

            <input
                :id="fieldId"
                :name="name"
                :type="type"
                :value="modelValue"
                :required="required"
                :aria-describedby="describedBy || undefined"
                :aria-invalid="message ? 'true' : undefined"
                class="input"
                :class="[message ? 'input-error' : '', prefix || $slots.icon ? 'pl-10' : '']"
                @input="onInput"
            >
        </div>

        <p v-if="hint" :id="`${fieldId}-hint`" class="input-hint">{{ hint }}</p>

        <p v-if="message" :id="`${fieldId}-error`" class="input-error-text">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
            {{ message }}
        </p>
    </div>
</template>
