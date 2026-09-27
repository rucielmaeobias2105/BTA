<script setup>
import { computed, provide, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import FormTextarea from '@/components/ui/form/FormTextarea.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import {
    activeServices,
    defaultVariant,
    durationLabel,
    effectiveDuration,
    findService,
    serviceCategories,
    servicesByCategory,
} from '@/data/services';
import { appointments, appointmentsForUser, generateReferenceNumber, timeLabel } from '@/data/appointments';
import { AppointmentStatus, ChangedBy, DownPaymentStatus } from '@/data/enums';
import { stylists } from '@/data/admins';
import { expectedDownPaymentFor, salonSettings } from '@/data/settings';
import {
    availableSlots,
    blockedRanges,
    calendarMap,
    dateProblems,
    firstBookableDate,
    lastBookableDate,
    timeProblems,
} from '@/data/availability';
import { addDays, fromDateInput, toDateInput, today } from '@/lib/dates';
import { currentUser, setFlash } from '@/lib/session';
import { PHONE, createForm } from '@/lib/validation';
import { formatMoney } from '@/lib/format';

/**
 * Book Appointment (Customer Flow 5).
 *
 * Ported from `customer/appointments/create.blade.php`,
 * `Customer\AppointmentController` and `Customer\StoreBookingRequest`. The
 * selected-service basket, the variant/quantity controls and the read-only
 * summary are the former `bookingForm` Alpine component, and the
 * `withValidator()` hook is now `createForm`'s `after` callback. The
 * `/book/slots` JSON round-trip is a local computed over `availableSlots()`.
 */
const route = useRoute();
const router = useRouter();

/** The allergy checklist, exactly as the fieldset listed it. */
const ALLERGIES = ['Latex', 'Nickel', 'Fragrance', 'Shellfish / Marine', 'Hair Dye / Ammonia', 'Adhesive', 'None known'];

const downPaymentRequired = computed(() => Boolean(salonSettings.down_payment_required));
const downPaymentPercent = computed(() => Number(salonSettings.down_payment_percentage));

/* ------------------------------------------------------------------ */
/* Catalogue — the shape the Alpine basket worked with                  */
/* ------------------------------------------------------------------ */

const groupedServices = computed(() =>
    serviceCategories.value.map((category) => ({
        category,
        services: [...servicesByCategory(category)].sort((a, b) => a.name.localeCompare(b.name)),
    })),
);

const catalogue = computed(() =>
    Object.fromEntries(
        activeServices.value.map((service) => [
            service.id,
            {
                name: service.name,
                category: service.category,
                basePrice: Number(service.price),
                baseDuration: Number(service.duration_minutes),
                variants: service.variants.map((variant) => ({
                    id: variant.id,
                    name: variant.name,
                    price: Number(variant.price),
                    duration: effectiveDuration(service, variant),
                })),
            },
        ]),
    ),
);

/** `$service->variants->min('price') ?? $service->price` */
function cheapestPrice(service) {
    if (!service.variants.length) return Number(service.price);

    return Math.min(...service.variants.map((variant) => Number(variant.price)));
}

/* ------------------------------------------------------------------ */
/* Preselection — `AppointmentController::preselectFromRequest()`        */
/* ------------------------------------------------------------------ */

/** `?services[]=slug` arrives as an array, or as a single bare value. */
function requestedSlugs() {
    const raw = route.query.services;

    if (raw === undefined || raw === null) return [];

    return (Array.isArray(raw) ? raw : [raw]).map(String);
}

const initialSelection = computed(() => {
    const slugs = requestedSlugs();

    if (!slugs.length) return [];

    return groupedServices.value
        .flatMap((group) => group.services)
        .filter((service) => slugs.includes(service.slug) || slugs.includes(String(service.id)))
        .map((service) => {
            const variant = defaultVariant(service);

            return {
                service_id: service.id,
                service_variant_id: variant?.id ?? null,
                quantity: 1,
            };
        });
});

/* ------------------------------------------------------------------ */
/* The basket — the former `bookingForm` Alpine component                */
/* ------------------------------------------------------------------ */

const rows = ref([]);

function buildRow(serviceId, variantId, quantity) {
    const meta = catalogue.value[serviceId];

    if (!meta) return;

    const chosen = meta.variants.find((variant) => variant.id === Number(variantId)) ?? meta.variants[0] ?? null;

    rows.value.push({
        serviceId,
        name: chosen ? `${meta.name} (${chosen.name})` : meta.name,
        variants: meta.variants,
        variantId: chosen ? chosen.id : null,
        hasVariants: meta.variants.length > 1,
        price: chosen ? chosen.price : meta.basePrice,
        quantity: Number(quantity) || 1,
    });
}

function onVariantChange(row) {
    const meta = catalogue.value[row.serviceId];
    const chosen = row.variants.find((variant) => variant.id === Number(row.variantId));

    row.name = chosen ? `${meta.name} (${chosen.name})` : meta.name;
    row.price = chosen ? chosen.price : meta.basePrice;
}

function isSelected(serviceId) {
    return rows.value.some((row) => row.serviceId === Number(serviceId));
}

function toggle(serviceId, checked) {
    if (checked) {
        buildRow(serviceId, null, 1);
    } else {
        rows.value = rows.value.filter((row) => row.serviceId !== Number(serviceId));
    }
}

const selectedIds = computed(() => rows.value.map((row) => row.serviceId));

// Apply the preselection once the catalogue is available.
initialSelection.value.forEach((row) => buildRow(row.service_id, row.service_variant_id, row.quantity));

/** `BookingService::totalFor()` — rounds to 2dp like the PHP helper. */
const total = computed(
    () => Math.round(rows.value.reduce((sum, row) => sum + row.price * (Number(row.quantity) || 1), 0) * 100) / 100,
);

const expectedDownPayment = computed(() => expectedDownPaymentFor(total.value));

/* ------------------------------------------------------------------ */
/* Form                                                                */
/* ------------------------------------------------------------------ */

/** Default to the next day that actually has availability. */
function nextAvailableDate() {
    const last = lastBookableDate();

    for (let cursor = firstBookableDate(); cursor <= last; cursor = addDays(cursor, 1)) {
        if (availableSlots(cursor, null).length > 0) return toDateInput(cursor);
    }

    return toDateInput(today());
}

const prefilledDate = computed(() => String(route.query.date ?? '') || nextAvailableDate());

const form = createForm({
    initial: {
        services: [],
        customer_name: currentUser.value?.full_name ?? '',
        customer_phone: currentUser.value?.contact_number ?? '',
        preferred_date: prefilledDate.value,
        preferred_time: '',
        allergies: '',
        allergies_other: '',
        last_services_availed: '',
        last_services_availed_note: '',
        preferred_stylist_id: '',
        special_request: '',
        down_payment_reference: '',
        agree_terms: false,
    },
    rules: {
        services: ['required', 'array', 'min:1', 'max:10'],
        customer_name: ['required', 'string', 'max:150'],
        customer_phone: ['required', 'string', 'max:32', `regex:${PHONE.source}`],
        preferred_date: ['required', 'date'],
        preferred_time: ['required'],
        allergies: ['nullable', 'string', 'max:2000'],
        allergies_other: ['nullable', 'string', 'max:2000'],
        last_services_availed: ['nullable', 'string', 'max:2000'],
        last_services_availed_note: ['nullable', 'string', 'max:2000'],
        preferred_stylist_id: ['nullable', 'integer'],
        special_request: ['nullable', 'string', 'max:2000'],
        down_payment_reference: ['nullable', 'string', 'max:64'],
        agree_terms: ['accepted'],
    },
    messages: {
        regex: 'Please enter a valid phone number.',
        accepted: 'You must agree to the Terms and Conditions to book.',
    },
    after(errors, values) {
        // `withValidator()` re-checked the chosen slot against the calendar.
        const serviceId = values.services[0]?.service_id ?? null;
        const date = fromDateInput(values.preferred_date);

        // Laravel appended one entry per problem and Blade showed the first.
        const [dateProblem] = dateProblems(date, serviceId);
        const [timeProblem] = timeProblems(date, values.preferred_time, serviceId);

        if (dateProblem) errors.preferred_date = dateProblem;
        if (timeProblem) errors.preferred_time = timeProblem;

        // Down payment reference is mandatory when the salon requires it.
        if (salonSettings.down_payment_required && !String(values.down_payment_reference ?? '').trim()) {
            errors.down_payment_reference = 'A down payment GCash reference number is required to reserve your slot.';
        }
    },
});

provide('form-errors', form.errors);

const allergyChecks = ref([]);

/** The "Pick from your history" select, kept apart from the merged value. */
const historyPick = ref('');

/* ------------------------------------------------------------------ */
/* Slot lookup — the former `/book/slots` fetch                         */
/* ------------------------------------------------------------------ */

// The booking form's fetch never sent a `service_id`, so the lookup stayed
// service-agnostic here exactly as it was over HTTP.
const slots = computed(() => availableSlots(fromDateInput(form.values.preferred_date), null));

/** Blocked/closed flags for the date picker, keyed by `YYYY-MM-DD`. */
const calendar = computed(() => calendarMap(null));

const blockedHint = computed(() => {
    const entry = calendar.value[form.values.preferred_date];

    return entry?.blocked ? entry.label : null;
});

const closures = computed(() => blockedRanges(null));

const minDate = toDateInput(firstBookableDate());
const maxDate = toDateInput(lastBookableDate());

const history = computed(() =>
    appointmentsForUser(currentUser.value?.id).filter((row) => row.status === AppointmentStatus.Completed.value),
);

const historyOptions = computed(() => {
    const names = history.value.flatMap((row) => row.lines.map((line) => line.service_name));

    return Object.fromEntries([...new Set(names)].map((name) => [name, name]));
});

const stylistOptions = computed(() =>
    Object.fromEntries(stylists.value.map((stylist) => [stylist.id, stylist.full_name])),
);

const stylistName = computed(() => {
    if (!form.values.preferred_stylist_id) return 'No preference';

    return stylistOptions.value[form.values.preferred_stylist_id] || '—';
});

const dateTimeLabel = computed(() => {
    if (!form.values.preferred_date) return '—';

    const pretty = fromDateInput(form.values.preferred_date).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

    return form.values.preferred_time ? `${pretty} at ${timeLabel(form.values.preferred_time)}` : pretty;
});

/* ------------------------------------------------------------------ */
/* Submit                                                              */
/* ------------------------------------------------------------------ */

/**
 * `StoreBookingRequest::mergeNotes()` — fold the checkbox list and the
 * free-text note into the single column we store.
 */
function mergeNotes(checked, note) {
    const parts = [...new Set(checked.map((value) => String(value).trim()).filter(Boolean))];

    // "None known" alongside something else is contradictory — drop it.
    const kept = parts.length > 1 ? parts.filter((value) => value !== 'None known') : parts;

    const detail = String(note ?? '').trim();

    if (detail !== '') kept.push(detail);

    const merged = kept.filter(Boolean).join(', ').trim();

    return merged === '' ? null : merged;
}

/** `StoreBookingRequest::prepareForValidation()`. */
function prepare() {
    form.values.services = rows.value.map((row) => ({
        service_id: row.serviceId,
        service_variant_id: row.variantId,
        quantity: Number(row.quantity) || 1,
    }));

    form.values.customer_name = form.values.customer_name.trim();
    form.values.customer_phone = form.values.customer_phone.trim();
    form.values.down_payment_reference = form.values.down_payment_reference.trim().toUpperCase();
    form.values.allergies = mergeNotes(allergyChecks.value, form.values.allergies_other);
    form.values.last_services_availed = mergeNotes(
        historyPick.value ? [historyPick.value] : [],
        form.values.last_services_availed_note,
    );
}

/** Highest id already in use, so newly written rows keep the table unique. */
function nextIdFor(field) {
    return Math.max(0, ...appointments.flatMap((row) => row[field].map((entry) => entry.id))) + 1;
}

function submit() {
    prepare();

    if (!form.validate()) {
        // `createForm`'s message bag is keyed by rule, so the request's
        // field-specific wording for the service basket is applied here.
        if (form.errors.services) form.errors.services = 'Please select at least one service to book.';

        return;
    }

    let lineId = nextIdFor('lines');

    const lines = rows.value.map((row) => {
        const service = findService(row.serviceId);
        const variant = row.variants.find((entry) => entry.id === Number(row.variantId)) ?? null;

        return {
            id: lineId++,
            service_id: row.serviceId,
            service_variant_id: row.variantId,
            service_name: service?.name ?? '',
            variant_name: variant?.name ?? null,
            price: row.price,
            duration_minutes: effectiveDuration(service, variant),
            quantity: Number(row.quantity) || 1,
        };
    });

    const reference = generateReferenceNumber();
    const id = Math.max(0, ...appointments.map((row) => row.id)) + 1;

    appointments.unshift({
        id,
        reference_number: reference,
        user_id: currentUser.value?.id ?? null,
        customer_name: form.values.customer_name,
        customer_phone: form.values.customer_phone,
        customer_email: currentUser.value?.email ?? null,
        preferred_date: fromDateInput(form.values.preferred_date),
        preferred_time: form.values.preferred_time,
        allergies: form.values.allergies,
        last_services_availed: form.values.last_services_availed,
        preferred_stylist_id: form.values.preferred_stylist_id ? Number(form.values.preferred_stylist_id) : null,
        special_request: form.values.special_request,
        down_payment_reference: form.values.down_payment_reference || null,
        down_payment_amount: downPaymentRequired.value ? expectedDownPaymentFor(total.value) : null,
        // No payment gateway: an admin verifies the GCash reference by hand.
        down_payment_status:
            downPaymentRequired.value && form.values.down_payment_reference.trim()
                ? DownPaymentStatus.Unverified.value
                : DownPaymentStatus.NotRequired.value,
        total_amount: total.value,
        status: AppointmentStatus.Pending.value,
        source: 'web',
        admin_notes: null,
        cancellation_reason: null,
        reschedule_reason: null,
        cancelled_at: null,
        confirmed_at: null,
        completed_at: null,
        started_at: null,
        lines,
        history: [
            {
                id: nextIdFor('history'),
                from_status: null,
                to_status: AppointmentStatus.Pending.value,
                changed_by: ChangedBy.Customer.value,
                changed_by_id: currentUser.value?.id ?? null,
                changed_by_name: form.values.customer_name,
                note: 'Appointment submitted by customer.',
            },
        ],
        review: null,
    });

    setFlash(`Your booking request has been submitted. Reference: ${reference}`);

    router.push({ name: 'appointments.show', params: { id } });
}
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <PageHeader
            eyebrow="Reservations"
            title="Book Appointment"
            description="Choose your treatments, pick a date and time, and confirm your booking."
        />

        <ErrorSummary />

        <form novalidate @submit.prevent="submit">
            <div class="grid gap-8 lg:grid-cols-3">
                <!-- ================= LEFT: services + details ================= -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- 1. Selected service(s) -->
                    <CardPanel title="1. Selected Service" subtitle="Tap a service to add it to your booking.">
                        <EmptyState
                            v-if="groupedServices.length === 0"
                            title="No services available"
                            description="Please check back soon."
                        />

                        <div v-else class="space-y-6">
                            <div v-for="group in groupedServices" :key="group.category">
                                <p class="mb-2.5 text-xs font-semibold uppercase tracking-wider text-gold-dark">{{ group.category }}</p>

                                <div class="grid gap-2.5 sm:grid-cols-2">
                                    <label
                                        v-for="service in group.services"
                                        :key="service.id"
                                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-primary/15 bg-white/60 px-4 py-3 transition hover:border-gold hover:bg-linen/50"
                                        :class="isSelected(service.id) && '!border-primary !bg-primary/5'"
                                    >
                                        <input
                                            type="checkbox"
                                            class="checkbox"
                                            :value="service.id"
                                            :checked="isSelected(service.id)"
                                            @change="toggle(service.id, $event.target.checked)"
                                        >
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-ink">{{ service.name }}</span>
                                            <span class="block text-xs text-ink-muted">
                                                {{ durationLabel(service.duration_minutes) }} &middot;
                                                from {{ formatMoney(cheapestPrice(service)) }}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <p v-if="form.errors.services" class="input-error-text mt-3">{{ form.errors.services }}</p>
                    </CardPanel>

                    <!-- 2. Customer details -->
                    <CardPanel title="2. Your Details">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormInput
                                v-model="form.values.customer_name"
                                name="customer_name"
                                label="Full Name"
                                required
                            />
                            <FormInput
                                v-model="form.values.customer_phone"
                                name="customer_phone"
                                label="Phone Number"
                                required
                                placeholder="09XX XXX XXXX"
                            />
                        </div>

                        <p v-if="!currentUser" class="mt-4 rounded-xl bg-linen/70 px-4 py-3 text-xs text-ink-muted">
                            You are booking as a guest.
                            <router-link
                                :to="{ name: 'login' }"
                                class="font-medium text-primary underline underline-offset-2"
                            >Log in</router-link>
                            to track, reschedule or cancel this appointment later.
                        </p>
                    </CardPanel>

                    <!-- 3. Preferred date & time -->
                    <CardPanel title="3. Preferred Date & Time" subtitle="Only open days and available slots can be selected.">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="preferred_date" class="label">
                                    Preferred Date <span class="text-status-cancelled">*</span>
                                </label>

                                <input
                                    id="preferred_date"
                                    name="preferred_date"
                                    type="date"
                                    class="input"
                                    :class="form.errors.preferred_date ? 'input-error' : ''"
                                    :min="minDate"
                                    :max="maxDate"
                                    required
                                    :aria-invalid="form.errors.preferred_date ? 'true' : undefined"
                                    v-model="form.values.preferred_date"
                                >

                                <p v-if="form.errors.preferred_date" class="input-error-text">
                                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                    </svg>
                                    {{ form.errors.preferred_date }}
                                </p>

                                <p v-else-if="blockedHint" class="input-hint">{{ blockedHint }}</p>
                            </div>

                            <div>
                                <label for="preferred_time" class="label">
                                    Preferred Time <span class="text-status-cancelled">*</span>
                                </label>

                                <select
                                    id="preferred_time"
                                    name="preferred_time"
                                    class="input"
                                    :class="form.errors.preferred_time ? 'input-error' : ''"
                                    required
                                    v-model="form.values.preferred_time"
                                >
                                    <option value="">Select a time</option>
                                    <option v-for="slot in slots" :key="slot" :value="slot">{{ timeLabel(slot) }}</option>
                                </select>

                                <p v-if="slots.length === 0" class="input-hint">
                                    No open slots on this date. Please choose another day.
                                </p>

                                <p v-if="form.errors.preferred_time" class="input-error-text">
                                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-9-4a1 1 0 112 0v4a1 1 0 11-2 0V6zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                    </svg>
                                    {{ form.errors.preferred_time }}
                                </p>
                            </div>
                        </div>

                        <!-- Blocked / closed dates surfaced to the customer -->
                        <div
                            v-if="closures.length !== 0"
                            class="mt-5 rounded-xl border border-status-low-stock/25 bg-status-low-stock-bg/40 p-4"
                        >
                            <p class="flex items-center gap-2 text-sm font-semibold text-status-low-stock">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                Upcoming closure dates
                            </p>

                            <ul class="mt-2.5 space-y-1 text-xs text-ink">
                                <li v-for="block in closures" :key="`${block.label}-${block.service_id}`">
                                    <span class="font-medium text-primary">{{ block.label }}</span>
                                    — {{ block.service_id === null ? 'Salon closed' : `${block.service} unavailable` }}
                                    <span v-if="block.reason" class="text-ink-muted">({{ block.reason }})</span>
                                </li>
                            </ul>
                        </div>
                    </CardPanel>

                    <!-- 4. Preferences -->
                    <CardPanel title="4. Preferences & Health Notes">
                        <div class="space-y-4">
                            <fieldset>
                                <legend class="label">Allergies <span class="font-normal text-ink-muted">(select all that apply)</span></legend>

                                <div class="grid gap-2.5 sm:grid-cols-2">
                                    <label
                                        v-for="(allergy, index) in ALLERGIES"
                                        :key="allergy"
                                        class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-primary/15 bg-white/60 px-3.5 py-2.5 text-sm transition hover:border-gold"
                                    >
                                        <input
                                            type="checkbox"
                                            class="checkbox"
                                            :name="`allergy_${index}`"
                                            :value="allergy"
                                            v-model="allergyChecks"
                                        >
                                        <span class="text-ink">{{ allergy }}</span>
                                    </label>
                                </div>
                            </fieldset>

                            <FormTextarea
                                v-model="form.values.allergies_other"
                                name="allergies_other"
                                label="Allergy details"
                                :rows="2"
                                placeholder="Anything else we should know about your sensitivities?"
                            />
                        </div>
                    </CardPanel>

                    <!-- 5. Last services availed -->
                    <CardPanel title="5. Last Service(s) Availed" subtitle="Helps your therapist prepare ahead of time.">
                        <FormSelect
                            v-if="history.length !== 0"
                            v-model="historyPick"
                            name="last_services_availed"
                            label="Pick from your history"
                            include-blank
                            blank-label="Select a previous service"
                            :options="historyOptions"
                        />

                        <p v-else class="mb-4 text-sm text-ink-muted">No previous visits on record yet.</p>

                        <FormTextarea
                            v-model="form.values.last_services_availed_note"
                            name="last_services_availed_note"
                            label="Or tell us freely"
                            :rows="2"
                            placeholder="e.g. Colour &amp; Highlights last March, Glow Manicure last month"
                        />
                    </CardPanel>

                    <!-- 6. Stylist + special request -->
                    <CardPanel title="6. Preferred Stylist & Special Request">
                        <FormSelect
                            v-model="form.values.preferred_stylist_id"
                            name="preferred_stylist_id"
                            label="Preferred Stylist (optional)"
                            include-blank
                            blank-label="No preference"
                            :options="stylistOptions"
                        />

                        <div class="mt-4">
                            <FormTextarea
                                v-model="form.values.special_request"
                                name="special_request"
                                label="Special Request"
                                :rows="3"
                                placeholder="Quiet corner, extra time for consultation, specific products to avoid…"
                            />
                        </div>
                    </CardPanel>

                    <!-- 7. Down payment -->
                    <CardPanel title="7. Down Payment">
                        <div v-if="downPaymentRequired">
                            <div class="mb-4 rounded-xl bg-gold/10 px-4 py-3 text-sm text-ink">
                                A <span class="font-semibold text-primary">{{ downPaymentPercent }}%</span> down payment
                                is required to reserve your slot. Send via GCash and enter the reference number below.
                                <span class="mt-1 block text-xs text-ink-muted">
                                    We verify references manually — no online payment is processed on this site.
                                </span>
                            </div>

                            <FormInput
                                v-model="form.values.down_payment_reference"
                                name="down_payment_reference"
                                label="GCash Reference Number"
                                required
                                placeholder="e.g. 1234567890"
                                maxlength="64"
                            />

                            <p v-if="total > 0" class="mt-3 text-sm text-ink-muted">
                                Expected down payment:
                                <span class="font-semibold text-primary">{{ formatMoney(expectedDownPayment) }}</span>
                            </p>
                        </div>

                        <template v-else>
                            <p class="text-sm text-ink-muted">No down payment is required for this booking.</p>
                            <input type="hidden" name="down_payment_reference" value="">
                        </template>
                    </CardPanel>

                    <!-- 8. Terms -->
                    <CardPanel title="8. Terms & Conditions" accent="maroon">
                        <FormCheckbox v-model="form.values.agree_terms" name="agree_terms" :value="1" required>
                            <span>
                                I have read and agree to the
                                <router-link
                                    :to="{ name: 'terms.show', params: { category: 'booking' } }"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark"
                                >Booking Terms &amp; Conditions</router-link>
                                , the
                                <router-link
                                    :to="{ name: 'terms.show', params: { category: 'cancellation' } }"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark"
                                >Cancellation Policy</router-link>
                                and the
                                <router-link
                                    :to="{ name: 'terms.show', params: { category: 'rescheduling' } }"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark"
                                >Rescheduling Policy</router-link>.
                            </span>
                        </FormCheckbox>
                    </CardPanel>
                </div>

                <!-- ================= RIGHT: booking summary ================= -->
                <div class="lg:col-span-1">
                    <div class="bta-card overflow-hidden lg:sticky lg:top-28">
                        <div class="border-b border-primary/10 bg-primary px-5 py-4">
                            <h2 class="font-display text-lg font-semibold text-cream">Booking Summary</h2>
                            <p class="text-xs text-cream/70">Read-only summary</p>
                        </div>

                        <div class="space-y-4 p-5 text-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Customer</p>
                                <p class="mt-1 font-medium text-primary">{{ form.values.customer_name || '—' }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Service(s)</p>

                                <div v-if="selectedIds.length === 0" class="mt-1 text-ink-muted">No service selected yet.</div>

                                <ul v-else class="mt-1.5 space-y-2">
                                    <li
                                        v-for="(row, rowIndex) in rows"
                                        :key="`${row.serviceId}-${row.variantId ?? 'base'}`"
                                        class="rounded-lg bg-linen/60 px-3 py-2"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="min-w-0 flex-1 text-ink">{{ row.name }}</span>
                                            <span class="shrink-0 font-medium text-primary">{{ formatMoney(row.price * (Number(row.quantity) || 1)) }}</span>
                                        </div>
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <select
                                                v-if="row.hasVariants"
                                                class="rounded-lg border border-primary/15 bg-white px-2 py-1 text-[11px] text-ink"
                                                :value="row.variantId"
                                                @change="row.variantId = Number($event.target.value); onVariantChange(row)"
                                            >
                                                <option
                                                    v-for="variant in row.variants"
                                                    :key="variant.id"
                                                    :value="variant.id"
                                                >{{ variant.name }}</option>
                                            </select>

                                            <input
                                                v-model.number="row.quantity"
                                                type="number"
                                                min="1"
                                                max="10"
                                                :name="`services[${rowIndex}][quantity]`"
                                                class="w-16 rounded-lg border border-primary/15 bg-white px-2 py-1 text-[11px] text-ink"
                                            >
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Date &amp; Time</p>
                                <p class="mt-1 font-medium text-primary">{{ dateTimeLabel }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Preferred Stylist</p>
                                <p class="mt-1 font-medium text-primary">{{ stylistName }}</p>
                            </div>

                            <div class="bta-divider"></div>

                            <div class="flex items-baseline justify-between">
                                <span class="text-sm font-semibold text-ink">Total Amount</span>
                                <span class="font-display text-2xl font-bold text-primary">{{ formatMoney(total) }}</span>
                            </div>

                            <div v-if="expectedDownPayment > 0" class="flex items-baseline justify-between text-sm">
                                <span class="text-ink-muted">Down Payment (<span>{{ downPaymentPercent }}</span>%)</span>
                                <span class="font-semibold text-gold-dark">{{ formatMoney(expectedDownPayment) }}</span>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Down Payment Status</p>
                                <p class="mt-1">
                                    <span class="badge badge-pending">Awaiting Verification</span>
                                </p>
                                <p class="mt-1.5 text-[11px] leading-snug text-ink-muted">
                                    Verified manually by our team after you submit.
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Reference Number</p>
                                <p class="mt-1 text-ink-muted">Generated automatically on submit</p>
                            </div>
                        </div>

                        <div class="border-t border-primary/10 bg-linen/50 p-5">
                            <button type="submit" class="btn-primary w-full btn-lg" :disabled="selectedIds.length === 0">
                                Confirm Booking
                            </button>
                            <p class="mt-2.5 text-center text-[11px] text-ink-muted">
                                By confirming you agree to our booking terms.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
