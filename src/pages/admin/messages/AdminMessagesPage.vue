<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormTextarea from '@/components/ui/form/FormTextarea.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { findAdmin } from '@/data/admins';
import { InquiryTopic, roleCan } from '@/data/enums';
import { contactMessages, unreadMessages } from '@/data/messages';
import { formatDate } from '@/lib/format';
import { currentAdmin, setFlash } from '@/lib/session';

/**
 * Admin Flow 13 (part 1) — Contact Messages inbox.
 *
 * Ported from `admin/messages/index.blade.php` and
 * `Admin\ContactMessageController::index()`, `update()` and `destroy()`.
 * The `filter` and `search` query params and the `paginate(15)` were server
 * side; they are a `computed` over the reactive `contactMessages` collection
 * with `route.query` as the filter state.
 *
 * Every message carried its own reply form, so the draft text is held in a
 * plain object here instead of one form per card.
 */
const route = useRoute();
const router = useRouter();

/** Rows per page. The controller used `paginate(15)`. */
const PER_PAGE = 10;

/**
 * `currentAdmin.role` holds the *label*, so the machine value comes off the
 * `admins` record — the same resolution `AdminServicesIndexPage` uses.
 *
 * `admin.messages.manage` guarded every route in this group, so the inbox stays
 * readable for a read-only role and the write controls are hidden instead.
 */
const role = computed(() => findAdmin(currentAdmin.value?.id)?.role ?? 'super_admin');

const canManage = computed(() => roleCan(role.value, 'messages.manage'));

/* ------------------------------------------------------------------ */
/* Filters — the query string the Blade form submitted                 */
/* ------------------------------------------------------------------ */

const unreadCount = computed(() => unreadMessages.value.length);

const search = computed(() => String(route.query.search ?? ''));
const filter = computed(() => (String(route.query.filter ?? '') === 'unread' ? 'unread' : ''));

const hasFilters = computed(() => Boolean(search.value || filter.value));

/** The tabs, so "All" and "Unread" are rendered from the same list. */
const tabs = computed(() => [
    { value: '', label: 'All' },
    { value: 'unread', label: `Unread (${unreadCount.value})` },
]);

/** Drops blanks rather than writing back `?search=`, as `array_filter` did. */
function applyQuery(patch) {
    const next = { ...route.query, ...patch };

    Object.keys(next).forEach((key) => {
        if (next[key] === '' || next[key] === null || next[key] === undefined) delete next[key];
    });

    router.replace({ name: 'admin.messages.index', query: next });
}

/** The search box holds a local copy and commits on a short debounce. */
const term = ref(search.value);

watch(search, (value) => {
    term.value = value;
});

let timer = null;

watch(term, (value) => {
    if (value === search.value) return;

    window.clearTimeout(timer);

    timer = window.setTimeout(() => applyQuery({ search: value, page: '' }), 300);
});

onBeforeUnmount(() => {
    window.clearTimeout(timer);
});

/* ------------------------------------------------------------------ */
/* The list                                                             */
/* ------------------------------------------------------------------ */

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return [...contactMessages]
        .filter((message) => {
            if (filter.value === 'unread' && message.is_read) return false;

            if (!needle) return true;

            // The query matched name, email and the message body.
            return [message.name, message.email, message.message].some((field) =>
                String(field ?? '').toLowerCase().includes(needle),
            );
        })
        // `->latest()`
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

/* ------------------------------------------------------------------ */
/* Pagination                                                           */
/* ------------------------------------------------------------------ */

const page = computed(() => {
    const requested = Number(route.query.page ?? 1);

    return Number.isInteger(requested) && requested > 0 ? requested : 1;
});

const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)));

const rows = computed(() => {
    const current = Math.min(page.value, lastPage.value);

    return filtered.value.slice((current - 1) * PER_PAGE, current * PER_PAGE);
});

const rangeLabel = computed(() => {
    if (filtered.value.length === 0) return '0 results';

    const first = (Math.min(page.value, lastPage.value) - 1) * PER_PAGE + 1;

    return `Showing ${first}–${Math.min(first + PER_PAGE - 1, filtered.value.length)} of ${filtered.value.length}`;
});

function goToPage(target) {
    if (target < 1 || target > lastPage.value) return;

    applyQuery({ page: target === 1 ? '' : String(target) });
}

/* ------------------------------------------------------------------ */
/* Drafts + actions                                                     */
/* ------------------------------------------------------------------ */

/** `old('admin_reply', $message->admin_reply)` — one textarea per message. */
const drafts = ref({});

function draftFor(message) {
    return drafts.value[message.id] ?? message.admin_reply ?? '';
}

function onDraft(message, value) {
    drafts.value = { ...drafts.value, [message.id]: value };
}

function topicMeta(message) {
    return InquiryTopic[message.topic] ?? null;
}

/** `ContactMessageController::update()` — saving the reply also marks it read. */
function save(message) {
    const reply = draftFor(message).trim() || null;

    message.admin_reply = reply;
    message.is_read = true;

    if (reply) message.replied_at = new Date();

    drafts.value = { ...drafts.value, [message.id]: reply ?? '' };

    setFlash('Reply saved.');
}

/** `ContactMessageController::destroy()` */
function destroy(message) {
    if (!window.confirm('Delete this message?')) return;

    const index = contactMessages.findIndex((row) => row.id === message.id);

    if (index === -1) return;

    contactMessages.splice(index, 1);

    setFlash('Message deleted.');
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Inbox"
            title="Contact Messages"
            description="Enquiries submitted through the customer Contact Us form."
        />

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <router-link
                v-for="tab in tabs"
                :key="tab.value || 'all'"
                :to="tab.value ? { name: 'admin.messages.index', query: { filter: tab.value } } : { name: 'admin.messages.index' }"
                class="rounded-pill px-3.5 py-1.5 text-sm font-medium transition"
                :class="filter === tab.value ? 'bg-primary text-cream' : 'bg-cream text-ink hover:bg-linen'"
            >
                {{ tab.label }}
            </router-link>
        </div>

        <form class="bta-card mb-6 p-5" novalidate @submit.prevent="applyQuery({ search: term, page: '' })">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-64 flex-1">
                    <FormInput
                        :model-value="term"
                        name="search"
                        label="Search"
                        placeholder="Name, email or message"
                        @update:model-value="term = $event"
                    />
                </div>
                <button type="submit" class="btn-primary">Search</button>
                <router-link v-if="hasFilters" :to="{ name: 'admin.messages.index' }" class="btn-ghost">Clear</router-link>
            </div>
        </form>

        <EmptyState
            v-if="filtered.length === 0"
            title="No messages"
            description="Customer enquiries will appear here."
        />

        <template v-else>
            <div class="space-y-4">
                <article
                    v-for="message in rows"
                    :key="message.id"
                    class="bta-card p-5"
                    :class="message.is_read ? '' : 'border-l-4 !border-l-primary'"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-primary">{{ message.name }}</p>
                                <Badge v-if="topicMeta(message)" status="gold" :label="topicMeta(message).label" />
                                <Badge v-if="!message.is_read" status="pending" label="New" />
                            </div>

                            <p class="mt-0.5 text-xs text-ink-muted">
                                <a :href="`mailto:${message.email}`" class="hover:text-primary">{{ message.email }}</a>
                                &middot; {{ formatDate(message.created_at, 'M j, Y g:i A') }}
                            </p>

                            <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-ink">{{ message.message }}</p>
                        </div>

                        <button
                            v-if="canManage"
                            type="button"
                            class="btn-danger btn-sm shrink-0"
                            @click="destroy(message)"
                        >Delete</button>
                    </div>

                    <form
                        v-if="canManage"
                        class="mt-4 border-t border-primary/10 pt-4"
                        novalidate
                        @submit.prevent="save(message)"
                    >
                        <FormTextarea
                            :model-value="draftFor(message)"
                            name="admin_reply"
                            label="Internal Reply / Note"
                            :rows="3"
                            placeholder="Record your response here and mark the message as read."
                            @update:model-value="onDraft(message, $event)"
                        />

                        <div class="mt-3 flex items-center justify-end">
                            <button type="submit" class="btn-primary btn-sm">Save &amp; Mark Read</button>
                        </div>
                    </form>
                </article>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-ink-muted">{{ rangeLabel }}</p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="btn-ghost btn-sm"
                        :disabled="page <= 1"
                        @click="goToPage(page - 1)"
                    >Previous</button>

                    <span class="text-sm text-ink-muted">Page {{ Math.min(page, lastPage) }} of {{ lastPage }}</span>

                    <button
                        type="button"
                        class="btn-ghost btn-sm"
                        :disabled="page >= lastPage"
                        @click="goToPage(page + 1)"
                    >Next</button>
                </div>
            </div>
        </template>
    </div>
</template>
