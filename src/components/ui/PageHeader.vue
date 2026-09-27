<script setup>
/**
 * Page title block with an optional eyebrow, description and action row.
 *
 * Ported from `components/ui/page-header.blade.php`.
 */
defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    eyebrow: { type: String, default: null },
});
</script>

<template>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p v-if="eyebrow" class="mb-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-gold-dark">
                {{ eyebrow }}
            </p>

            <h1 v-if="title" class="font-display text-2xl font-bold tracking-tight text-primary sm:text-3xl">
                {{ title }}
            </h1>

            <p v-if="description" class="mt-1.5 max-w-2xl text-sm text-ink-muted">{{ description }}</p>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
