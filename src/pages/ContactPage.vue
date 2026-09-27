<script setup>
import { computed, provide, ref } from 'vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import CardPanel from '@/components/ui/CardPanel.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import FormTextarea from '@/components/ui/form/FormTextarea.vue';
import { DAY_NAMES, WEEKDAY_ORDER, salonSettings } from '@/data/settings';
import { INQUIRY_TOPIC_VALUES, InquiryTopic } from '@/data/enums';
import { contactMessages } from '@/data/messages';
import { currentUser, setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';

/**
 * Contact form.
 *
 * Ported from `contact.blade.php` and `Customer\StoreContactMessageRequest`.
 * The POST is now a local push onto the demo `contactMessages` collection,
 * followed by the same `session('status')` flash the controller set.
 */
const form = createForm({
    initial: {
        name: currentUser.value?.full_name ?? '',
        email: currentUser.value?.email ?? '',
        topic: '',
        message: '',
    },
    rules: {
        name: ['required', 'string', 'max:150'],
        email: ['required', 'string', 'email', 'max:255'],
        topic: ['required', 'string', `in:${INQUIRY_TOPIC_VALUES.join(',')}`],
        message: ['required', 'string', 'min:10', 'max:3000'],
    },
});

// Lets the form components read their error without every call site threading
// the bag through by hand — the replacement for Blade's global `$errors`.
provide('form-errors', form.errors);

const submitting = ref(false);

const topics = Object.fromEntries(
    INQUIRY_TOPIC_VALUES.map((value) => [value, InquiryTopic[value].label]),
);

function hoursLabel(key) {
    const hours = salonSettings.operating_hours[key];

    return hours ? `${hours[0].slice(0, 5)} – ${hours[1].slice(0, 5)}` : 'Closed';
}

const submitted = computed(() => submitting.value);

function submit() {
    if (!form.validate()) return;

    submitting.value = true;

    contactMessages.unshift({
        id: Math.max(0, ...contactMessages.map((row) => row.id)) + 1,
        name: form.values.name.trim(),
        email: form.values.email.toLowerCase().trim(),
        topic: form.values.topic,
        message: form.values.message,
        is_read: false,
        admin_reply: null,
        replied_at: null,
        created_at: new Date(),
    });

    form.reset({ name: '', email: '', topic: '', message: '' });
    submitting.value = false;

    setFlash('Thank you! Your message has been received and we will get back to you shortly.');
}
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <PageHeader
            eyebrow="Get in Touch"
            title="Contact Us"
            description="Have a question about our services, pricing or bookings? Send us a message and we'll reply as soon as we can."
        />

        <ErrorSummary />

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <form class="bta-card p-6 sm:p-8" novalidate @submit.prevent="submit">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormInput v-model="form.values.name" name="name" label="Name" required />
                        <FormInput v-model="form.values.email" name="email" type="email" label="Email" required />
                    </div>

                    <div class="mt-5">
                        <FormSelect
                            v-model="form.values.topic"
                            name="topic"
                            label="Inquiry Topic"
                            required
                            include-blank
                            blank-label="Select a topic"
                            :options="topics"
                        />
                    </div>

                    <div class="mt-5">
                        <FormTextarea
                            v-model="form.values.message"
                            name="message"
                            label="Message"
                            required
                            :rows="6"
                            placeholder="How can we help?"
                            hint="Please include as much detail as you can so we can assist you properly."
                        />
                    </div>

                    <button type="submit" class="btn-primary mt-6" :disabled="submitted">
                        Send Message
                    </button>
                </form>
            </div>

            <aside class="space-y-5">
                <CardPanel title="Visit Us">
                    <ul class="space-y-4 text-sm">
                        <li class="flex gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                                <svg style="width: 1.1rem; height: 1.1rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                            </span>
                            <span>
                                <span class="block font-medium text-primary">Balai ti Arjud</span>
                                <span class="text-ink-muted">Glow &amp; Co. Beauty Lounge<br>Purok 5, Abra, Philippines</span>
                            </span>
                        </li>

                        <li class="flex gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                                <svg style="width: 1.1rem; height: 1.1rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                </svg>
                            </span>
                            <span>
                                <span class="block font-medium text-primary">Phone</span>
                                <a href="tel:+639000000000" class="text-ink-muted transition hover:text-primary">+63 900 000 0000</a>
                            </span>
                        </li>

                        <li class="flex gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                                <svg style="width: 1.1rem; height: 1.1rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                            </span>
                            <span>
                                <span class="block font-medium text-primary">Email</span>
                                <a href="mailto:hello@balaitiarjud.test" class="text-ink-muted transition hover:text-primary">hello@balaitiarjud.test</a>
                            </span>
                        </li>
                    </ul>
                </CardPanel>

                <CardPanel title="Opening Hours">
                    <dl class="space-y-2.5 text-sm">
                        <div v-for="key in WEEKDAY_ORDER" :key="key" class="flex items-center justify-between gap-3">
                            <dt class="text-ink-muted">{{ DAY_NAMES[key] }}</dt>
                            <dd class="font-medium text-primary">{{ hoursLabel(key) }}</dd>
                        </div>
                    </dl>
                </CardPanel>

                <div class="rounded-card bg-primary p-6 text-center">
                    <p class="font-display text-lg font-semibold text-cream">Ready to book?</p>
                    <p class="mt-1.5 text-sm text-cream/75">Skip the wait — reserve your slot online in under a minute.</p>
                    <router-link
                        :to="{ name: 'appointments.create' }"
                        class="mt-4 inline-flex rounded-pill bg-cream px-5 py-2.5 text-sm font-medium text-primary transition hover:bg-gold-light"
                    >
                        Book an Appointment
                    </router-link>
                </div>
            </aside>
        </div>
    </div>
</template>
