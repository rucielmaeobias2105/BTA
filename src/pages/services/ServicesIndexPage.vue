<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ServiceCard from '@/components/ui/ServiceCard.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import { activeServices } from '@/data/services';
import { reviews } from '@/data/appointments';
import { serviceCategories } from '@/data/services';
import { formatNumber, plural } from '@/lib/format';

/**
 * Service catalogue with filters.
 *
 * Ported from `services/index.blade.php` and the pipeline in
 * `App\Http\Controllers\ServiceController::filtered()`. The filter inputs and the
 * "Reset Filters" action were still commented out in the Blade template, but
 * the controller, the empty-state copy ("Try widening your price range") and
 * the README all describe them — so the filter row is rebuilt here, driven by
 * the query string the same way `$request->only([...])` was.
 */
const route = useRoute();
const router = useRouter();

/** Typing in the search box waits a beat so every keystroke is not a history entry. */
const SEARCH_DEBOUNCE_MS = 250;

const form = ref({ search: '', category: '', min_price: '', max_price: '' });

const categories = computed(() => [
    { value: '', label: 'All categories' },
    ...serviceCategories.value.map((name) => ({ value: name, label: name })),
]);

/** `priceBounds()` — the range the price inputs span. */
const bounds = computed(() => {
    const prices = activeServices.value.map((service) => Number(service.price));

    return {
        min: prices.length ? Math.min(...prices) : 0,
        max: prices.length ? Math.max(...prices) : 0,
    };
});

/**
 * The same predicate the Eloquent scopes chained: category, then price band,
 * then free-text search across name / description / category.
 */
const results = computed(() => {
    const term = String(route.query.search ?? '').trim().toLowerCase();
    const category = String(route.query.category ?? '');
    const min = route.query.min_price ? Number(route.query.min_price) : null;
    const max = route.query.max_price ? Number(route.query.max_price) : null;

    return activeServices.value
        .filter((service) => {
            if (category && service.category !== category) return false;
            if (min !== null && Number(service.price) < min) return false;
            if (max !== null && Number(service.price) > max) return false;

            if (term) {
                const haystack = [service.name, service.description, service.category];

                if (!haystack.some((field) => String(field ?? '').toLowerCase().includes(term))) return false;
            }

            return true;
        })
        .sort((a, b) => a.category.localeCompare(b.category) || a.name.localeCompare(b.name));
});

const countLabel = computed(() => plural(results.value.length, 'service'));

function pushQuery() {
    const query = {};

    Object.entries(form.value).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) query[key] = value;
    });

    router.replace({ name: 'services.index', query });
}

let timer = null;

function onSearchInput() {
    window.clearTimeout(timer);

    timer = window.setTimeout(pushQuery, SEARCH_DEBOUNCE_MS);
}

// The query string is the source of truth, so a shared link or a back
// navigation re-fills the inputs.
watch(
    () => route.query,
    (query) => {
        form.value = {
            search: query.search ?? '',
            category: query.category ?? '',
            min_price: query.min_price ?? '',
            max_price: query.max_price ?? '',
        };
    },
    { immediate: true, deep: true },
);

function reset() {
    form.value = { search: '', category: '', min_price: '', max_price: '' };
    router.replace({ name: 'services.index' });
}
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <PageHeader
            title="Browse Services"
            description="Explore the full menu of treatments, packages and add-ons we offer."
        >
            <template #actions>
                <router-link :to="{ name: 'services.refined' }" class="btn-secondary btn-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                    </svg>
                    Refined Grid
                </router-link>
            </template>
        </PageHeader>

        <div class="bta-card mb-7 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
            <FormInput
                v-model="form.search"
                name="search"
                placeholder="Search services…"
                wrapper-class="lg:col-span-2"
                @update:model-value="onSearchInput"
            >
                <template #icon>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </template>
            </FormInput>

            <FormSelect v-model="form.category" name="category" :options="categories" @update:model-value="pushQuery" />

            <div class="grid grid-cols-2 gap-3">
                <FormInput
                    v-model="form.min_price"
                    name="min_price"
                    type="number"
                    inputmode="numeric"
                    prefix="₱"
                    placeholder="Min"
                    @update:model-value="pushQuery"
                />
                <FormInput
                    v-model="form.max_price"
                    name="max_price"
                    type="number"
                    inputmode="numeric"
                    prefix="₱"
                    placeholder="Max"
                    @update:model-value="pushQuery"
                />
            </div>
        </div>

        <p class="mb-5 text-sm text-ink-muted">
            Showing <span class="font-semibold text-primary">{{ results.length }}</span> {{ countLabel }}
            <span class="text-ink-muted/70">
                (from ₱{{ formatNumber(bounds.min) }} to ₱{{ formatNumber(bounds.max) }})
            </span>
        </p>

        <EmptyState
            v-if="results.length === 0"
            title="No services match your filters"
            description="Try widening your price range or choosing a different category."
        >
            <button type="button" class="btn-secondary" @click="reset">Reset Filters</button>
        </EmptyState>

        <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <ServiceCard
                v-for="service in results"
                :key="service.id"
                :service="service"
                :reviews="reviews"
            />
        </div>
    </div>
</template>
