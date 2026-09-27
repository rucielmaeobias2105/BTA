<script setup>
import { computed, nextTick, onMounted, provide, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Alert from '@/components/ui/Alert.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { TERMS_CATEGORY_VALUES, findTermsDocument, publishedTerms, termsDocuments } from '@/data/terms';
import { TermsCategory } from '@/data/enums';
import { currentAdmin, setFlash } from '@/lib/session';
import { today } from '@/lib/dates';
import { createForm } from '@/lib/validation';

/**
 * Admin Flow 10 (part 2) — Terms & Conditions editor.
 *
 * Ported from `admin/terms/_form.blade.php` plus `Admin\TermsController`'s
 * `store()`, `update()`, `publish()` and `destroy()`. The route decides the
 * mode: `/admin/terms/create` has no `:id`, `/admin/terms/:id/edit` does.
 *
 * The dependency-free contenteditable editor from the Blade partial is kept —
 * its `execCommand` toolbar and the raw source textarea are two views of the
 * same `content` string, which is exactly the contract the controller had with
 * the posted `content` field.
 */
const route = useRoute();
const router = useRouter();

/** No `:id` means create; the index page's "Add version" link preselects a category. */
const termId = computed(() => route.params.id ?? null);
const term = computed(() => (termId.value ? findTermsDocument(termId.value) : null));
const isEditing = computed(() => Boolean(term.value));

const categories = Object.fromEntries(
    TERMS_CATEGORY_VALUES.map((value) => [value, TermsCategory[value].label]),
);

function categorySeed() {
    const requested = TermsCategory[String(route.query.category ?? '')] ? route.query.category : null;

    return term.value?.category ?? requested ?? TermsCategory.Booking.value;
}

/** `TermsAndCondition::nextVersionFor()` */
function nextVersionFor(category) {
    return termsDocuments
        .filter((doc) => doc.category === category)
        .reduce((highest, doc) => Math.max(highest, doc.version), 0) + 1;
}

const form = createForm({
    initial: {
        category: categorySeed(),
        content: term.value?.content ?? '',
        is_published: term.value ? Boolean(term.value.is_published) : true,
    },
    rules: {
        category: ['required', 'string', `in:${TERMS_CATEGORY_VALUES.join(',')}`],
        content: ['required', 'string', 'min:10'],
        is_published: ['nullable'],
    },
    // `TermsController::store()` refused a save identical to the live version.
    after(errors, values) {
        const existing = publishedTerms(values.category);

        if (existing && existing.content === values.content) {
            errors.content = 'This is identical to the current published version.';
        }
    },
});

provide('form-errors', form.errors);

/* ------------------------------------------------------------------ */
/* Rich text surface                                                   */
/* ------------------------------------------------------------------ */

const editor = ref(null);
const source = ref(null);
const showSource = ref(false);

/** Bold / italic / underline — the same three buttons the Blade toolbar had. */
const TOOLBAR = [
    ['bold', 'Bold', 'M6 4h8a4 4 0 0 1 0 8H6zM6 12h9a4 4 0 0 1 0 8H6z'],
    ['italic', 'Italic', 'M19 4h-9M14 20H5M15 4 9 20'],
    ['underline', 'Underline', 'M6 4v6a6 6 0 0 0 12 0V4M4 21h16'],
];

function loadEditor() {
    if (editor.value) editor.value.innerHTML = form.values.content;
    if (source.value) source.value.value = form.values.content;
}

onMounted(loadEditor);

function exec(command) {
    editor.value.focus();
    document.execCommand(command, false, null);
    sync();
}

function onBlockChange(event) {
    formatBlock(event.target.value);
    event.target.value = '';
}

function formatBlock(tag) {
    if (!tag) return;

    editor.value.focus();
    document.execCommand('formatBlock', false, tag);
    sync();
}

function insertLink() {
    const url = window.prompt('Link URL (https://…)');

    if (!url) return;

    editor.value.focus();
    document.execCommand('createLink', false, url);
    sync();
}

/** The surface must never end up empty silently. */
function sync() {
    if (!editor.value) return;

    form.values.content = editor.value.innerHTML;
}

function syncFromSource(event) {
    form.values.content = event.target.value;

    if (editor.value) editor.value.innerHTML = form.values.content;
}

function toggleSource() {
    sync();

    if (source.value) source.value.value = form.values.content;

    showSource.value = !showSource.value;
}

/* ------------------------------------------------------------------ */
/* Saving                                                              */
/* ------------------------------------------------------------------ */

const submitLabel = computed(() => {
    if (!isEditing.value) return 'Create Version';
    if (term.value.is_published) return 'Save as New Version';

    return 'Save Draft';
});

/** Only one published revision per category survives a publish. */
function retireOtherRevisions(category, keepId = null) {
    termsDocuments
        .filter((doc) => doc.category === category && doc.id !== keepId)
        .forEach((doc) => {
            doc.is_published = false;
        });
}

/** `TermsController::store()` — every store is a new version, never an overwrite. */
function store() {
    const category = form.values.category;
    const isPublished = Boolean(form.values.is_published);

    if (isPublished) retireOtherRevisions(category);

    const doc = reactive({
        id: termsDocuments.reduce((highest, row) => Math.max(highest, row.id), 0) + 1,
        category,
        version: nextVersionFor(category),
        content: form.values.content,
        is_published: isPublished,
        published_at: isPublished ? today() : null,
        created_by: currentAdmin.value?.id ?? 1,
    });

    termsDocuments.push(doc);

    setFlash(`${TermsCategory[category].label} terms saved as version ${doc.version}.`);

    router.push({ name: 'admin.terms.index' });
}

/** `TermsController::update()` */
function update(target) {
    const category = form.values.category;
    const isPublished = Boolean(form.values.is_published);

    if (target.is_published && !isPublished) {
        form.errors.is_published = 'Unpublish directly from the list — published versions are immutable records.';

        return;
    }

    // Editing a draft mutates in place; published content is versioned.
    if (!target.is_published && target.content !== form.values.content) {
        Object.assign(target, { category, content: form.values.content });

        setFlash(`Draft updated (version ${target.version}).`);

        return;
    }

    if (target.is_published) {
        store();

        return;
    }

    setFlash('Nothing to update.');
}

function submit() {
    if (!form.validate()) return;

    if (isEditing.value) {
        update(term.value);

        return;
    }

    store();
}

/**
 * Create and edit share one component, so moving between the two routes keeps
 * the instance alive — the form and both editor surfaces are re-seeded here.
 */
watch([termId, () => route.query.category], () => {
    showSource.value = false;

    form.reset({
        category: categorySeed(),
        content: term.value?.content ?? '',
        is_published: term.value ? Boolean(term.value.is_published) : true,
    });

    nextTick(loadEditor);
});
</script>

<template>
    <div>
        <router-link
            :to="{ name: 'admin.terms.index' }"
            class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to terms
        </router-link>

        <PageHeader
            eyebrow="Policies"
            :title="isEditing ? `Edit Terms · ${TermsCategory[term.category].label}` : 'New Terms Version'"
            :description="isEditing && term.is_published
                ? 'This version is published. Saving a change creates a new version; the published record stays immutable.'
                : 'Drafts can be edited freely. Publishing retires the previous version.'"
        />

        <ErrorSummary />

        <form novalidate @submit.prevent="submit">
            <div class="grid gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <CardPanel title="Content" subtitle="Formatting is stored as HTML and rendered on the customer-facing T&C pages.">
                        <div class="mb-4">
                            <FormSelect
                                v-model="form.values.category"
                                name="category"
                                label="Category"
                                required
                                :options="categories"
                            />
                        </div>

                        <!-- Toolbar -->
                        <div class="mb-2.5 flex flex-wrap items-center gap-1 rounded-xl border border-primary/15 bg-linen/60 p-2">
                            <button
                                v-for="[command, label, path] in TOOLBAR"
                                :key="command"
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-primary transition hover:bg-cream"
                                :title="label"
                                @click="exec(command)"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path :d="path"/></svg>
                            </button>

                            <span class="mx-1 h-6 w-px bg-primary/15" />

                            <select class="input w-32 py-1.5 text-xs" aria-label="Text style" @change="onBlockChange">
                                <option value="">Paragraph</option>
                                <option value="h2">Heading 2</option>
                                <option value="h3">Heading 3</option>
                                <option value="blockquote">Quote</option>
                            </select>

                            <span class="mx-1 h-6 w-px bg-primary/15" />

                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-primary transition hover:bg-cream"
                                title="Bulleted list"
                                @click="exec('insertUnorderedList')"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>
                            </button>
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-primary transition hover:bg-cream"
                                title="Numbered list"
                                @click="exec('insertOrderedList')"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>
                            </button>

                            <span class="mx-1 h-6 w-px bg-primary/15" />

                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-primary transition hover:bg-cream"
                                title="Insert link"
                                @click="insertLink"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                            </button>
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-primary transition hover:bg-cream"
                                title="Divider"
                                @click="exec('insertHorizontalRule')"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 12h18"/></svg>
                            </button>

                            <span class="ml-auto" />

                            <button type="button" class="toggle-link" @click="toggleSource">Show HTML</button>
                        </div>

                        <!-- WYSIWYG surface -->
                        <div
                            ref="editor"
                            contenteditable="true"
                            class="prose-bta min-h-72 rounded-xl border border-primary/20 bg-white/70 px-4 py-3.5 text-sm leading-relaxed text-ink focus:border-gold focus:outline-none focus:ring-2 focus:ring-gold/40
                                   [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-primary [&_h2]:mb-3
                                   [&_h3]:mt-5 [&_h3]:font-semibold [&_h3]:text-primary
                                   [&_blockquote]:border-l-2 [&_blockquote]:border-gold [&_blockquote]:pl-4 [&_blockquote]:italic
                                   [&_li]:ml-5 [&_li]:list-disc [&_ol_li]:list-decimal
                                   [&_p]:mb-3 [&_strong]:font-semibold [&_strong]:text-primary [&_ul]:mb-3"
                            @input="sync"
                            @blur="sync"
                        />

                        <!-- Raw HTML source (same value, toggled) -->
                        <textarea
                            v-show="showSource"
                            :value="form.values.content"
                            rows="14"
                            class="input mt-3 font-mono text-xs"
                            spellcheck="false"
                            aria-label="Terms HTML source"
                            @input="syncFromSource"
                        />

                        <p v-if="form.errors.content" class="input-error-text">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            {{ form.errors.content }}
                        </p>
                    </CardPanel>
                </div>

                <aside class="space-y-6">
                    <CardPanel title="Publishing">
                        <Alert v-if="isEditing && term.is_published" type="warning" class="mb-4" :dismissible="false">
                            Version {{ term.version }} is live. Saving will create version
                            {{ nextVersionFor(term.category) }} and publish it.
                        </Alert>

                        <FormCheckbox
                            v-model="form.values.is_published"
                            name="is_published"
                            :value="1"
                            label="Publish immediately (retires the previous version)"
                        />
                    </CardPanel>

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-primary w-full">
                            {{ submitLabel }}
                        </button>

                        <router-link :to="{ name: 'admin.terms.index' }" class="btn-ghost w-full">Cancel</router-link>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</template>
