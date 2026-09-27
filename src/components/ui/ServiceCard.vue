<script setup>
import { computed } from 'vue';
import Badge from './Badge.vue';
import StarRating from './StarRating.vue';
import { formatMoney } from '@/lib/format';
import { durationLabel, inventoryUsage } from '@/data/services';
import { inventoryItems } from '@/data/inventory';
import { ItemTag } from '@/data/enums';

/**
 * Catalogue tile.
 *
 * Ported from `components/ui/service-card.blade.php`. The Blade version read
 * `reviews_avg_rating` / `reviews_count` / `is_unavailable()` off the service;
 * those are derived here from the demo dataset instead.
 */
const props = defineProps({
    service: { type: Object, required: true },
    showRating: { type: Boolean, default: true },
    cta: { type: Boolean, default: true },
    reviews: { type: Array, default: () => [] },
});

const forThisService = computed(() => props.reviews.filter((review) => review.service_id === props.service.id));

const average = computed(() => {
    if (forThisService.value.length === 0) return 0;

    const total = forThisService.value.reduce((sum, review) => sum + review.rating, 0);

    return total / forThisService.value.length;
});

/** The cheapest variant, falling back to the base price. */
const cheapest = computed(() => {
    const prices = props.service.variants.map((variant) => Number(variant.price));

    return prices.length ? Math.min(...prices) : Number(props.service.price);
});

/** True when every linked item is sold out. */
const unavailable = computed(() => {
    const linked = inventoryUsage(props.service).map(({ sku }) =>
        inventoryItems.find((item) => item.sku === sku),
    ).filter(Boolean);

    return linked.length > 0 && linked.every((item) => item.status_tag === ItemTag.SoldOut.value || item.quantity <= 0);
});
</script>

<template>
    <article class="group flex flex-col overflow-hidden rounded-card border border-gold/25 bg-cream shadow-card transition duration-300 hover:-translate-y-0.5 hover:border-gold/50 hover:shadow-card-hover">
        <div class="relative aspect-[4/3] overflow-hidden bg-linen">
            <img
                v-if="service.photo_path"
                :src="service.photo_path"
                :alt="service.name"
                loading="lazy"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
            >
            <div v-else class="flex h-full w-full items-center justify-center bg-gradient-to-br from-linen to-gold-light/40">
                <svg class="h-12 w-12 text-gold-dark/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9.75 6.75 4.5 12l5.25 5.25M14.25 6.75 19.5 12l-5.25 5.25" />
                </svg>
            </div>

            <div class="absolute left-3 top-3 flex flex-wrap gap-1.5">
                <span class="badge badge-gold">{{ service.category }}</span>
                <Badge v-if="unavailable" status="sold_out" label="Sold Out" />
            </div>
        </div>

        <div class="flex flex-1 flex-col p-4">
            <h3 class="font-display text-base font-semibold leading-snug text-primary">
                {{ service.name }}
            </h3>

            <p v-if="service.description" class="mt-1.5 line-clamp-2 text-sm text-ink-muted">
                {{ service.description }}
            </p>

            <div v-if="showRating && forThisService.length > 0" class="mt-2.5 flex items-center gap-1.5">
                <StarRating :model-value="Math.round(average)" :interactive="false" size="sm" />
                <span class="text-xs text-ink-muted">({{ forThisService.length }})</span>
            </div>

            <div class="mt-auto pt-4">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="text-lg font-semibold text-primary">{{ formatMoney(cheapest) }}</p>
                        <p class="text-xs text-ink-muted">{{ durationLabel(service.duration_minutes) }}</p>
                    </div>

                    <router-link
                        v-if="cta"
                        :to="{ name: 'appointments.create', query: { services: service.slug } }"
                        class="btn-primary btn-sm shrink-0"
                    >
                        Book Now
                    </router-link>
                </div>
            </div>
        </div>
    </article>
</template>
