<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BrandLogo from '@/components/brand/BrandLogo.vue';
import { isAuthenticated, currentUser, signOutCustomer } from '@/lib/session';
import { firstName, initials } from '@/lib/format';
import { customerCurrent } from '@/lib/nav';
import { sectionTitle } from '@/lib/tabTitle';
import { unreadNotificationCount } from '@/data/notifications';

/**
 * Customer header.
 *
 * Ported from `partials/customer/nav.blade.php`. The `@auth` / `@else` branches
 * became `v-if` on the session store, the Alpine `dropdown` component became a
 * local `ref`, and the logout `<form method="POST">` became a button.
 */
const route = useRoute();
const router = useRouter();

const current = computed(() => customerCurrent(route.name));
const unread = computed(() => (isAuthenticated.value ? unreadNotificationCount.value : 0));

const mobileOpen = ref(false);
const profileOpen = ref(false);

const PROMO_TAB = sectionTitle('Promo');

const LINKS = computed(() => [
    { key: 'home', label: 'Home', to: { name: 'home' } },
    { key: 'services', label: 'Services', to: { name: 'services.index' } },
    { key: 'promo', label: 'Promo', to: { path: '/#offers' }, tabTitle: PROMO_TAB, hash: true },
    { key: 'about', label: 'About Us', to: { name: 'about' } },
    { key: 'contact', label: 'Contact', to: { name: 'contact.create' } },
]);

/** The hash link needs the raw `/#offers` string for `data-tab-title` binding. */
function hrefFor(link) {
    return link.hash ? link.to.path : undefined;
}

function linkTo(link) {
    return link.hash ? undefined : link.to;
}

function signOut() {
    signOutCustomer();
    profileOpen.value = false;
    mobileOpen.value = false;
    router.push({ name: 'home' });
}
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-primary/10 bg-cream/95 backdrop-blur supports-[backdrop-filter]:bg-cream/80">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <BrandLogo :to="{ name: 'home' }" size="md" image="/images/logo.png" />

            <nav class="hidden items-center gap-7 lg:flex" aria-label="Main">
                <template v-for="link in LINKS" :key="link.key">
                    <a
                        v-if="link.hash"
                        :href="hrefFor(link)"
                        class="customer-nav-link"
                        :data-tab-title="link.tabTitle"
                    >{{ link.label }}</a>
                    <router-link
                        v-else
                        :to="linkTo(link)"
                        class="customer-nav-link"
                        :class="{ 'customer-nav-link-active': current === link.key }"
                    >{{ link.label }}</router-link>
                </template>
            </nav>

            <div class="flex items-center gap-2.5">
                <template v-if="isAuthenticated">
                    <router-link
                        :to="{ name: 'notifications.index' }"
                        class="relative flex h-10 w-10 items-center justify-center rounded-full text-primary transition hover:bg-primary/5"
                        :aria-label="`Notifications (${unread} unread)`"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>

                        <span
                            v-if="unread > 0"
                            class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-cream ring-2 ring-cream animate-bell-ring"
                        >
                            {{ unread > 9 ? '9+' : unread }}
                        </span>
                    </router-link>

                    <div v-if="currentUser" class="relative hidden sm:block">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-pill border border-primary/15 py-1 pl-1 pr-3 transition hover:border-gold hover:bg-linen/60"
                            aria-haspopup="true"
                            :aria-expanded="profileOpen"
                            @click="profileOpen = !profileOpen"
                        >
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-cream">
                                {{ initials(currentUser.first_name, currentUser.last_name) }}
                            </span>
                            <span class="text-sm font-medium text-ink">{{ firstName(currentUser.full_name) }}</span>
                            <svg class="h-3.5 w-3.5 text-ink-muted" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div
                            v-show="profileOpen"
                            class="absolute right-0 mt-2 w-56 overflow-hidden rounded-card border border-primary/10 bg-cream shadow-card-hover"
                            @click.outside="profileOpen = false"
                        >
                            <div class="border-b border-primary/10 px-4 py-3">
                                <p class="truncate text-sm font-semibold text-primary">{{ currentUser.full_name }}</p>
                                <p class="truncate text-xs text-ink-muted">{{ currentUser.email }}</p>
                            </div>
                            <div class="p-1.5">
                                <router-link :to="{ name: 'dashboard' }" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-linen" @click="profileOpen = false">Dashboard</router-link>
                                <router-link :to="{ name: 'appointments.index' }" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-linen" @click="profileOpen = false">My Appointments</router-link>
                                <router-link :to="{ name: 'profile.edit' }" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-linen" @click="profileOpen = false">Profile</router-link>
                                <div class="mt-1 border-t border-primary/10 pt-1">
                                    <button type="button" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-status-cancelled transition hover:bg-status-cancelled-bg/50" @click="signOut">Log Out</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <router-link :to="{ name: 'login' }" class="btn-secondary btn-sm hidden sm:inline-flex">Log In</router-link>
                    <router-link :to="{ name: 'register' }" class="btn-primary btn-sm">Register</router-link>
                </template>

                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-lg text-primary lg:hidden"
                    aria-label="Toggle menu"
                    :aria-expanded="mobileOpen"
                    @click="mobileOpen = !mobileOpen"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
            </div>
        </div>

        <div v-show="mobileOpen" class="border-t border-primary/10 bg-cream lg:hidden">
            <nav class="mx-auto grid max-w-7xl gap-1 px-4 py-4 sm:px-6" aria-label="Mobile">
                <template v-for="link in LINKS" :key="link.key">
                    <a
                        v-if="link.hash"
                        :href="hrefFor(link)"
                        class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen"
                        :data-tab-title="link.tabTitle"
                    >{{ link.label }}</a>
                    <router-link
                        v-else
                        :to="linkTo(link)"
                        class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen"
                    >{{ link.label }}</router-link>
                </template>

                <template v-if="isAuthenticated">
                    <div class="bta-divider my-2" />
                    <router-link :to="{ name: 'dashboard' }" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen" @click="mobileOpen = false">Dashboard</router-link>
                    <router-link :to="{ name: 'appointments.index' }" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen" @click="mobileOpen = false">My Appointments</router-link>
                    <router-link :to="{ name: 'notifications.index' }" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen" @click="mobileOpen = false">
                        Notifications
                        <span v-if="unread > 0" class="ml-1 rounded-pill bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-cream">{{ unread }}</span>
                    </router-link>
                    <router-link :to="{ name: 'profile.edit' }" class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-linen" @click="mobileOpen = false">Profile</router-link>
                    <button type="button" class="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-status-cancelled hover:bg-status-cancelled-bg/50" @click="signOut">Log Out</button>
                </template>

                <div v-else class="mt-3 grid grid-cols-2 gap-2">
                    <router-link :to="{ name: 'login' }" class="btn-secondary btn-sm">Log In</router-link>
                    <router-link :to="{ name: 'register' }" class="btn-primary btn-sm">Register</router-link>
                </div>
            </nav>
        </div>
    </header>
</template>
