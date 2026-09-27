<script setup>
import BrandLogo from '@/components/brand/BrandLogo.vue';
import { currentPromo, validityLabel } from '@/data/promos';
import { truncate } from '@/lib/format';

/**
 * Site footer.
 *
 * Ported from `partials/customer/footer.blade.php`. `PromoBanner::current()`
 * became the `currentPromo` computed.
 */
</script>

<template>
    <footer class="mt-auto border-t border-primary/10 bg-cream">
        <div v-if="currentPromo" class="border-b border-gold/30 bg-gold/10">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-3 px-4 py-2.5 text-center text-sm sm:px-6 lg:px-8">
                <span class="badge badge-gold">Promo</span>
                <span class="text-ink">
                    {{ currentPromo.title }} — {{ truncate(currentPromo.description, 90) }}
                </span>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-10 md:grid-cols-4">
                <div class="md:col-span-2">
                    <BrandLogo size="md" image="/images/logo.png" />
                </div>

                <div class="md:col-span-2">
                    <h4 class="mb-4 font-display text-sm font-semibold uppercase tracking-wider text-primary">Visit Us</h4>
                    <ul class="space-y-2.5 text-sm text-ink-muted">
                        <li>Balai ti Arjud, Glow &amp; Co. Beauty Lounge</li>
                        <li>Unit 4, 2 wins Bldg. Abra Kalinga Rd. Patucannay, Tayum, Abra</li>
                        <li>
                            <a href="tel:+639656244405" class="transition hover:text-primary">+63 965 6244 405</a>
                        </li>
                        <li>
                            <a href="mailto:hello@balaitiarjud.test" class="transition hover:text-primary">hello@balaitiarjud.test</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>
</template>
