<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Alert from '@/components/ui/Alert.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StarRating from '@/components/ui/StarRating.vue';
import { averageRating, findAppointment, reviews } from '@/data/appointments';
import { findService } from '@/data/services';
import { findUser } from '@/data/users';
import { AdminRole, roleCan } from '@/data/enums';
import { currentAdmin, setFlash } from '@/lib/session';
import { formatDate } from '@/lib/format';

/**
 * Admin Flow 11 — Review / Ratings Moderation.
 *
 * Ported from `admin/reviews/index.blade.php` and `ReviewModerationController`.
 * View and delete only; there is deliberately no create or edit. The GET filter
 * form became router query state, so the list re-derives from the same reactive
 * collection instead of a paginated SQL query.
 */
const route = useRoute();
const router = useRouter();

const role = computed(() => currentAdmin.value?.role ?? AdminRole.SuperAdmin.value);
const canManage = computed(() => roleCan(role.value, 'reviews.manage'));

const RATING_OPTIONS = {
    '': 'All Ratings',
    5: '5 stars',
    4: '4 stars',
    3: '3 stars',
    2: '2 stars',
    1: '1 star',
};

const filters = computed(() => ({
    search: String(route.query.search ?? ''),
    rating: String(route.query.rating ?? ''),
}));

/** The Blade form held its own copy until "Filter" was pressed; same here. */
const search = ref(filters.value.search);
const rating = ref(filters.value.rating);

watch(filters, (value) => {
    search.value = value.search;
    rating.value = value.rating;
});

const hasFilters = computed(() => Boolean(filters.value.search || filters.value.rating));

function applyFilters() {
    router.replace({
        name: 'admin.reviews.index',
        query: {
            ...(search.value ? { search: search.value } : {}),
            ...(rating.value ? { rating: rating.value } : {}),
        },
    });
}

const totalReviews = computed(() => reviews.value.length);

/** `Review::query()->selectRaw('rating, COUNT(*)')->groupBy('rating')` */
const distribution = computed(() =>
    reviews.value.reduce((counts, review) => {
        counts[review.rating] = (counts[review.rating] ?? 0) + 1;

        return counts;
    }, {}),
);

const lowRatedCount = computed(
    () => reviews.value.filter((review) => review.rating <= 2).length,
);

const stars = [5, 4, 3, 2, 1];

function share(count) {
    return totalReviews.value > 0 ? Math.round((count / totalReviews.value) * 100) : 0;
}

/** Newest first, as `->latest()` ordered the paginated query. */
const visible = computed(() => {
    const term = filters.value.search.trim().toLowerCase();
    // `in_array((int) $rating, [1, 2, 3, 4, 5], true)` — anything else is ignored.
    const wanted = [1, 2, 3, 4, 5].includes(Number(filters.value.rating))
        ? Number(filters.value.rating)
        : null;

    return [...reviews.value]
        .filter((review) => {
            if (wanted !== null && review.rating !== wanted) return false;

            if (!term) return true;

            return [review.message, review.customer_name, serviceName(review)]
                .some((field) => String(field ?? '').toLowerCase().includes(term));
        })
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

/** `$review->service_name` */
function serviceName(review) {
    return findService(review.service_id)?.name ?? null;
}

function appointmentOf(review) {
    return review.appointment_id ? findAppointment(review.appointment_id) : null;
}

function userOf(review) {
    return review.user_id ? findUser(review.user_id) : null;
}

function destroy(review) {
    if (!window.confirm('Delete this review? The customer will not be able to re-submit it.')) return;

    const appointment = appointmentOf(review);

    // Reviews are read straight off their appointment, so deleting one is
    // clearing that link — `reviews` re-derives from the same collection.
    if (appointment) appointment.review = null;

    setFlash('Review removed.');
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Feedback"
            title="Review Moderation"
            description="Read every customer review and delete anything inappropriate. There is no create or edit here by design."
        />

        <!-- Rating distribution -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bta-card flex items-center gap-4 p-5">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gold/20 text-gold-dark">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 0 0 .95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 0 0-.364 1.118l1.286 3.958c.3.921-.755 1.688-1.539 1.118l-3.367-2.446a1 1 0 0 0-1.175 0l-3.367 2.446c-.783.57-1.838-.197-1.538-1.118l1.286-3.958a1 1 0 0 0-.364-1.118L2.062 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 0 0 .95-.69l1.286-3.958Z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Average</p>
                    <p class="font-display text-2xl font-bold text-primary">{{ averageRating }} / 5</p>
                </div>
            </div>

            <div class="bta-card flex items-center gap-4 p-5">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 21h9m-9 0a1.5 1.5 0 0 1-1.5-1.5m1.5 1.5V21m-9-3.5h13.5A1.5 1.5 0 0 0 21 16V8.25a1.5 1.5 0 0 0-1.5-1.5H4.5A1.5 1.5 0 0 0 3 8.25V16a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Reviews</p>
                    <p class="font-display text-2xl font-bold text-primary">{{ totalReviews }}</p>
                </div>
            </div>

            <div class="bta-card p-5 lg:col-span-2">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-muted">Distribution</p>
                <div class="space-y-1.5">
                    <div v-for="star in stars" :key="star" class="flex items-center gap-2.5">
                        <span class="w-10 shrink-0 text-xs text-ink-muted">{{ star }} star{{ star === 1 ? '' : 's' }}</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-pill bg-linen">
                            <div class="h-full rounded-pill bg-gold" :style="{ width: `${share(distribution[star] ?? 0)}%` }" />
                        </div>
                        <span class="w-14 shrink-0 text-right text-xs text-ink-muted">
                            {{ distribution[star] ?? 0 }} ({{ share(distribution[star] ?? 0) }}%)
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <Alert v-if="lowRatedCount > 0" type="warning" class="mb-6">
            {{ lowRatedCount }} review{{ lowRatedCount === 1 ? '' : 's' }} rated 2 stars or below — worth reviewing.
        </Alert>

        <form class="bta-card mb-6 p-5" novalidate @submit.prevent="applyFilters">
            <div class="grid gap-4 md:grid-cols-12">
                <div class="md:col-span-7">
                    <FormInput v-model="search" name="search" label="Search" placeholder="Message, customer or service" />
                </div>
                <div class="md:col-span-3">
                    <FormSelect v-model="rating" name="rating" label="Rating" :options="RATING_OPTIONS" />
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="btn-primary flex-1">Filter</button>
                    <router-link v-if="hasFilters" :to="{ name: 'admin.reviews.index' }" class="btn-ghost">Clear</router-link>
                </div>
            </div>
        </form>

        <EmptyState
            v-if="visible.length === 0"
            title="No reviews found"
            description="Customer reviews will appear here once appointments are completed."
        />

        <div v-else class="space-y-4">
            <article v-for="review in visible" :key="review.id" class="bta-card p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <p class="font-semibold text-primary">{{ review.customer_name }}</p>
                            <StarRating :model-value="review.rating" :interactive="false" size="sm" />
                            <span class="text-xs text-ink-muted">{{ formatDate(review.created_at, 'M j, Y g:i A') }}</span>
                        </div>

                        <p class="mt-2.5 text-sm leading-relaxed text-ink">{{ review.message }}</p>

                        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-ink-muted">
                            <span class="rounded-pill bg-linen px-2.5 py-1">{{ serviceName(review) }}</span>

                            <router-link
                                v-if="appointmentOf(review)"
                                :to="{ name: 'admin.appointments.show', params: { id: appointmentOf(review).id } }"
                                class="font-medium text-primary underline underline-offset-2"
                            >
                                {{ appointmentOf(review).reference_number }}
                            </router-link>

                            <router-link
                                v-if="userOf(review)"
                                :to="{ name: 'admin.users.show', params: { id: userOf(review).id } }"
                                class="font-medium text-primary underline underline-offset-2"
                            >View customer</router-link>
                        </div>
                    </div>

                    <button
                        v-if="canManage"
                        type="button"
                        class="btn-danger btn-sm shrink-0"
                        @click="destroy(review)"
                    >Delete</button>
                </div>
            </article>
        </div>
    </div>
</template>
