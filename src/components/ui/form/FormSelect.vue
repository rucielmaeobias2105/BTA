<script setup>
import { computed, inject } from 'vue';

/**
 * Labelled select.
 *
 * Ported from `components/ui/form/select.blade.php`, which took a
 * `value => label` map. Objects of that shape are still accepted directly, so
 * call sites that passed `AppointmentStatus::options()` work unchanged; plain
 * arrays of `{ value, label }` work too.
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    name: { type: String, required: true },
    id: { type: String, default: null },
    label: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: null },
    options: { type: [Array, Object], default: () => ({}) },
    includeBlank: { type: Boolean, default: false },
    blankLabel: { type: String, default: 'Select an option' },
    error: { type: String, default: null },
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

/** Normalises both accepted option shapes into `{ value, label }` rows. */
const rows = computed(() =>
    Array.isArray(props.options)
        ? props.options
        : Object.entries(props.options).map(([value, label]) => ({ value, label })),
);

function onChange(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
    <div>
        <label v-if="label" :for="fieldId" class="label">
            {{ label }}
            <span v-if="required" class="text-status-cancelled">*</span>
        </label>

        <select
            :id="fieldId"
            :name="name"
            :required="required"
            :aria-describedby="describedBy || undefined"
            :aria-invalid="message ? 'true' : undefined"
            class="input pr-9"
            :class="message ? 'input-error' : ''"
            :value="modelValue"
            @change="onChange"
        >
            <option v-if="includeBlank" value="">{{ blankLabel }}</option>
            <option
                v-for="option in rows"
                :key="option.value"
                :value="option.value"
                :selected="String(modelValue) === String(option.value)"
            >
                {{ option.label }}
            </option>
        </select>

        <p v-if="hint" :id="`${fieldId}-hint`" class="input-hint">{{ hint }}</p>

        <p v-if="message" :id="`${fieldId}-error`" class="input-error-text">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
            {{ message }}
        </p>
    </div>
</template>
