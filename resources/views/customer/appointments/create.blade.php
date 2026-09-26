{{--
    Book Appointment (Customer Flow 5).

    The selected-service basket is held in Alpine state and mirrored into
    hidden inputs on submit, so the summary total stays live without a
    round-trip. Server-side validation in StoreBookingRequest is the real
    gate; everything here is a client-side convenience.
--}}
@extends('layouts.customer')

@section('title', 'Book Appointment')

@section('content')
    @php
        /** Catalogue shape Alpine works with: id => [name, price, duration, category, variants, defaultVariant]. */
        $catalogue = $groupedServices
            ->flatten()
            ->mapWithKeys(fn ($service) => [
                $service->id => [
                    'name' => $service->name,
                    'category' => $service->category,
                    'basePrice' => (float) $service->price,
                    'baseDuration' => (int) $service->duration_minutes,
                    'variants' => $service->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                        'price' => (float) $v->price,
                        'duration' => (int) $v->effectiveDuration(),
                    ])->values(),
                ],
            ]);

        $initialSelection = collect($selected)->map(fn ($row) => [
            'service_id' => (int) $row['service_id'],
            'service_variant_id' => $row['service_variant_id'] ? (int) $row['service_variant_id'] : null,
            'quantity' => (int) $row['quantity'],
        ])->values();

        $minDate = $availability->firstBookableDate()->toDateString();
        $maxDate = $availability->lastBookableDate()->toDateString();
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow="Reservations"
            title="Book Appointment"
            description="Choose your treatments, pick a date and time, and confirm your booking."
        />

        <x-ui.alert type="error" class="mb-6" :dismissible="false">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>

        <form
            method="POST"
            action="{{ route('appointments.store') }}"
            enctype="multipart/form-data"
            x-data="bookingForm"
            novalidate
        >
            @csrf

            <input type="hidden" name="allergies" :value="allergies.join(', ')" x-cloak>

            <div class="grid gap-8 lg:grid-cols-3">
                {{-- ================= LEFT: services + details ================= --}}
                <div class="space-y-6 lg:col-span-2">
                    {{-- 1. Selected service(s) --}}
                    <x-ui.card title="1. Selected Service" subtitle="Tap a service to add it to your booking.">
                        @if ($groupedServices->isEmpty())
                            <x-ui.empty title="No services available" description="Please check back soon." />
                        @else
                            <div class="space-y-6">
                                @foreach ($groupedServices as $category => $services)
                                    <div>
                                        <p class="mb-2.5 text-xs font-semibold uppercase tracking-wider text-gold-dark">{{ $category }}</p>

                                        <div class="grid gap-2.5 sm:grid-cols-2">
                                            @foreach ($services as $service)
                                                @php
                                                    $cheapest = $service->variants->min('price') ?? $service->price;
                                                @endphp
                                                <label
                                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-primary/15 bg-white/60 px-4 py-3 transition hover:border-gold hover:bg-linen/50"
                                                    :class="isSelected({{ $service->id }}) && '!border-primary !bg-primary/5'"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        class="checkbox"
                                                        value="{{ $service->id }}"
                                                        x-model="selectedIds"
                                                        @change="toggle({{ $service->id }}, $event.target.checked)"
                                                    >
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm font-medium text-ink">{{ $service->name }}</span>
                                                        <span class="block text-xs text-ink-muted">
                                                            {{ $service->duration_label }} &middot;
                                                            from ₱{{ number_format((float) $cheapest, 2) }}
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @error('services')
                            <p class="input-error-text mt-3">{{ $message }}</p>
                        @enderror
                        @error('services.*')
                            <p class="input-error-text mt-3">{{ $message }}</p>
                        @enderror
                    </x-ui.card>

                    {{-- 2. Customer details --}}
                    <x-ui.card title="2. Your Details">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.form.input
                                name="customer_name"
                                label="Full Name"
                                required
                                autocomplete="name"
                                :value="auth()->user()?->full_name"
                            />
                            <x-ui.form.input
                                name="customer_phone"
                                label="Phone Number"
                                required
                                autocomplete="tel"
                                placeholder="09XX XXX XXXX"
                                :value="auth()->user()?->contact_number"
                            />
                        </div>

                        @guest
                            <p class="mt-4 rounded-xl bg-linen/70 px-4 py-3 text-xs text-ink-muted">
                                You are booking as a guest. <a href="{{ route('login') }}" class="font-medium text-primary underline underline-offset-2">Log in</a>
                                to track, reschedule or cancel this appointment later.
                            </p>
                        @endguest
                    </x-ui.card>

                    {{-- 3. Preferred date & time --}}
                    <x-ui.card title="3. Preferred Date & Time" subtitle="Only open days and available slots can be selected.">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.form.input
                                name="preferred_date"
                                type="date"
                                label="Preferred Date"
                                required
                                :value="old('preferred_date', $prefilledDate)"
                                :min="$minDate"
                                :max="$maxDate"
                                x-model="date"
                            />

                            <div>
                                <label for="preferred_time" class="label">
                                    Preferred Time <span class="text-status-cancelled">*</span>
                                </label>

                                <select id="preferred_time" name="preferred_time" class="input" required x-model="time">
                                    <option value="">Select a time</option>
                                    <template x-for="slot in slots" :key="slot">
                                        <option :value="slot" x-text="formatTime(slot)"></option>
                                    </template>
                                </select>

                                <p class="input-hint" x-show="loadingSlots">Checking availability…</p>
                                <p class="input-hint" x-show="! loadingSlots && slots.length === 0" x-cloak>
                                    No open slots on this date. Please choose another day.
                                </p>

                                @error('preferred_time')
                                    <p class="input-error-text">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Blocked / closed dates surfaced to the customer --}}
                        @if ($blockedRanges !== [])
                            <div class="mt-5 rounded-xl border border-status-low-stock/25 bg-status-low-stock-bg/40 p-4">
                                <p class="flex items-center gap-2 text-sm font-semibold text-status-low-stock">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                    Upcoming closure dates
                                </p>

                                <ul class="mt-2.5 space-y-1 text-xs text-ink">
                                    @foreach ($blockedRanges as $block)
                                        <li>
                                            <span class="font-medium text-primary">{{ $block['label'] }}</span>
                                            — {{ $block['service_id'] === null ? 'Salon closed' : $block['service'].' unavailable' }}
                                            @if ($block['reason'])
                                                <span class="text-ink-muted">({{ $block['reason'] }})</span>
                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </x-ui.card>

                    {{-- 4. Preferences --}}
                    <x-ui.card title="4. Preferences & Health Notes">
                        {{-- Allergies: checklist + free text --}}
                        <div class="space-y-4">
                            <fieldset>
                                <legend class="label">Allergies <span class="font-normal text-ink-muted">(select all that apply)</span></legend>

                                <div class="grid gap-2.5 sm:grid-cols-2">
                                    @foreach (['Latex', 'Nickel', 'Fragrance', 'Shellfish / Marine', 'Hair Dye / Ammonia', 'Adhesive', 'None known'] as $allergy)
                                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-primary/15 bg-white/60 px-3.5 py-2.5 text-sm transition hover:border-gold">
                                            <input type="checkbox" class="checkbox" :name="'allergy_'.{{ $loop->index }}" value="{{ $allergy }}" x-model="allergies">
                                            <span class="text-ink">{{ $allergy }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            <x-ui.form.textarea
                                name="allergies_other"
                                label="Allergy details"
                                rows="2"
                                placeholder="Anything else we should know about your sensitivities?"
                            />
                        </div>
                    </x-ui.card>

                    {{-- 5. Last services availed --}}
                    <x-ui.card title="5. Last Service(s) Availed" subtitle="Helps your therapist prepare ahead of time.">
                        @if ($history->isNotEmpty())
                            <x-ui.form.select
                                name="last_services_availed"
                                label="Pick from your history"
                                :includeBlank="true"
                                blankLabel="Select a previous service"
                                :options="$history->flatMap(fn ($a) => $a->serviceLines->pluck('service_name')->unique())
                                    ->unique()->values()->mapWithKeys(fn ($n) => [$n => $n])->all()"
                            />
                        @else
                            <p class="mb-4 text-sm text-ink-muted">No previous visits on record yet.</p>
                        @endif

                        <x-ui.form.textarea
                            name="last_services_availed_note"
                            label="Or tell us freely"
                            rows="2"
                            placeholder="e.g. Colour &amp; Highlights last March, Glow Manicure last month"
                        />
                    </x-ui.card>

                    {{-- 6. Stylist + special request --}}
                    <x-ui.card title="6. Preferred Stylist & Special Request">
                        <x-ui.form.select
                            name="preferred_stylist_id"
                            label="Preferred Stylist (optional)"
                            :includeBlank="true"
                            blankLabel="No preference"
                            :options="$stylists->mapWithKeys(fn ($a) => [$a->id => $a->full_name])->all()"
                        />

                        <div class="mt-4">
                            <x-ui.form.textarea
                                name="special_request"
                                label="Special Request"
                                rows="3"
                                placeholder="Quiet corner, extra time for consultation, specific products to avoid…"
                            />
                        </div>
                    </x-ui.card>

                    {{-- 7. Down payment --}}
                    <x-ui.card title="7. Down Payment">
                        @if ($settings->down_payment_required)
                            <div class="mb-4 rounded-xl bg-gold/10 px-4 py-3 text-sm text-ink">
                                A <span class="font-semibold text-primary">{{ $settings->down_payment_percentage }}%</span> down payment
                                is required to reserve your slot. Send via GCash and enter the reference number below.
                                <span class="mt-1 block text-xs text-ink-muted">
                                    We verify references manually — no online payment is processed on this site.
                                </span>
                            </div>

                            <x-ui.form.input
                                name="down_payment_reference"
                                label="GCash Reference Number"
                                required
                                placeholder="e.g. 1234567890"
                                maxlength="64"
                                :value="old('down_payment_reference')"
                            />

                            <template x-if="total > 0">
                                <p class="mt-3 text-sm text-ink-muted">
                                    Expected down payment:
                                    <span class="font-semibold text-primary" x-text="money(expectedDownPayment)"></span>
                                </p>
                            </template>
                        @else
                            <p class="text-sm text-ink-muted">No down payment is required for this booking.</p>
                            <input type="hidden" name="down_payment_reference" value="">
                        @endif
                    </x-ui.card>

                    {{-- 8. Terms --}}
                    <x-ui.card title="8. Terms & Conditions" accent="maroon">
                        <x-ui.form.checkbox name="agree_terms" value="1" required>
                            I have read and agree to the
                            <a href="{{ route('terms.show', 'booking') }}" target="_blank" rel="noopener"
                               class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Booking Terms &amp; Conditions</a>
                            , the
                            <a href="{{ route('terms.show', 'cancellation') }}" target="_blank" rel="noopener"
                               class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Cancellation Policy</a>
                            and the
                            <a href="{{ route('terms.show', 'rescheduling') }}" target="_blank" rel="noopener"
                               class="font-medium text-primary underline underline-offset-2 hover:text-primary-dark">Rescheduling Policy</a>.
                        </x-ui.form.checkbox>
                    </x-ui.card>
                </div>

                {{-- ================= RIGHT: booking summary ================= --}}
                <div class="lg:col-span-1">
                    <div class="bta-card overflow-hidden lg:sticky lg:top-28">
                        <div class="border-b border-primary/10 bg-primary px-5 py-4">
                            <h2 class="font-display text-lg font-semibold text-cream">Booking Summary</h2>
                            <p class="text-xs text-cream/70">Read-only summary</p>
                        </div>

                        <div class="space-y-4 p-5 text-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Customer</p>
                                <p class="mt-1 font-medium text-primary" x-text="$el.closest('form').querySelector('[name=customer_name]').value || '—'"></p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Service(s)</p>

                                <div x-show="selectedIds.length === 0" class="mt-1 text-ink-muted">No service selected yet.</div>

                                <ul class="mt-1.5 space-y-2">
                                    <template x-for="row in lines" :key="row.key">
                                        <li class="rounded-lg bg-linen/60 px-3 py-2">
                                            <div class="flex items-start justify-between gap-2">
                                                <span class="min-w-0 flex-1 text-ink" x-text="row.name"></span>
                                                <span class="shrink-0 font-medium text-primary" x-text="money(row.price * row.quantity)"></span>
                                            </div>
                                            <div class="mt-1.5 flex items-center gap-2">
                                                <select
                                                    class="rounded-lg border border-primary/15 bg-white px-2 py-1 text-[11px] text-ink"
                                                    x-show="row.hasVariants"
                                                    x-model="row.variantId"
                                                    @change="onVariantChange(row)"
                                                >
                                                    <template x-for="v in row.variants" :key="v.id">
                                                        <option :value="v.id" x-text="v.name"></option>
                                                    </template>
                                                </select>

                                                <input type="number" min="1" max="10"
                                                       class="w-16 rounded-lg border border-primary/15 bg-white px-2 py-1 text-[11px] text-ink"
                                                       :name="`services[${row.index}][quantity]`"
                                                       x-model.number="row.quantity">
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Date &amp; Time</p>
                                <p class="mt-1 font-medium text-primary" x-text="dateTimeLabel"></p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Preferred Stylist</p>
                                <p class="mt-1 font-medium text-primary" x-text="stylistName"></p>
                            </div>

                            <div class="bta-divider"></div>

                            <div class="flex items-baseline justify-between">
                                <span class="text-sm font-semibold text-ink">Total Amount</span>
                                <span class="font-display text-2xl font-bold text-primary" x-text="money(total)"></span>
                            </div>

                            <template x-if="expectedDownPayment > 0">
                                <div class="flex items-baseline justify-between text-sm">
                                    <span class="text-ink-muted">Down Payment (<span x-text="downPaymentPercent"></span>%)</span>
                                    <span class="font-semibold text-gold-dark" x-text="money(expectedDownPayment)"></span>
                                </div>
                            </template>

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
                            <button type="submit" class="btn-primary w-full btn-lg" x-bind:disabled="selectedIds.length === 0">
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
@endsection

@push('scripts')
    @php
        // Built in PHP so Blade's @json() parser never has to walk closures.
        $bookingConfig = [
            'catalogue' => $catalogue,
            'initial' => $initialSelection,
            'downPaymentPercent' => (int) $settings->down_payment_percentage,
            'slots' => $slots,
            'slotsUrl' => route('appointments.slots'),
            'date' => old('preferred_date', $prefilledDate),
            'time' => old('preferred_time', ''),
            'stylistId' => old('preferred_stylist_id', ''),
            'stylistNames' => $stylists->mapWithKeys(static fn ($a) => [$a->id => $a->full_name])->all(),
            'allergies' => collect(explode(',', (string) old('allergies', '')))->filter()->values()->all(),
        ];
    @endphp

    {{-- Server-rendered config, read once by the Alpine component. --}}
    <script type="application/json" id="bta-booking-config">{!! json_encode($bookingConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    <script>
        /**
         * Live booking basket.
         *
         * Owns the selected services, their variants and quantities, mirrors
         * them into the named inputs that StoreBookingRequest validates, and
         * keeps the read-only Booking Summary in sync. Configuration arrives
         * as JSON from the server; the server remains the real gate.
         */
        document.addEventListener('alpine:init', () => {
            Alpine.data('bookingForm', () => ({
                config: {},
                catalogue: {},
                downPaymentPercent: 50,
                selectedIds: [],
                rows: [],
                allergies: [],
                date: '',
                time: '',
                slots: [],
                loadingSlots: false,
                stylistId: '',
                stylistNames: {},
                slotsUrl: '',

                init() {
                    const node = document.getElementById('bta-booking-config');

                    this.config = node ? JSON.parse(node.textContent) : {};

                    this.catalogue = this.config.catalogue || {};
                    this.downPaymentPercent = this.config.downPaymentPercent || 50;
                    this.slots = this.config.slots || [];
                    this.slotsUrl = this.config.slotsUrl || '';
                    this.date = this.config.date || '';
                    this.time = this.config.time || '';
                    this.stylistId = this.config.stylistId || '';
                    this.stylistNames = this.config.stylistNames || {};
                    this.allergies = this.config.allergies || [];

                    (this.config.initial || []).forEach((row) => {
                        this.selectedIds.push(Number(row.service_id));
                        this.buildRow(Number(row.service_id), row.service_variant_id, row.quantity);
                    });

                    this.$watch('date', () => this.loadSlots());
                },

                isSelected(id) {
                    return this.selectedIds.includes(Number(id));
                },

                toggle(id, checked) {
                    if (checked) {
                        this.buildRow(Number(id), null, 1);
                    } else {
                        this.rows = this.rows.filter((row) => row.serviceId !== Number(id));
                    }

                    this.reindex();
                },

                buildRow(serviceId, variantId, quantity) {
                    const meta = this.catalogue[serviceId];

                    if (!meta) return;

                    const variants = meta.variants || [];
                    const chosen = variants.find((v) => v.id === Number(variantId)) || variants[0] || null;

                    this.rows.push({
                        key: serviceId + '-' + (chosen ? chosen.id : 'base'),
                        index: this.rows.length,
                        serviceId: serviceId,
                        name: chosen ? meta.name + ' (' + chosen.name + ')' : meta.name,
                        variants: variants,
                        variantId: chosen ? chosen.id : null,
                        hasVariants: variants.length > 1,
                        price: chosen ? chosen.price : meta.basePrice,
                        quantity: Number(quantity) || 1,
                    });
                },

                onVariantChange(row) {
                    const meta = this.catalogue[row.serviceId];
                    const chosen = row.variants.find((v) => v.id === Number(row.variantId));

                    row.name = chosen ? meta.name + ' (' + chosen.name + ')' : meta.name;
                    row.price = chosen ? chosen.price : meta.basePrice;
                    row.key = row.serviceId + '-' + (chosen ? chosen.id : 'base');
                },

                reindex() {
                    this.rows.forEach((row, index) => { row.index = index; });
                },

                get lines() {
                    return this.rows;
                },

                get total() {
                    return this.rows.reduce((sum, row) => sum + row.price * row.quantity, 0);
                },

                get expectedDownPayment() {
                    return Math.round(this.total * (this.downPaymentPercent / 100) * 100) / 100;
                },

                get stylistName() {
                    return this.stylistId ? (this.stylistNames[this.stylistId] || '—') : 'No preference';
                },

                get dateTimeLabel() {
                    if (!this.date) return '—';

                    const pretty = new Date(this.date + 'T00:00:00')
                        .toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });

                    return this.time ? pretty + ' at ' + this.formatTime(this.time) : pretty;
                },

                async loadSlots() {
                    if (!this.date || !this.slotsUrl) return;

                    const previous = this.time;
                    this.loadingSlots = true;

                    try {
                        const response = await fetch(this.slotsUrl + '?date=' + encodeURIComponent(this.date), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) throw new Error('slot lookup failed');

                        const data = await response.json();

                        this.slots = data.slots || [];

                        if (previous && !this.slots.includes(previous)) {
                            this.time = '';
                        }
                    } catch (error) {
                        this.slots = [];
                    } finally {
                        this.loadingSlots = false;
                    }
                },

                formatTime(value) {
                    const parts = String(value).split(':');
                    const hour = parseInt(parts[0], 10);
                    const minute = parts[1] || '00';
                    const suffix = hour >= 12 ? 'PM' : 'AM';
                    const display = hour % 12 === 0 ? 12 : hour % 12;

                    return display + ':' + minute + ' ' + suffix;
                },

                money(value) {
                    return '₱' + Number(value || 0).toLocaleString('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                },
            }));
        });
    </script>
@endpush
