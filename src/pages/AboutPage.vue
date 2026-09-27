<script setup>
import CardPanel from '@/components/ui/CardPanel.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import { DAY_NAMES, WEEKDAY_ORDER, salonSettings } from '@/data/settings';
import { serviceCategories } from '@/data/services';

/**
 * About page.
 *
 * Ported from `about.blade.php`. The `$settings->operating_hours` loop over
 * `SalonSetting::dayNames()` became the `WEEKDAY_ORDER` / `DAY_NAMES` map, and
 * the heroicon names in the feature list resolved through `AppIcon`.
 */
const PILLARS = [
    {
        title: 'Professional Therapists',
        body: 'Trained in the latest techniques across hair, nails, lashes and skin.',
        icon: 'academic-cap',
    },
    {
        title: 'Safe & Clean Environment',
        body: 'Tools are sanitised between every client and surfaces are wiped down regularly.',
        icon: 'shield-check',
    },
    {
        title: 'Quality Products',
        body: 'Professional-grade brands in every category, from colour to cleanser.',
        icon: 'sparkles',
    },
    {
        title: 'Relax · Rejuvenate · Feel Beautiful',
        body: 'Every treatment is designed around how you want to feel afterwards.',
        icon: 'heart',
    },
];

const categories = serviceCategories;

function hoursLabel(key) {
    const hours = salonSettings.operating_hours[key];

    return hours ? `${hours[0].slice(0, 5)} – ${hours[1].slice(0, 5)}` : 'Closed';
}
</script>

<template>
    <div>
        <section class="relative overflow-hidden bg-primary">
            <img
                src="/images/10.jpg"
                alt="Balai ti Arjud interior"
                class="absolute inset-0 h-full w-full object-cover opacity-25"
            >

            <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold">About Us</p>
                <h1 class="mt-3 max-w-3xl font-display text-4xl font-bold leading-tight tracking-tight text-cream sm:text-5xl">
                    Beauty meets hospitality in the heart of Abra
                </h1>
                <p class="mt-4 max-w-2xl text-sm leading-relaxed text-cream/80">
                    Balai ti Arjud — Glow &amp; Co. Beauty Lounge is a relaxing retreat where skilled
                    therapists, quality products and genuine care come together.
                </p>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-dark">Our Story</p>
                    <h2 class="mt-3 font-display text-3xl font-bold tracking-tight text-primary">
                        A salon built around how you should feel
                    </h2>

                    <div class="mt-5 space-y-4 text-sm leading-relaxed text-ink-muted">
                        <p>
                            What started as a small neighbourhood salon has grown into a full-service beauty
                            lounge offering hair, nails, lashes, facials, massage and spa treatments — all
                            under one roof.
                        </p>
                        <p>
                            We believe a beauty appointment should feel like a break from a busy week, not
                            another task to get through. That means unhurried consultations, therapists who
                            listen, and products we genuinely stand behind.
                        </p>
                        <p>
                            Booking online is our way of making that easier: reserve your slot, tell us about
                            any sensitivities, and arrive to a chair that's already prepared for you.
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-for="pillar in PILLARS" :key="pillar.title" class="bta-card p-6">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                            <AppIcon :name="pillar.icon" class="h-5 w-5" />
                        </span>
                        <h3 class="mt-4 font-display text-base font-semibold text-primary">{{ pillar.title }}</h3>
                        <p class="mt-1.5 text-sm text-ink-muted">{{ pillar.body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="categories.length" class="border-y border-primary/10 bg-cream">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-dark">What We Offer</p>
                    <h2 class="mt-3 font-display text-3xl font-bold tracking-tight text-primary">Explore our services</h2>
                </div>

                <div class="mt-9 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <router-link
                        v-for="category in categories"
                        :key="category"
                        :to="{ name: 'services.refined', query: { category } }"
                        class="group flex items-center justify-between gap-3 rounded-card border border-primary/10 bg-linen/40 px-5 py-4 transition hover:border-gold hover:bg-gold/10"
                    >
                        <span class="font-medium text-primary">{{ category }}</span>
                        <svg class="h-4 w-4 shrink-0 text-gold-dark transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </router-link>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-2">
                <CardPanel title="Opening Hours" subtitle="Appointments must fall within these hours.">
                    <dl class="space-y-2.5 text-sm">
                        <div
                            v-for="key in WEEKDAY_ORDER"
                            :key="key"
                            class="flex items-center justify-between gap-3 border-b border-primary/5 pb-2.5 last:border-0 last:pb-0"
                        >
                            <dt class="text-ink-muted">{{ DAY_NAMES[key] }}</dt>
                            <dd class="font-medium text-primary">{{ hoursLabel(key) }}</dd>
                        </div>
                    </dl>
                </CardPanel>

                <div class="flex flex-col justify-center rounded-card bg-primary p-8 text-center">
                    <h2 class="font-display text-2xl font-bold tracking-tight text-cream">Come visit us</h2>
                    <p class="mt-3 text-sm leading-relaxed text-cream/80">
                        {{ salonSettings.address }}<br>
                        <a :href="`tel:${salonSettings.phone}`" class="underline underline-offset-2 hover:text-gold">{{ salonSettings.phone }}</a>
                    </p>

                    <div class="mt-7 flex flex-wrap justify-center gap-3">
                        <router-link
                            :to="{ name: 'appointments.create' }"
                            class="rounded-pill bg-cream px-6 py-3 text-sm font-medium text-primary transition hover:bg-gold-light"
                        >
                            Book an Appointment
                        </router-link>
                        <router-link
                            :to="{ name: 'contact.create' }"
                            class="rounded-pill border border-cream/50 px-6 py-3 text-sm font-medium text-cream transition hover:bg-cream/10"
                        >
                            Contact Us
                        </router-link>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
