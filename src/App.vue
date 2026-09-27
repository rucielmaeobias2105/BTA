<script setup>
import { computed, nextTick, watch } from 'vue';
import { useRoute } from 'vue-router';
import CustomerLayout from '@/layouts/CustomerLayout.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { refreshTabTitles } from '@/lib/tabTitle';

const LAYOUTS = {
    customer: CustomerLayout,
    admin: AdminLayout,
    guest: GuestLayout,
};

const route = useRoute();

/**
 * `meta.layout` replaces the `@extends('layouts.…')` line each view declared.
 * The three shells are the same ones the Blade layouts provided.
 */
const layout = computed(() => LAYOUTS[route.meta.layout ?? 'customer'] ?? CustomerLayout);

watch(
    () => route.fullPath,
    async () => {
        // The section-title helper binds `a[data-tab-title]` listeners, so it
        // has to run again once the new page has actually rendered.
        await nextTick();
        refreshTabTitles();
    },
    { immediate: true },
);
</script>

<template>
    <component :is="layout">
        <router-view v-slot="{ Component }">
            <component :is="Component" />
        </router-view>
    </component>
</template>
