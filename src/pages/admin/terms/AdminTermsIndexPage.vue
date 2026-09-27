<script setup>
import { computed, provide, reactive } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { TERMS_CATEGORY_VALUES, publishedTerms, revisionsFor, termsDocuments } from '@/data/terms';
import { TermsCategory, AdminRole, roleCan } from '@/data/enums';
import { findAdmin } from '@/data/admins';
import { currentAdmin, setFlash } from '@/lib/session';
import { today } from '@/lib/dates';
import { formatDate, stripTags, truncate } from '@/lib/format';

/**
 * Admin Flow 10 (part 1) — Terms & Conditions list.
 *
 * Ported from `admin/terms/index.blade.php` and `Admin\TermsController::index()`,
 * `publish()` and `destroy()`. The controller's "one published revision per
 * category" invariant is reproduced in `publish()`; the list itself re-derives
 * from the reactive `termsDocuments` collection.
 */
const role = computed(() => currentAdmin.value?.role ?? AdminRole.SuperAdmin.value);
const canManage = computed(() => roleCan(role.value, 'terms.manage'));

/** The global error bag Blade read through `<x-ui.errors />`. */
const errors = reactive({});

provide('form-errors', errors);

/** `$version->admin?->full_name ?? 'System'` */
function authorOf(version) {
    return findAdmin(version.created_by)?.full_name ?? 'System';
}

/**
 * The demo revision records carry `published_at` but no separate `created_at`
 * column, so the "authored on" line falls back to the publish date.
 */
function createdAtOf(version) {
    return formatDate(version.created_at ?? version.published_at, 'M j, Y');
}

function publish(version) {
    if (!window.confirm(`Publish version ${version.version}? The current live version will be retired.`)) return;

    // `TermsAndCondition::where('category', ...)->whereKeyNot($term->id)->update(['is_published' => false])`
    revisionsFor(version.category).forEach((row) => {
        row.is_published = row.id === version.id;
    });

    version.published_at = today();

    delete errors.term;

    setFlash(`Version ${version.version} is now live for customers.`);
}

function destroy(version) {
    // Published content is an immutable record, so it can never be deleted.
    if (version.is_published) {
        errors.term = 'Unpublish this version before deleting it.';

        return;
    }

    if (!window.confirm(`Delete draft version ${version.version}?`)) return;

    const index = termsDocuments.indexOf(version);

    if (index !== -1) termsDocuments.splice(index, 1);

    delete errors.term;

    setFlash(`Draft version ${version.version} deleted.`);
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Policies"
            title="Terms & Conditions"
            description="Versioned content per category. Publishing a version retires the previous one and updates the customer-facing pages."
        >
            <template #actions>
                <router-link v-if="canManage" :to="{ name: 'admin.terms.create' }" class="btn-primary btn-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    New Version
                </router-link>
            </template>
        </PageHeader>

        <ErrorSummary />

        <div class="space-y-6">
            <CardPanel
                v-for="category in TERMS_CATEGORY_VALUES"
                :key="category"
                :title="`${TermsCategory[category].label} Terms`"
            >
                <template #actions>
                    <router-link
                        v-if="canManage"
                        :to="{ name: 'admin.terms.create', query: { category } }"
                        class="text-xs font-medium text-primary underline underline-offset-2"
                    >Add version</router-link>
                </template>

                <div class="mb-4 flex flex-wrap items-center gap-2.5 rounded-xl bg-linen/60 px-4 py-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Live for customers</span>

                    <template v-if="publishedTerms(category)">
                        <Badge status="completed" :label="`v${publishedTerms(category).version} published`" />
                        <span class="text-xs text-ink-muted">{{ formatDate(publishedTerms(category).published_at, 'M j, Y') }}</span>

                        <router-link
                            :to="{ name: 'terms.show', params: { category } }"
                            class="ml-auto text-xs font-medium text-primary underline underline-offset-2"
                        >Preview</router-link>
                    </template>

                    <Badge v-else status="cancelled" label="Not published" />
                </div>

                <p v-if="revisionsFor(category).length === 0" class="text-sm text-ink-muted">No versions yet.</p>

                <ul v-else class="space-y-2.5">
                    <li
                        v-for="version in revisionsFor(category)"
                        :key="version.id"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3"
                        :class="version.is_published
                            ? 'border-status-completed/30 bg-status-completed-bg/25'
                            : 'border-primary/12 bg-linen/50'"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-primary">Version {{ version.version }}</p>

                                <Badge
                                    :status="version.is_published ? 'completed' : 'pending'"
                                    :label="version.is_published ? 'Live' : 'Draft'"
                                />
                            </div>

                            <p class="mt-0.5 text-xs text-ink-muted">
                                {{ truncate(stripTags(version.content), 110) }}
                            </p>

                            <p class="mt-1 text-[11px] text-ink-muted">
                                {{ authorOf(version) }}
                                &middot; {{ createdAtOf(version) }}
                            </p>
                        </div>

                        <div v-if="canManage" class="flex shrink-0 flex-wrap items-center gap-1.5">
                            <router-link
                                :to="{ name: 'admin.terms.edit', params: { id: version.id } }"
                                class="btn-secondary btn-sm"
                            >Edit</router-link>

                            <template v-if="!version.is_published">
                                <button type="button" class="btn-primary btn-sm" @click="publish(version)">
                                    Publish
                                </button>

                                <button type="button" class="btn-danger btn-sm" @click="destroy(version)">
                                    Delete
                                </button>
                            </template>
                        </div>
                    </li>
                </ul>
            </CardPanel>
        </div>
    </div>
</template>
