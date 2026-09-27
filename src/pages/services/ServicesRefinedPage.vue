<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ServiceCard from '@/components/ui/ServiceCard.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import { activeServices, serviceCategories } from '@/data/services';
import { reviews } from '@/data/appointments';

/**
 * Category-first service grid.
 *
 * Ported from `services/refined.blade.php` and `ServiceController::refined()`.
 * The tab bar writes `?category=`; an unknown category falls back to "all",
 * exactly as the controller's `in_array` guard did.
 */
const route = useRoute();
const router = useRouter();

const categories = serviceCategories;

const activeCategory = computed(() => {
    const requested = String(route.query.category ?? '');

    return requested && categories.value.includes(requested) ? requested : 'all';
});

const search = computed(() => String(route.query.search ?? ''));

const localSearch = ref(search.value);

const results = computed(() => {
    const term = search.value.trim().toLowerCase();

    return activeServices.value
        .filter((service) => {
            if (activeCategory.value !== 'all' && service.category !== activeCategory.value) return false;

            if (term) {
                const haystack = [service.name, service.description, service.category];

                if (!haystack.some((field) => String(field ?? '').toLowerCase().includes(term))) return false;
            }

            return true;
        })
        // Featured first, then alphabetical — the `orderBy` chain in the query.
        .sort((a, b) => Number(b.is_featured) - Number(a.is_featured) || a.name.localeCompare(b.name));
});

watch(search, (value) => {
    localSearch.value = value;
});

function tabQuery(category) {
    return { name: 'services.refined', query: { ...(search.value ? { search: search.value } : {}), ...(category === 'all' ? {} : { category }) } };
}

function submitSearch() {
    router.replace({
        name: 'services.refined',
        query: {
            ...(localSearch.value ? { search: localSearch.value } : {}),
            ...(activeCategory.value === 'all' ? {} : { category: activeCategory.value }),
        },
    });
}

const searchLabel = computed(() =>
    activeCategory.value === 'all' ? 'Search within all services' : `Search within ${activeCategory.value}`,
);
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <PageHeader
            eyebrow="Refined Grid View"
            title="Browse by Category"
            description="A cleaner, category-first view of our menu. Tap a category to narrow the grid."
        />

        <div class="mb-7 flex flex-wrap items-center gap-2">
            <router-link
                :to="tabQuery('all')"
                class="rounded-pill px-4 py-2 text-sm font-medium transition"
                :class="activeCategory === 'all' ? 'bg-primary text-cream shadow-card' : 'bg-cream text-ink hover:bg-linen'"
            >
                All Services
            </router-link>

            <router-link
                v-for="category in categories"
                :key="category"
                :to="tabQuery(category)"
                class="rounded-pill px-4 py-2 text-sm font-medium transition"
                :class="activeCategory === category ? 'bg-primary text-cream shadow-card' : 'bg-cream text-ink hover:bg-linen'"
            >
                {{ category }}
            </router-link>
        </div>

        <form class="mb-8 max-w-md" @submit.prevent="submitSearch">
            <FormInput v-model="localSearch" name="search" :label="searchLabel" placeholder="Search services…">
                <template #icon>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </template>
            </FormInput>
        </form>

        <EmptyState
            v-if="results.length === 0"
            title="No services in this category yet"
            description="Try another category, or view the complete menu."
        >
            <router-link :to="{ name: 'services.refined', query: { category: 'all' } }" class="btn-secondary">
                View All Services
            </router-link>
        </EmptyState>

        <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <ServiceCard
                v-for="service in results"
                :key="service.id"
                :service="service"
                :reviews="reviews"
            />
        </div>
    </div>
</template>
