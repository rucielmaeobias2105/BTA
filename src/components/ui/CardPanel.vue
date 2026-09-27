<script setup>
/**
 * Bordered surface with an optional header and footer.
 *
 * Ported from `components/ui/card.blade.php`. The Blade version took an
 * `actions` slot in the header and a `footer` slot; both are ordinary named
 * slots here.
 */
defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    padded: { type: Boolean, default: true },
    accent: { type: String, default: 'gold' },
});
</script>

<template>
    <section
        class="rounded-card bg-cream shadow-card"
        :class="accent === 'maroon' ? 'border border-primary/20' : 'border border-gold/25'"
    >
        <header v-if="title || subtitle || $slots.actions" class="flex flex-wrap items-start justify-between gap-3 border-b border-primary/10 px-5 py-4">
            <div class="min-w-0">
                <h3 v-if="title" class="font-display text-lg font-semibold text-primary">{{ title }}</h3>
                <p v-if="subtitle" class="mt-0.5 text-sm text-ink-muted">{{ subtitle }}</p>
            </div>

            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </header>

        <div :class="padded ? 'p-5' : ''">
            <slot />
        </div>

        <footer v-if="$slots.footer" class="border-t border-primary/10 bg-linen/40 px-5 py-3.5 text-sm">
            <slot name="footer" />
        </footer>
    </section>
</template>
