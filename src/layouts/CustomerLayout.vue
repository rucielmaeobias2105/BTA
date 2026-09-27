<script setup>
import Alert from '@/components/ui/Alert.vue';
import CustomerNav from '@/components/customer/CustomerNav.vue';
import CustomerFooter from '@/components/customer/CustomerFooter.vue';
import { flashMessage } from '@/lib/session';

/**
 * Public / customer shell.
 *
 * Ported from `layouts/customer.blade.php`. The `<head>`, the `@vite` tag and
 * the `csrf-token` meta all moved to `index.html` and the router, so what is
 * left is the skip link, the flash banner (`session('status')`), the page slot
 * and the footer.
 */
</script>

<template>
    <div class="flex min-h-full flex-col bg-linen">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-cream"
        >
            Skip to content
        </a>

        <CustomerNav />

        <main id="main" class="flex-1">
            <div v-if="flashMessage" class="mx-auto w-full max-w-7xl px-4 pt-5 sm:px-6 lg:px-8">
                <Alert type="success">{{ flashMessage }}</Alert>
            </div>

            <slot />
        </main>

        <CustomerFooter />
    </div>
</template>
