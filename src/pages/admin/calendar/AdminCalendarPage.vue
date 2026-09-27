<script setup>
import { computed, provide, ref } from 'vue';
import { useRoute } from 'vue-router';
import CardPanel from '@/components/ui/CardPanel.vue';
import ErrorSummary from '@/components/ui/ErrorSummary.vue';
import FormCheckbox from '@/components/ui/form/FormCheckbox.vue';
import FormInput from '@/components/ui/form/FormInput.vue';
import FormSelect from '@/components/ui/form/FormSelect.vue';
import FormTextarea from '@/components/ui/form/FormTextarea.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { appointments } from '@/data/appointments';
import { firstBookableDate } from '@/data/availability';
import { activeServices } from '@/data/services';
import { AdminRole, AppointmentStatus, roleCan } from '@/data/enums';
import {
    DAY_NAMES,
    WEEKDAY_ORDER,
    blockedDates,
    blocksInMonth,
    isOpenOn,
    salonSettings,
} from '@/data/settings';
import { currentAdmin, setFlash } from '@/lib/session';
import { createForm } from '@/lib/validation';
import { addDays, daysBetween, fromDateInput, isWithin, startOfDay, toDateInput, today } from '@/lib/dates';
import { formatDate, plural } from '@/lib/format';

/**
 * Admin Flow 8 — Calendar & Blocked Dates.
 *
 * Ported from `admin/calendar/index.blade.php` and
 * `Admin\CalendarController`. The month used to arrive as a `?month=YYYY-MM`
 * query param; it is local state here, and the closures, the operating hours and
 * the blocked-date rows are all read from — and written back to — the reactive
 * settings module, which is what the customer booking form validates against.
 */
const route = useRoute();

const role = computed(() => currentAdmin.value?.role ?? AdminRole.SuperAdmin.value);
const canManage = computed(() => roleCan(role.value, 'calendar.manage'));

/* ------------------------------------------------------------------ */
/* Month grid                                                           */
/* ------------------------------------------------------------------ */

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** `?month=YYYY-MM`, defaulting to the current month, as `index()` did. */
const month = ref((() => {
    const requested = String(route.query.month ?? '');

    if (/^\d{4}-\d{2}$/.test(requested)) {
        const parsed = fromDateInput(`${requested}-01`);

        if (parsed) return parsed;
    }

    return today();
})());

const monthLabel = computed(() => formatDate(month.value, 'F Y'));

function shiftMonth(delta) {
    const next = new Date(month.value.getFullYear(), month.value.getMonth() + delta, 1);

    month.value = next;
}

/** The blocks touching the displayed month, oldest first. */
const blocked = computed(() =>
    [...blocksInMonth(month.value.getFullYear(), month.value.getMonth())].sort(
        (a, b) => startOfDay(a.start_date) - startOfDay(b.start_date),
    ),
);

/** `$closures` — the same map keyed by `Y-m-d`, holding every block on that day. */
const closures = computed(() => {
    const map = {};

    blockedDates.forEach((block) => {
        const span = daysBetween(block.start_date, block.end_date);

        for (let offset = 0; offset <= span; offset += 1) {
            const key = toDateInput(addDays(block.start_date, offset));

            (map[key] ??= []).push(block);
        }
    });

    return map;
});

/** The six-week grid: whole weeks around the month, Sunday first. */
const gridDays = computed(() => {
    const first = new Date(month.value.getFullYear(), month.value.getMonth(), 1);
    const last = new Date(month.value.getFullYear(), month.value.getMonth() + 1, 0);

    // Carbon's `startOfWeek()` landed on Monday while the header row started at
    // "Sun"; anchoring on Sunday is what makes the labels line up.
    const start = addDays(first, -first.getDay());
    const end = addDays(last, 6 - last.getDay());
    const days = [];

    for (let cursor = start; cursor <= end; cursor = addDays(cursor, 1)) {
        days.push(cursor);
    }

    return days;
});

/** `@class` for one day cell, unchanged from the Blade view. */
function dayClass(day) {
    const key = toDateInput(day);
    const dayBlocks = closures.value[key] ?? [];
    const isToday = key === toDateInput(today());
    const inMonth = day.getMonth() === month.value.getMonth();
    const isPast = startOfDay(day) < today();
    const open = isOpenOn(day);

    return {
        'relative flex aspect-square flex-col items-center justify-center rounded-xl border p-1 text-sm transition': true,
        'border-primary bg-primary text-cream': isToday,
        'border-primary/15 bg-cream text-ink': !isToday && inMonth && dayBlocks.length === 0 && open,
        'border-primary/10 bg-linen/50 text-ink-muted': !isToday && !inMonth,
        'border-status-cancelled/20 bg-status-cancelled-bg/50 text-ink-muted': dayBlocks.length > 0,
        'opacity-50': isPast,
    };
}

/** `BlockedDate::getRangeLabelAttribute()`. */
function rangeLabel(block) {
    const start = formatDate(block.start_date, 'M j, Y');

    return toDateInput(block.start_date) === toDateInput(block.end_date)
        ? start
        : `${start} – ${formatDate(block.end_date, 'M j, Y')}`;
}

/** `BlockedDate::getScopeLabelAttribute()`. */
function scopeLabel(block) {
    return block.service_name ?? 'All services';
}

/** `BlockedDate::appointments()` — still-active bookings inside the range. */
function affectedBookings(block) {
    return appointments.filter(
        (appointment) =>
            isWithin(appointment.preferred_date, block.start_date, block.end_date)
            && [AppointmentStatus.Pending.value, AppointmentStatus.Confirmed.value].includes(appointment.status),
    ).length;
}

/** `destroy()` asked for confirmation before removing the row. */
function unblock(block) {
    if (!window.confirm(`Unblock ${rangeLabel(block)}?`)) return;

    const index = blockedDates.indexOf(block);

    if (index !== -1) blockedDates.splice(index, 1);

    setFlash(`Unblocked ${rangeLabel(block)}.`);
}

/* ------------------------------------------------------------------ */
/* Block a date — `CalendarController::store()`                         */
/* ------------------------------------------------------------------ */

const blockForm = createForm({
    initial: {
        start_date: '',
        end_date: '',
        service_id: '',
        reason: '',
    },
    rules: {
        start_date: ['required', 'date'],
        end_date: ['nullable', 'date'],
        service_id: ['nullable'],
        reason: ['nullable', 'string', 'max:500'],
    },
    messages: {
        'end_date.after_or_equal': 'The end date cannot be before the start date.',
    },
    after(errors, values) {
        const start = fromDateInput(values.start_date);
        const end = values.end_date ? fromDateInput(values.end_date) : start;

        if (!start || !end) return;

        if (end < start) {
            errors.end_date = 'The end date cannot be before the start date.';

            return;
        }

        if (daysBetween(start, end) > 365) {
            errors.end_date = 'You cannot block more than one year at a time.';

            return;
        }

        const scope = values.service_id ? String(values.service_id) : null;

        const duplicate = blockedDates.some(
            (block) =>
                String(block.service_id ?? '') === scope
                && startOfDay(block.start_date) <= end
                && startOfDay(block.end_date) >= start,
        );

        if (duplicate) errors.start_date = 'That range is already blocked for this scope.';
    },
});

provide('form-errors', blockForm.errors);

const firstBookable = toDateInput(firstBookableDate());

/** `Service::active()->orderBy('name')`, as the scope select received it. */
const serviceOptions = computed(() =>
    Object.fromEntries([...activeServices.value].sort((a, b) => a.name.localeCompare(b.name)).map((service) => [service.id, service.name])),
);

function blockDates() {
    if (!blockForm.validate()) return;

    const start = fromDateInput(blockForm.values.start_date);
    const end = blockForm.values.end_date ? fromDateInput(blockForm.values.end_date) : start;
    const service = serviceOptions.value[blockForm.values.service_id] ?? null;

    blockedDates.push({
        id: Math.max(0, ...blockedDates.map((block) => Number(block.id) || 0)) + 1,
        start_date: start,
        end_date: end,
        service_id: blockForm.values.service_id === '' ? null : Number(blockForm.values.service_id),
        service_name: service,
        reason: blockForm.values.reason ? String(blockForm.values.reason).trim() : null,
        created_by: currentAdmin.value?.id ?? null,
    });

    const single = toDateInput(start) === toDateInput(end);

    blockForm.reset({ start_date: '', end_date: '', service_id: '', reason: '' });

    setFlash(`Blocked ${formatDate(start, 'M j, Y')}${single ? '' : ` – ${formatDate(end, 'M j, Y')}`}.`);
}

/* ------------------------------------------------------------------ */
/* Operating hours & rules — `CalendarController::updateSettings()`      */
/* ------------------------------------------------------------------ */

const settingsForm = createForm({
    initial: {
        name: salonSettings.name,
        address: salonSettings.address ?? '',
        phone: salonSettings.phone ?? '',
        email: salonSettings.email ?? '',
        slot_interval_minutes: String(salonSettings.slot_interval_minutes),
        booking_lead_days: String(salonSettings.booking_lead_days),
        down_payment_required: salonSettings.down_payment_required,
        down_payment_percentage: String(salonSettings.down_payment_percentage),
    },
    rules: {
        name: ['required', 'string', 'max:150'],
        address: ['nullable', 'string', 'max:255'],
        phone: ['nullable', 'string', 'max:32'],
        email: ['nullable', 'email', 'max:255'],
        slot_interval_minutes: ['required', 'integer', 'min:15', 'max:240'],
        booking_lead_days: ['required', 'integer', 'min:1', 'max:365'],
        down_payment_required: ['nullable'],
        down_payment_percentage: ['required', 'integer', 'min:0', 'max:100'],
    },
});

/** The hours grid edits `salonSettings.operating_hours` directly. */
function hoursFor(key) {
    return salonSettings.operating_hours[key] ?? ['', ''];
}

function saveSettings() {
    if (!settingsForm.validate()) return;

    // Only a day with both ends filled, and a close after the open, counts as
    // open — everything else is dropped, which is how a day gets closed.
    const hours = {};

    WEEKDAY_ORDER.forEach((key) => {
        const [open, close] = hoursFor(key);

        if (open && close && close > open) hours[key] = [open, close];
    });

    if (Object.keys(hours).length === 0) {
        settingsForm.errors.hours = 'Set operating hours for at least one day.';

        return;
    }

    salonSettings.name = settingsForm.values.name;
    salonSettings.address = settingsForm.values.address || null;
    salonSettings.phone = settingsForm.values.phone || null;
    salonSettings.email = settingsForm.values.email || null;
    salonSettings.slot_interval_minutes = Number(settingsForm.values.slot_interval_minutes);
    salonSettings.booking_lead_days = Number(settingsForm.values.booking_lead_days);
    salonSettings.down_payment_required = Boolean(settingsForm.values.down_payment_required);
    salonSettings.down_payment_percentage = Number(settingsForm.values.down_payment_percentage);

    Object.keys(salonSettings.operating_hours).forEach((key) => delete salonSettings.operating_hours[key]);
    Object.assign(salonSettings.operating_hours, hours);

    setFlash('Salon settings updated.');
}
</script>

<template>
    <div>
        <PageHeader
            eyebrow="Availability"
            title="Calendar &amp; Blocked Dates"
            description="Block single dates or ranges. Customers cannot book on these dates from the booking form."
        />

        <ErrorSummary />

        <div class="grid gap-6 2xl:grid-cols-3">
            <!-- Calendar grid -->
            <div class="2xl:col-span-2">
                <CardPanel :padded="false">
                    <template #actions>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-primary/15 text-primary transition hover:bg-linen"
                                aria-label="Previous month"
                                @click="shiftMonth(-1)"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                            </button>
                            <span class="min-w-36 text-center font-display text-sm font-semibold text-primary">{{ monthLabel }}</span>
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg border border-primary/15 text-primary transition hover:bg-linen"
                                aria-label="Next month"
                                @click="shiftMonth(1)"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            </button>
                        </div>
                    </template>

                    <div class="grid grid-cols-7 gap-1 text-center">
                        <div
                            v-for="label in WEEKDAY_LABELS"
                            :key="label"
                            class="pb-2 text-[11px] font-semibold uppercase tracking-wider text-ink-muted"
                        >{{ label }}</div>

                        <div v-for="day in gridDays" :key="toDateInput(day)" :class="dayClass(day)">
                            <span class="font-medium">{{ day.getDate() }}</span>

                            <template v-if="(closures[toDateInput(day)] ?? []).length > 0">
                                <span class="mt-0.5 flex items-center gap-0.5">
                                    <span
                                        v-for="(block, index) in (closures[toDateInput(day)] ?? []).slice(0, 3)"
                                        :key="`${block.id}-${index}`"
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="block.service_id === null ? 'bg-primary' : 'bg-gold'"
                                        :title="`${scopeLabel(block)} — ${block.reason ?? ''}`"
                                    />
                                </span>
                            </template>
                            <span v-else-if="!isOpenOn(day)" class="text-[9px] uppercase text-ink-muted">Closed</span>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-4 border-t border-primary/10 pt-4 text-xs text-ink-muted">
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-primary"></span>
                            Today
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-primary/30"></span>
                            Open
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-primary"></span>
                            Salon-wide block
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-gold"></span>
                            Service-specific block
                        </span>
                    </div>
                </CardPanel>

                <!-- Blocked dates list -->
                <CardPanel title="Blocked Dates" subtitle="Active and upcoming closures." class="mt-6">
                    <p v-if="blocked.length === 0" class="text-sm text-ink-muted">No dates blocked in this month.</p>

                    <ul v-else class="space-y-2.5">
                        <li
                            v-for="block in blocked"
                            :key="block.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/12 bg-linen/50 px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-primary">{{ rangeLabel(block) }}</p>
                                <p class="text-xs text-ink-muted">
                                    {{ scopeLabel(block) }}
                                    <template v-if="block.reason">&middot; {{ block.reason }}</template>
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span v-if="affectedBookings(block) > 0" class="badge badge-pending">
                                    {{ affectedBookings(block) }} booking{{ affectedBookings(block) === 1 ? '' : 's' }}
                                </span>
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="btn-danger btn-sm"
                                    @click="unblock(block)"
                                >Unblock</button>
                            </div>
                        </li>
                    </ul>
                </CardPanel>
            </div>

            <!-- Sidebar: add block + settings. Both are write screens, so a
                 read-only role (Staff) does not see them at all. -->
            <aside v-if="canManage" class="space-y-6">
                <CardPanel title="Block a Date" subtitle="Single day or a range.">
                    <form class="space-y-4" novalidate @submit.prevent="blockDates">
                        <div>
                            <label for="start_date" class="label">
                                Start Date <span class="text-status-cancelled">*</span>
                            </label>
                            <input
                                id="start_date"
                                name="start_date"
                                type="date"
                                class="input"
                                :class="blockForm.errors.start_date ? 'input-error' : ''"
                                :min="firstBookable"
                                :aria-invalid="blockForm.errors.start_date ? 'true' : undefined"
                                v-model="blockForm.values.start_date"
                            >
                            <p v-if="blockForm.errors.start_date" class="input-error-text">
                                {{ blockForm.errors.start_date }}
                            </p>
                        </div>

                        <div>
                            <label for="end_date" class="label">End Date (optional)</label>
                            <input
                                id="end_date"
                                name="end_date"
                                type="date"
                                class="input"
                                :class="blockForm.errors.end_date ? 'input-error' : ''"
                                :min="blockForm.values.start_date || firstBookable"
                                :aria-invalid="blockForm.errors.end_date ? 'true' : undefined"
                                v-model="blockForm.values.end_date"
                            >
                            <p v-if="blockForm.errors.end_date" class="input-error-text">
                                {{ blockForm.errors.end_date }}
                            </p>
                            <p class="input-hint">Leave blank to block a single day.</p>
                        </div>

                        <FormSelect
                            v-model="blockForm.values.service_id"
                            name="service_id"
                            label="Scope"
                            include-blank
                            blank-label="All services (salon-wide)"
                            :options="serviceOptions"
                        />

                        <FormTextarea
                            v-model="blockForm.values.reason"
                            name="reason"
                            label="Reason (internal)"
                            :rows="2"
                            placeholder="e.g. Provincial holiday, stock delivery"
                        />

                        <button type="submit" class="btn-primary w-full">Block Date(s)</button>
                    </form>
                </CardPanel>

                <CardPanel title="Operating Hours &amp; Rules">
                    <form class="space-y-4" novalidate @submit.prevent="saveSettings">
                        <ErrorSummary :errors="settingsForm.errors" />

                        <FormInput
                            v-model="settingsForm.values.name"
                            name="name"
                            label="Salon Name"
                            required
                            :error="settingsForm.errors.name ?? null"
                        />
                        <FormInput
                            v-model="settingsForm.values.address"
                            name="address"
                            label="Address"
                            :error="settingsForm.errors.address ?? null"
                        />
                        <FormInput
                            v-model="settingsForm.values.phone"
                            name="phone"
                            label="Phone"
                            :error="settingsForm.errors.phone ?? null"
                        />
                        <FormInput
                            v-model="settingsForm.values.email"
                            name="email"
                            type="email"
                            label="Email"
                            :error="settingsForm.errors.email ?? null"
                        />

                        <div class="bta-divider"></div>

                        <div class="space-y-2.5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Opening Hours</p>

                            <div
                                v-for="key in WEEKDAY_ORDER"
                                :key="key"
                                class="grid grid-cols-[1fr_auto_auto_1fr] items-center gap-2"
                            >
                                <span class="text-sm text-ink">{{ DAY_NAMES[key] }}</span>
                                <input
                                    type="time"
                                    class="input w-24 px-2 py-1.5 text-xs"
                                    :aria-label="`${DAY_NAMES[key]} opening time`"
                                    v-model="salonSettings.operating_hours[key][0]"
                                >
                                <span class="text-ink-muted">–</span>
                                <input
                                    type="time"
                                    class="input w-24 px-2 py-1.5 text-xs"
                                    :aria-label="`${DAY_NAMES[key]} closing time`"
                                    v-model="salonSettings.operating_hours[key][1]"
                                >
                            </div>

                            <p class="input-hint">Leave both times blank to mark a day closed.</p>
                        </div>

                        <div class="bta-divider"></div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="slot_interval_minutes" class="label">
                                    Slot Interval (min) <span class="text-status-cancelled">*</span>
                                </label>
                                <input
                                    id="slot_interval_minutes"
                                    name="slot_interval_minutes"
                                    type="number"
                                    min="15"
                                    max="240"
                                    step="5"
                                    class="input"
                                    :class="settingsForm.errors.slot_interval_minutes ? 'input-error' : ''"
                                    v-model="settingsForm.values.slot_interval_minutes"
                                >
                                <p v-if="settingsForm.errors.slot_interval_minutes" class="input-error-text">
                                    {{ settingsForm.errors.slot_interval_minutes }}
                                </p>
                            </div>

                            <div>
                                <label for="booking_lead_days" class="label">
                                    Booking Horizon (days) <span class="text-status-cancelled">*</span>
                                </label>
                                <input
                                    id="booking_lead_days"
                                    name="booking_lead_days"
                                    type="number"
                                    min="1"
                                    max="365"
                                    class="input"
                                    :class="settingsForm.errors.booking_lead_days ? 'input-error' : ''"
                                    v-model="settingsForm.values.booking_lead_days"
                                >
                                <p v-if="settingsForm.errors.booking_lead_days" class="input-error-text">
                                    {{ settingsForm.errors.booking_lead_days }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormCheckbox
                                v-model="settingsForm.values.down_payment_required"
                                name="down_payment_required"
                                label="Require down payment"
                                :error="settingsForm.errors.down_payment_required ?? null"
                            />
                            <div>
                                <label for="down_payment_percentage" class="label">
                                    Down Payment (%) <span class="text-status-cancelled">*</span>
                                </label>
                                <input
                                    id="down_payment_percentage"
                                    name="down_payment_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="input"
                                    :class="settingsForm.errors.down_payment_percentage ? 'input-error' : ''"
                                    v-model="settingsForm.values.down_payment_percentage"
                                >
                                <p v-if="settingsForm.errors.down_payment_percentage" class="input-error-text">
                                    {{ settingsForm.errors.down_payment_percentage }}
                                </p>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary w-full">Save Settings</button>
                    </form>
                </CardPanel>
            </aside>
        </div>
    </div>
</template>
