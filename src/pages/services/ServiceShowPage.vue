<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import StarRating from '@/components/ui/StarRating.vue';
import ServiceCard from '@/components/ui/ServiceCard.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import { activeServices, durationLabel, effectiveDuration, findService, inventoryUsage } from '@/data/services';
import { inventoryItems } from '@/data/inventory';
import { ItemTag } from '@/data/enums';
import { reviews } from '@/data/appointments';
import { formatDate, formatMoney } from '@/lib/format';

/**
 * Service detail.
 *
 * Ported from `services/show.blade.php` and `ServiceController::show()`. The
 * route key was the slug (`getRouteKeyName()`), which `findService()` resolves.
 */
const route = useRoute();

const service = computed(() => findService(route.params.slug));

const serviceReviews = computed(() => {
    if (!service.value) return [];

    return reviews.value
        .filter((review) => review.service_id === service.value.id)
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 6);
});

/** Same category, excluding this service, capped at four. */
const related = computed(() => {
    if (!service.value) return [];

    return activeServices.value
        .filter((item) => item.category === service.value.category && item.id !== service.value.id)
        .slice(0, 4);
});

/** `isUnavailable()` — true when every linked item is sold out. */
const unavailable = computed(() => {
    if (!service.value) return false;

    const linked = inventoryUsage(service.value)
        .map(({ sku }) => inventoryItems.find((item) => item.sku === sku))
        .filter(Boolean);

    return linked.length > 0 && linked.every((item) => item.status_tag === ItemTag.SoldOut.value || item.quantity <= 0);
});
</script>

<template>
    <NotFoundPage v-if="!service" />

    <div v-else class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <nav class="mb-6 flex items-center gap-2 text-sm text-ink-muted" aria-label="Breadcrumb">
            <router-link :to="{ name: 'home' }" class="transition hover:text-primary">Home</router-link>
            <span aria-hidden="true">/</span>
            <router-link :to="{ name: 'services.index' }" class="transition hover:text-primary">Services</router-link>
            <span aria-hidden="true">/</span>
            <router-link
                :to="{ name: 'services.index', query: { category: service.category } }"
                class="transition hover:text-primary"
            >{{ service.category }}</router-link>
            <span aria-hidden="true">/</span>
            <span class="truncate font-medium text-primary">{{ service.name }}</span>
        </nav>

        <div class="grid gap-8 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <div class="overflow-hidden rounded-card border border-gold/30 bg-cream shadow-panel">
                    <img
                        v-if="service.photo_path"
                        :src="service.photo_path"
                        :alt="service.name"
                        class="aspect-[4/3] w-full object-cover"
                    >
                    <div v-else class="flex aspect-[4/3] w-full items-center justify-center bg-gradient-to-br from-linen to-gold-light/40">
                        <svg class="h-20 w-20 text-gold-dark/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9.75 6.75 4.5 12l5.25 5.25M14.25 6.75 19.5 12l-5.25 5.25" />
                        </svg>
                    </div>
                </div>

                <div v-if="serviceReviews.length" class="mt-8">
                    <h2 class="font-display text-xl font-semibold text-primary">What Our Clients Say</h2>

                    <div class="mt-4 space-y-4">
                        <article v-for="review in serviceReviews" :key="review.id" class="bta-card p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-primary">{{ review.customer_name }}</p>
                                    <p class="text-xs text-ink-muted">{{ formatDate(review.created_at, 'M j, Y') }}</p>
                                </div>
                                <StarRating :model-value="review.rating" :interactive="false" size="sm" />
                            </div>
                            <p class="mt-3 text-sm leading-relaxed text-ink">{{ review.message }}</p>
                        </article>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bta-card p-6 lg:sticky lg:top-28">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-gold">{{ service.category }}</span>
                        <Badge v-if="service.is_featured" status="best_seller" label="Best Seller" />
                        <Badge v-if="unavailable" status="sold_out" label="Sold Out" />
                    </div>

                    <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-primary">{{ service.name }}</h1>

                    <div class="mt-4 flex items-end gap-4">
                        <p class="font-display text-3xl font-semibold text-primary">{{ formatMoney(service.price) }}</p>
                        <p class="pb-1.5 text-sm text-ink-muted">{{ durationLabel(service.duration_minutes) }}</p>
                    </div>

                    <p v-if="service.description" class="mt-4 text-sm leading-relaxed text-ink-muted">
                        {{ service.description }}
                    </p>

                    <div v-if="service.variants.length" class="mt-6 border-t border-primary/10 pt-5">
                        <h2 class="text-sm font-semibold text-primary">Choose a Variant</h2>

                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="variant in service.variants"
                                :key="variant.id"
                                class="flex items-center justify-between gap-3 rounded-xl border border-primary/10 bg-linen/50 px-4 py-3"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-ink">{{ variant.name }}</p>
                                    <p class="text-xs text-ink-muted">
                                        {{ effectiveDuration(service, variant) }} min
                                        <template v-if="variant.is_default">
                                            &middot; <span class="text-gold-dark">Default</span>
                                        </template>
                                    </p>
                                </div>
                                <p class="shrink-0 text-sm font-semibold text-primary">{{ formatMoney(variant.price) }}</p>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-6 space-y-2.5 border-t border-primary/10 pt-5">
                        <router-link
                            :to="{ name: 'appointments.create', query: { services: service.slug } }"
                            class="btn-primary btn-lg w-full"
                        >
                            Book Now
                        </router-link>
                        <router-link
                            :to="{ name: 'services.index', query: { category: service.category } }"
                            class="btn-ghost w-full"
                        >
                            More {{ service.category }} services
                        </router-link>
                    </div>
                </div>
            </div>
        </div>

        <section v-if="related.length" class="mt-16">
            <h2 class="font-display text-2xl font-bold tracking-tight text-primary">You May Also Like</h2>

            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <ServiceCard
                    v-for="item in related"
                    :key="item.id"
                    :service="item"
                    :show-rating="false"
                    :reviews="reviews"
                />
            </div>
        </section>
    </div>
</template>
