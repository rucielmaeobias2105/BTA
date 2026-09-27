<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BrandLogo from '@/components/brand/BrandLogo.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { adminCurrent } from '@/lib/nav';
import { currentAdmin, signOutAdmin } from '@/lib/session';
import { initialsFrom } from '@/lib/format';
import { AdminRole, roleCan } from '@/data/enums';
import { appointments } from '@/data/appointments';
import { lowStockItems } from '@/data/inventory';

/**
 * Admin sidebar.
 *
 * Ported from `partials/admin/sidebar.blade.php`. The nav definition moved out of
 * the `@php` block, and `Gate::allows($link['ability'])` became `roleCan()` over
 * the same ability matrix the routes used to enforce.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const route = useRoute();
const router = useRouter();

const current = computed(() => adminCurrent(route.name));

const pendingCount = computed(
    () => appointments.filter((appointment) => appointment.status === 'pending').length,
);

const lowStockCount = computed(() => lowStockItems.value.length);

const GROUPS = computed(() => [
    {
        heading: 'Main',
        links: [
            { route: 'admin.dashboard', label: 'Dashboard', icon: 'squares-2x2', active: 'dashboard', ability: 'dashboard.view' },
            { route: 'admin.appointments.index', label: 'Appointments', icon: 'calendar-days', active: 'appointments', ability: 'appointments.manage', badge: pendingCount.value },
            { route: 'admin.calendar.index', label: 'Calendar', icon: 'calendar', active: 'calendar', ability: 'calendar.view' },
        ],
    },
    {
        heading: 'Catalog',
        links: [
            { route: 'admin.catalog.index', label: 'Services & Items', icon: 'sparkles', active: 'catalog', ability: 'catalog.view' },
            { route: 'admin.services.index', label: 'Services', icon: 'scissors', active: 'services', ability: 'catalog.view' },
            { route: 'admin.promos.index', label: 'Promo', icon: 'megaphone', active: 'promos', ability: 'promos.manage' },
        ],
    },
    {
        heading: 'Operations',
        links: [
            { route: 'admin.inventory.index', label: 'Inventory', icon: 'archive-box', active: 'inventory', ability: 'inventory.view', badge: lowStockCount.value, badgeStyle: 'warning' },
            { route: 'admin.tags.index', label: 'Low-Stock Tags', icon: 'tag', active: 'tags', ability: 'inventory.view' },
            { route: 'admin.users.index', label: 'Registered Users', icon: 'users', active: 'users', ability: 'users.view' },
            { route: 'admin.reviews.index', label: 'Reviews', icon: 'star', active: 'reviews', ability: 'reviews.view' },
        ],
    },
    {
        heading: 'System',
        links: [
            { route: 'admin.terms.index', label: 'Terms & Conditions', icon: 'document-text', active: 'terms', ability: 'terms.view' },
            { route: 'admin.reports.index', label: 'Reports', icon: 'chart-bar', active: 'reports', ability: 'reports.view' },
        ],
    },
]);

const role = computed(() => currentAdmin.value?.role ?? AdminRole.SuperAdmin.value);

const visibleGroups = computed(() =>
    GROUPS.value
        .map((group) => ({ ...group, links: group.links.filter((link) => roleCan(role.value, link.ability)) }))
        .filter((group) => group.links.length > 0),
);

const roleLabel = computed(
    () => AdminRole[role.value]?.label ?? 'Staff',
);

function signOut() {
    signOutAdmin();
    emit('close');
    router.push({ name: 'home' });
}
</script>

<template>
    <aside
        v-show="open"
        class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col border-r border-line/70 bg-cream transition-transform duration-200 lg:static lg:!transform lg:translate-x-0"
        aria-label="Admin navigation"
    >
        <div class="relative shrink-0 border-b border-line/70 px-4 py-4">
            <router-link :to="{ name: 'admin.dashboard' }">
                <BrandLogo size="sm" :href="null" />
            </router-link>
            <span class="ml-14 mt-2 inline-block rounded-pill bg-blush px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-widest text-primary">Admin Panel</span>

            <button
                type="button"
                class="absolute right-3 top-5 rounded-lg p-1.5 text-ink-muted hover:bg-linen lg:hidden"
                aria-label="Close navigation"
                @click="emit('close')"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <path d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Admin">
            <template v-for="group in visibleGroups" :key="group.heading">
                <p class="admin-nav-heading">{{ group.heading }}</p>

                <router-link
                    v-for="link in group.links"
                    :key="link.route"
                    :to="{ name: link.route }"
                    class="admin-nav-link"
                    :class="{ 'admin-nav-link-active': current === link.active }"
                    :aria-current="current === link.active ? 'page' : undefined"
                    @click="emit('close')"
                >
                    <AppIcon :name="link.icon" class="h-5 w-5 shrink-0" />
                    <span class="flex-1 truncate">{{ link.label }}</span>

                    <span
                        v-if="link.badge"
                        class="rounded-pill px-1.5 py-0.5 text-[10px] font-semibold"
                        :class="current === link.active || link.badgeStyle !== 'warning'
                            ? 'bg-primary text-cream'
                            : 'bg-status-low-stock-bg text-status-low-stock'"
                    >{{ link.badge }}</span>
                </router-link>
            </template>
        </nav>

        <div class="shrink-0 space-y-2 border-t border-line/70 p-3">
            <router-link
                :to="{ name: 'admin.profile.edit' }"
                class="flex items-center gap-3 rounded-xl bg-linen px-3 py-2.5 transition hover:bg-blush"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blush text-sm font-bold text-primary">
                    {{ initialsFrom(currentAdmin?.full_name ?? '') }}
                </span>
                <span class="min-w-0">
                    <span class="flex items-center gap-1.5">
                        <span class="truncate text-sm font-semibold text-ink">{{ currentAdmin?.full_name }}</span>
                        <span class="badge badge-gold shrink-0">{{ roleLabel }}</span>
                    </span>
                    <span class="block truncate text-xs text-ink-muted">{{ currentAdmin?.email }}</span>
                </span>
            </router-link>

            <a href="/" target="_blank" rel="noopener" class="admin-nav-link">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
                <span class="flex-1">View Site</span>
            </a>

            <button
                type="button"
                class="admin-nav-link w-full text-status-cancelled hover:bg-status-cancelled-bg/50 hover:text-status-cancelled"
                @click="signOut"
            >
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                </svg>
                <span>Logout</span>
            </button>
        </div>
    </aside>
</template>
