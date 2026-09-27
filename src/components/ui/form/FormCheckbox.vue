<script setup>
import { computed, inject } from 'vue';

/**
 * Labelled checkbox.
 *
 * Ported from `components/ui/form/checkbox.blade.php`. The label accepted
 * trailing inline content (a link to the T&C text, for instance), which is what
 * the default slot still carries.
 */
const props = defineProps({
    modelValue: { type: [Boolean, Number, String], default: false },
    name: { type: String, required: true },
    id: { type: String, default: null },
    label: { type: String, default: null },
    value: { type: [String, Number], default: 1 },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    error: { type: String, default: null },
    wrapperClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const injected = inject('form-errors', null);

const fieldId = computed(() => props.id ?? props.name);
const message = computed(() => props.error ?? injected?.[props.name] ?? null);

function onChange(event) {
    emit('update:modelValue', event.target.checked);
}
</script>

<template>
    <div :class="wrapperClass">
        <div class="flex items-start gap-2.5">
            <input
                :id="fieldId"
                :name="name"
                type="checkbox"
                :value="value"
                :checked="Boolean(modelValue)"
                :required="required"
                :aria-invalid="message ? 'true' : undefined"
                class="checkbox mt-0.5"
                :class="message ? 'ring-2 ring-status-cancelled/40' : ''"
                @change="onChange"
            >

            <div class="min-w-0">
                <label
                    v-if="label || $slots.default"
                    :for="fieldId"
                    class="cursor-pointer select-none text-sm text-ink"
                >
                    {{ label }}
                    <span v-if="required" class="text-status-cancelled">*</span>
                    <slot />
                </label>

                <p v-if="hint" class="mt-0.5 text-xs text-ink-muted">{{ hint }}</p>
            </div>
        </div>

        <p v-if="message" class="input-error-text">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
            {{ message }}
        </p>
    </div>
</template>
