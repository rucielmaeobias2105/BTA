<script setup>
import { computed, inject } from 'vue';
import Alert from './Alert.vue';

/**
 * Validation error summary.
 *
 * Ported from `components/ui/errors.blade.php`. Renders nothing at all when
 * there are no errors, so pages can drop it unconditionally instead of
 * guarding with `v-if`. Pass `fields` to narrow the list to `$errors->only()`.
 */
const props = defineProps({
    errors: { type: Object, default: null },
    fields: { type: Array, default: null },
    title: { type: String, default: 'Please fix the following:' },
});

const injected = inject('form-errors', null);

const bag = computed(() => props.errors ?? injected ?? {});

const messages = computed(() => {
    if (props.fields) return props.fields.map((field) => bag.value[field]).filter(Boolean);

    return Object.values(bag.value);
});
</script>

<template>
    <Alert v-if="messages.length" type="error" :dismissible="false">
        <p v-if="title" class="mb-1 font-semibold">{{ title }}</p>

        <ul class="list-inside list-disc space-y-0.5">
            <li v-for="message in messages" :key="message">{{ message }}</li>
        </ul>
    </Alert>
</template>
