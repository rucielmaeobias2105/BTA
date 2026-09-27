<script setup>
import { computed } from 'vue';

/**
 * Status / tag pill.
 *
 * Ported from `components/ui/badge.blade.php`. One component covered both
 * appointment statuses and inventory tags, so the `status` prop is mapped to the
 * same badge modifier the Blade `$map` used.
 */
const props = defineProps({
    status: { type: String, default: 'pending' },
    label: { type: String, default: null },
    dot: { type: Boolean, default: true },
});

const MODIFIERS = {
    // Appointment statuses
    pending: 'badge-pending',
    confirmed: 'badge-confirmed',
    in_progress: 'badge-progress',
    progress: 'badge-progress',
    completed: 'badge-completed',
    cancelled: 'badge-cancelled',
    // Item / service tags
    low_stock: 'badge-lowstock',
    best_seller: 'badge-bestseller',
    sold_out: 'badge-soldout',
    available: 'badge-confirmed',
    gold: 'badge-gold',
};

const LABELS = {
    in_progress: 'In Progress',
    low_stock: 'Low Stock',
    best_seller: 'Best Seller',
    sold_out: 'Sold Out',
};

const modifier = computed(() => MODIFIERS[props.status] ?? 'badge-gold');

const text = computed(
    () => props.label ?? LABELS[props.status] ?? props.status.replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase()),
);
</script>

<template>
    <span class="badge" :class="modifier">
        <span v-if="dot" class="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
        {{ text }}
    </span>
</template>
