<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { findInventoryItem } from '@/data/inventory';
import { findService } from '@/data/services';
import { setFlash } from '@/lib/session';

/**
 * Admin Flow 4 — the combined catalogue's row action.
 *
 * Ported from `CatalogController::edit()`, which was a pure dispatcher: it read
 * the `type` path segment and redirected to whichever editor owned that record.
 * There is no form to render here, so the same `match` runs on the way in and
 * the browser never lands on a URL the Laravel app would have refused to serve.
 */
const route = useRoute();
const router = useRouter();

/** `match ($type)` — the two editors the combined list could hand off to. */
const TARGETS = {
    service: { name: 'admin.services.edit', key: 'service' },
    item: { name: 'admin.inventory.edit', key: 'item' },
};

const type = computed(() => {
    const requested = String(route.params.type ?? '');

    return requested in TARGETS ? requested : null;
});

const record = computed(() => {
    if (type.value === 'service') return findService(route.params.id);
    if (type.value === 'item') return findInventoryItem(route.params.id);

    return null;
});

/** The label the demo flash would have carried, so the hand-off still reads. */
const label = computed(() => (type.value === 'item' ? 'Item' : 'Service'));

// The `default:` arm sent an unknown type or a missing record back to the list.
if (!record.value) {
    setFlash(`${label.value === 'Item' ? 'Item' : 'Service'} not found.`);

    router.replace({ name: 'admin.catalog.index' });
} else {
    router.replace({ name: TARGETS[type.value].name, params: { id: record.value.id } });
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Catalogue Overview"
            :title="`Edit ${label}`"
            :description="`Opening the ${label.toLowerCase()} editor…`"
        />

        <CardPanel title="Redirecting">
            <p class="text-sm text-ink-muted">Taking you to the {{ label.toLowerCase() }} editor.</p>
        </CardPanel>
    </div>
</template>
