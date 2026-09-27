<script setup>
import { computed } from 'vue';

/**
 * Brand lockup.
 *
 * Ported from `components/brand/logo.blade.php`. Without an `image` it falls
 * back to the "BtA" monogram inside a gold-ringed maroon disc.
 */
const props = defineProps({
    nameLabel: { type: String, default: 'Balai ti Arjud' },
    tagline: { type: String, default: 'Glow & Co. Beauty Lounge' },
    size: { type: String, default: 'md' },
    showWordmark: { type: Boolean, default: true },
    href: { type: String, default: null },
    image: { type: String, default: null },
});

const SIZES = {
    sm: { mark: 'h-9 w-9 text-[8px]', name: 'text-base', tag: 'text-[9px]' },
    md: { mark: 'h-12 w-12 text-[9px]', name: 'text-xl', tag: 'text-[10px]' },
    lg: { mark: 'h-16 w-16 text-[11px]', name: 'text-2xl', tag: 'text-xs' },
};

const sizing = computed(() => SIZES[props.size] ?? SIZES.md);
</script>

<template>
    <component
        :is="href ? 'router-link' : 'span'"
        :to="href ?? undefined"
        class="group inline-flex items-center gap-3"
    >
        <img
            v-if="image"
            :src="image"
            :alt="nameLabel"
            class="shrink-0 rounded-full object-cover ring-2 ring-gold ring-offset-2 ring-offset-cream shadow-card"
            :class="sizing.mark"
        >
        <span
            v-else
            class="relative flex shrink-0 items-center justify-center rounded-full bg-primary text-cream ring-2 ring-gold ring-offset-2 ring-offset-cream shadow-card transition group-hover:ring-gold-light"
            :class="sizing.mark"
        >
            <span class="font-display font-semibold leading-none tracking-tight">Bt<span class="text-gold">A</span></span>
            <span class="absolute inset-[3px] rounded-full border border-gold/40" />
        </span>

        <span v-if="showWordmark" class="min-w-0 leading-tight">
            <span class="block font-display font-bold tracking-tight text-primary" :class="sizing.name">
                {{ nameLabel }}
            </span>
            <span class="block font-sans uppercase tracking-[0.18em] text-gold-dark" :class="sizing.tag">
                {{ tagline }}
            </span>
        </span>
    </component>
</template>
