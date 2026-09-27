<script setup>
/**
 * 404 / catch-all.
 *
 * Stands in for the two places the PHP app would have called
 * `abort_unless($service->is_active, 404)` and for the router's
 * `:pathMatch(.*)*` entry.
 */
</script>

<template>
    <div class="mx-auto flex max-w-3xl flex-col items-center px-4 py-24 text-center sm:px-6">
        <p class="font-display text-7xl font-bold text-primary/20">404</p>

        <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-primary">Page not found</h1>

        <p class="mt-3 max-w-md text-sm leading-relaxed text-ink-muted">
            The page you were looking for has moved or no longer exists. Try heading back to
            the homepage or browsing our services.
        </p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <router-link :to="{ name: 'home' }" class="btn-primary">Back to Home</router-link>
            <router-link :to="{ name: 'services.index' }" class="btn-secondary">Browse Services</router-link>
        </div>
    </div>
</template>
