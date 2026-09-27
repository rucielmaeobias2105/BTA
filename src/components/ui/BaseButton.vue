<script setup>
import { computed } from 'vue';

/**
 * Primary action button.
 *
 * Ported from `components/ui/button.blade.php`, which rendered either an
 * `<a>` (when `href` was set) or a `<button>`. Vue Router gives the link case
 * client-side navigation for free.
 */
const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    href: { type: String, default: null },
    to: { type: [String, Object], default: null },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
});

const VARIANTS = ['primary', 'secondary', 'ghost', 'danger'];

const classes = computed(() => {
    const variant = VARIANTS.includes(props.variant) ? props.variant : 'gold';
    const size = props.size === 'sm' ? ' btn-sm' : props.size === 'lg' ? ' btn-lg' : '';

    return `btn-${variant}${size}`;
});
</script>

<template>
    <router-link v-if="to" :to="to" :class="classes">
        <slot />
    </router-link>

    <a v-else-if="href" :href="href" :class="classes">
        <slot />
    </a>

    <button v-else :type="type" :disabled="disabled" :class="classes">
        <slot />
    </button>
</template>
