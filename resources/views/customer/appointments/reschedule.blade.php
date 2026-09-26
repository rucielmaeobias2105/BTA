@extends('layouts.customer')

@section('title', 'Reschedule Appointment')

@section('content')
    @php
        $minDate = $availability->firstBookableDate()->toDateString();
        $maxDate = $availability->lastBookableDate()->toDateString();
    @endphp

    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <a href="{{ route('appointments.show', $appointment) }}" class="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to appointment
        </a>

        <x-ui.page-header
            eyebrow="Reschedule"
            title="Reschedule Appointment"
            description="Pick a new date and time. Your new slot is re-validated against our calendar."
        />

        <x-ui.alert type="error" class="mb-6" :dismissible="false">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>

        {{-- Current date/time — read-only --}}
        <div class="bta-card mb-6 border-l-4 border-l-gold p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Current Date &amp; Time (read-only)</p>
            <p class="mt-1.5 font-display text-xl font-bold text-primary">{{ $appointment->date_time_label }}</p>
            <p class="mt-0.5 text-sm text-ink-muted">{{ $appointment->service_names }} &middot; Ref {{ $appointment->reference_number }}</p>
        </div>

        @php
            $rescheduleConfig = [
                'date' => old('preferred_date', $prefilledDate),
                'slots' => $slots,
                'url' => route('appointments.slots'),
                'serviceId' => $serviceId,
            ];
        @endphp

        <form
            method="POST"
            action="{{ route('appointments.reschedule.update', $appointment) }}"
            x-data="rescheduleSlots"
            novalidate
        >
            @csrf
            @method('PATCH')

            <script type="application/json" id="bta-reschedule-config">{!! json_encode($rescheduleConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

            <x-ui.card title="New Date &amp; Time">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.form.input
                        name="preferred_date"
                        type="date"
                        label="New Preferred Date"
                        required
                        :value="old('preferred_date', $prefilledDate)"
                        :min="$minDate"
                        :max="$maxDate"
                        x-model="date"
                    />

                    <div>
                        <label for="preferred_time" class="label">
                            New Preferred Time <span class="text-status-cancelled">*</span>
                        </label>

                        <select id="preferred_time" name="preferred_time" class="input" required x-model="time">
                            <option value="">Select a time</option>
                            <template x-for="slot in slots" :key="slot">
                                <option :value="slot" x-text="formatTime(slot)"></option>
                            </template>
                        </select>

                        <p class="input-hint" x-show="loading" x-cloak>Checking availability…</p>
                        <p class="input-hint" x-show="! loading && slots.length === 0" x-cloak>
                            No open slots on this date. Please choose another day.
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <x-ui.form.textarea
                        name="reason"
                        label="Reason (optional)"
                        rows="3"
                        :value="old('reason')"
                        placeholder="Let us know why you need to move your appointment."
                    />
                </div>
            </x-ui.card>

            @if ($blockedRanges !== [])
                <div class="mt-6 rounded-card border border-status-low-stock/25 bg-status-low-stock-bg/40 p-5">
                    <p class="text-sm font-semibold text-status-low-stock">Dates we are closed</p>
                    <ul class="mt-2 space-y-1 text-xs text-ink">
                        @foreach ($blockedRanges as $block)
                            <li>
                                <span class="font-medium text-primary">{{ $block['label'] }}</span>
                                — {{ $block['service_id'] === null ? 'Salon closed' : $block['service'].' unavailable' }}
                                @if ($block['reason'])<span class="text-ink-muted">({{ $block['reason'] }})</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($policy)
                <x-ui.card title="Rescheduling Policy" class="mt-6">
                    <div class="max-h-48 space-y-2 overflow-y-auto pr-2 text-sm leading-relaxed text-ink [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-primary [&_h3]:mt-3 [&_h3]:font-semibold [&_h3]:text-primary [&_li]:ml-4 [&_li]:list-disc">
                        {!! $policy->content !!}
                    </div>
                </x-ui.card>
            @endif

            <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                <button type="submit" class="btn-primary sm:min-w-44">Confirm Reschedule</button>
                <a href="{{ route('appointments.show', $appointment) }}" class="btn-ghost sm:min-w-44">Keep Current Slot</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        // Slot picker for the reschedule form. Mirrors the booking form's
        // availability endpoint but excludes this appointment's own slot.
        document.addEventListener('alpine:init', () => {
            Alpine.data('rescheduleSlots', () => ({
                date: '',
                time: '',
                slots: [],
                loading: false,
                url: '',
                serviceId: null,

                init() {
                    const node = document.getElementById('bta-reschedule-config');
                    const config = node ? JSON.parse(node.textContent) : {};

                    this.date = config.date || '';
                    this.slots = config.slots || [];
                    this.url = config.url || '';
                    this.serviceId = config.serviceId || null;

                    this.$watch('date', () => this.load());
                },

                async load() {
                    if (!this.date) return;

                    this.loading = true;

                    try {
                        const params = new URLSearchParams({ date: this.date });

                        if (this.serviceId) params.set('service_id', this.serviceId);

                        const response = await fetch(this.url + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) throw new Error('slot lookup failed');

                        const data = await response.json();

                        this.slots = data.slots || [];
                    } catch (error) {
                        this.slots = [];
                    } finally {
                        this.loading = false;
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
            }));
        });
    </script>
@endpush
