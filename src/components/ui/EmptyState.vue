<script setup>
/**
 * Empty-state placeholder.
 *
 * Ported from `components/ui/empty.blade.php`.
 */
defineProps({
    title: { type: String, default: 'Nothing here yet' },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-card border border-dashed border-primary/20 bg-cream/60 px-6 py-14 text-center">
        <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-linen text-gold-dark">
            <slot name="icon">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5m16 0h-3.5a2.5 2.5 0 0 1-5 0H4" />
                </svg>
            </slot>
        </span>

        <p class="font-display text-lg font-semibold text-primary">{{ title }}</p>

        <p v-if="description" class="mt-1 max-w-sm text-sm text-ink-muted">{{ description }}</p>

        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
