<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import { TermsCategory } from '@/data/enums';
import { TERMS_CATEGORY_VALUES, publishedTerms } from '@/data/terms';
import { formatDate } from '@/lib/format';

/**
 * Renders the admin-authored, versioned T&C content that the booking,
 * cancellation and reschedule checkboxes link to.
 *
 * Ported from `customer/terms/show.blade.php` and `Customer\TermsController`.
 * `TermsCategory::tryFrom()` aborted 404 on an unknown slug, which is what the
 * `!category` branch below reproduces. The document body is salon-authored rich
 * text stored as HTML, so it keeps rendering unescaped, as `{!! !!}` did.
 */
const route = useRoute();

const category = computed(() => TermsCategory[String(route.params.category)] ?? null);
const terms = computed(() => (category.value ? publishedTerms(category.value.value) : null));

const description = computed(() =>
    terms.value
        ? `Currently published version ${terms.value.version} — last updated ${formatDate(terms.value.published_at, 'M j, Y')}.`
        : 'No published version for this category yet. Please check back soon.',
);
</script>

<template>
    <NotFoundPage v-if="!category" />

    <div v-else class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <nav class="mb-6 flex items-center gap-2 text-sm text-ink-muted" aria-label="Breadcrumb">
            <router-link :to="{ name: 'home' }" class="transition hover:text-primary">Home</router-link>
            <span aria-hidden="true">/</span>
            <span class="font-medium text-primary">{{ category.label }} Terms</span>
        </nav>

        <PageHeader
            eyebrow="Policies"
            :title="`${category.label} Terms & Conditions`"
            :description="description"
        />

        <div class="grid gap-5 sm:grid-cols-3">
            <router-link
                v-for="value in TERMS_CATEGORY_VALUES"
                :key="value"
                :to="{ name: 'terms.show', params: { category: value } }"
                class="rounded-card border px-4 py-3 text-center text-sm font-medium transition"
                :class="value === category.value
                    ? 'border-primary bg-primary text-cream'
                    : 'border-primary/15 bg-cream text-ink hover:border-gold hover:text-primary'"
            >
                {{ TermsCategory[value].label }}
            </router-link>
        </div>

        <article class="bta-card mt-8 p-6 sm:p-10">
            <!-- Admin-authored rich text (Admin Flow 10). Stored as HTML. -->
            <div
                v-if="terms"
                class="prose-bta max-w-none text-sm leading-relaxed text-ink [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-primary [&_h2]:mb-4 [&_h3]:mt-6 [&_h3]:font-semibold [&_h3]:text-primary [&_hr]:my-6 [&_li]:ml-5 [&_li]:list-disc [&_ol]:list-decimal [&_p]:mb-4 [&_strong]:font-semibold [&_strong]:text-primary [&_ul]:mb-4"
                v-html="terms.content"
            />

            <EmptyState
                v-else
                title="Not published yet"
                description="Our team is still finalising this policy. Please contact us if you have questions in the meantime."
            >
                <router-link :to="{ name: 'contact.create' }" class="btn-secondary">Contact Us</router-link>
            </EmptyState>
        </article>

        <div class="mt-8 text-center">
            <router-link :to="{ name: 'appointments.create' }" class="btn-primary">Book an Appointment</router-link>
        </div>
    </div>
</template>
