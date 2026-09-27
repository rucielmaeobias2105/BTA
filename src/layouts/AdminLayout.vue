<script setup>
import { ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import AdminSidebar from '@/components/admin/AdminSidebar.vue';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import { currentAdmin, flashMessage } from '@/lib/session';

/**
 * Admin shell.
 *
 * Ported from `layouts/admin.blade.php`. The `x-data="{ sidebar: false }"`
 * wrapper is a `ref`, and `auth('admin')->user()` in the footer reads the
 * session store's admin guard.
 */
const sidebar = ref(false);
</script>

<template>
    <div class="min-h-screen bg-linen lg:flex">
        <div
            v-show="sidebar"
            class="fixed inset-0 z-40 bg-primary/40 backdrop-blur-sm transition-opacity duration-200 lg:hidden"
            aria-hidden="true"
            @click="sidebar = false"
        />

        <AdminSidebar :open="sidebar" @close="sidebar = false" />

        <div class="flex min-w-0 flex-1 flex-col">
            <AdminTopbar @open-navigation="sidebar = true" />

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div v-if="flashMessage" class="mb-5">
                    <Alert type="success">{{ flashMessage }}</Alert>
                </div>

                <slot />
            </main>

            <footer class="border-t border-primary/10 px-4 py-4 text-center text-xs text-ink-muted sm:px-6 lg:px-8">
                Balai ti Arjud Admin &middot; Signed in as
                <span class="font-medium text-primary">{{ currentAdmin?.full_name }}</span>
            </footer>
        </div>
    </div>
</template>
