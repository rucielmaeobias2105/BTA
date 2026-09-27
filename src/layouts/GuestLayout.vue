<script setup>
import BrandLogo from '@/components/brand/BrandLogo.vue';

/**
 * Auth shell.
 *
 * Ported from `layouts/guest.blade.php`. Each auth screen used to pass
 * `$panelTitle` / `$panelScript` / `$authImage` to customise the photo panel;
 * those are props here.
 */
defineProps({
    panelTitle: { type: String, default: 'Beauty, Relaxation,<br>and Hospitality' },
    panelScript: { type: String, default: 'in one elegant experience.' },
    authImage: { type: String, default: '/images/hero.jpg' },
});
</script>

<template>
    <div class="auth-page" :style="{ '--auth-visual-image': `url('${authImage}')` }">
        <div class="auth-visual">
            <div class="auth-brand">
                <img src="/images/tab_logo.png" alt="Balai ti Arjud" class="auth-brand-logo">

                <span class="auth-brand-title">Balai ti Arjud</span>
                <span class="auth-brand-sub">Glow &amp; Co. Beauty Lounge</span>

                <h2 class="auth-brand-heading" v-html="panelTitle" />
                <p class="auth-brand-script">{{ panelScript }}</p>
            </div>
        </div>

        <div class="auth-box">
            <div class="auth-form">
                <div class="mb-8 text-center lg:hidden">
                    <BrandLogo :to="{ name: 'home' }" size="md" />
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
