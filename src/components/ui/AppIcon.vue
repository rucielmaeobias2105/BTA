<script setup>
import { computed } from 'vue';
import { ICONS } from '@/lib/icons';

/**
 * Renders a Heroicons outline path by name.
 *
 * Replaces `<x-dynamic-component :component="$link['icon']" />` from the admin
 * sidebar, which resolved `blade-ui-kit/blade-heroicons` names at render time.
 */
const props = defineProps({
    name: { type: String, required: true },
});

const paths = computed(() => ICONS[props.name] ?? []);
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
        aria-hidden="true"
    >
        <path
            v-for="(d, index) in paths"
            :key="index"
            stroke-linecap="round"
            stroke-linejoin="round"
            :d="d"
        />
    </svg>
</template>
