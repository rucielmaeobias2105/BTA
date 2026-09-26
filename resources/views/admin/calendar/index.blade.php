@extends('layouts.admin')

@section('title', 'Calendar')
@section('heading', 'Calendar & Blocked Dates')

@section('content')
    <x-ui.page-header
        eyebrow="Availability"
        title="Calendar & Blocked Dates"
        description="Block single dates or ranges. Customers cannot book on these dates from the booking form."
    />

    <x-ui.errors />

    <div class="grid gap-6 2xl:grid-cols-3">
        {{-- Calendar grid --}}
        <div class="2xl:col-span-2">
            <x-ui.card>
                <x-slot:actions>
                    <div class="flex items-center gap-1.5">
                        <a href="{{ route('admin.calendar.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}"
                           class="flex h-8 w-8 items-center justify-center rounded-lg border border-primary/15 text-primary transition hover:bg-linen" aria-label="Previous month">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                        </a>
                        <span class="min-w-36 text-center font-display text-sm font-semibold text-primary">{{ $month->format('F Y') }}</span>
                        <a href="{{ route('admin.calendar.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}"
                           class="flex h-8 w-8 items-center justify-center rounded-lg border border-primary/15 text-primary transition hover:bg-linen" aria-label="Next month">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </a>
                    </div>
                </x-slot:actions>

                <div class="grid grid-cols-7 gap-1 text-center">
                    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $label)
                        <div class="pb-2 text-[11px] font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</div>
                    @endforeach

                    @php $cursor = $gridStart->copy(); @endphp

                    @while ($cursor->lte($gridEnd))
                        @php
                            $key = $cursor->toDateString();
                            $dayBlocks = $closures[$key] ?? [];
                            $isToday = $cursor->isSameDay(today());
                            $inMonth = $cursor->month === $month->month;
                            $isPast = $cursor->lt(today());
                            $open = $settings->isOpenOn($cursor);
                        @endphp

                        <div @class([
                            'relative flex aspect-square flex-col items-center justify-center rounded-xl border p-1 text-sm transition',
                            'border-primary bg-primary text-cream' => $isToday,
                            'border-primary/15 bg-cream text-ink' => ! $isToday && $inMonth && $dayBlocks === [] && $open,
                            'border-primary/10 bg-linen/50 text-ink-muted' => ! $isToday && ! $inMonth,
                            'border-status-cancelled/20 bg-status-cancelled-bg/50 text-ink-muted' => $dayBlocks !== [],
                            'opacity-50' => $isPast,
                        ])>
                            <span class="font-medium">{{ $cursor->day }}</span>

                            @if ($dayBlocks !== [])
                                <span class="mt-0.5 flex items-center gap-0.5">
                                    @foreach (array_slice($dayBlocks, 0, 3) as $block)
                                        <span @class([
                                            'h-1.5 w-1.5 rounded-full',
                                            'bg-primary' => $block->service_id === null,
                                            'bg-gold' => $block->service_id !== null,
                                        ]) title="{{ $block->scope_label }} — {{ $block->reason }}"></span>
                                    @endforeach
                                </span>
                            @elseif (! $open)
                                <span class="text-[9px] uppercase text-ink-muted">Closed</span>
                            @endif
                        </div>

                        @php $cursor->addDay(); @endphp
                    @endwhile
                </div>

                <div class="mt-4 flex flex-wrap gap-4 border-t border-primary/10 pt-4 text-xs text-ink-muted">
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-primary"></span> Today
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-primary/30"></span> Open
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-primary"></span> Salon-wide block
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-gold"></span> Service-specific block
                    </span>
                </div>
            </x-ui.card>

            {{-- Blocked dates list --}}
            <x-ui.card title="Blocked Dates" subtitle="Active and upcoming closures." class="mt-6">
                @if ($blocked->isEmpty())
                    <p class="text-sm text-ink-muted">No dates blocked in this month.</p>
                @else
                    <ul class="space-y-2.5">
                        @foreach ($blocked as $block)
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/12 bg-linen/50 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-primary">{{ $block->range_label }}</p>
                                    <p class="text-xs text-ink-muted">
                                        {{ $block->scope_label }}
                                        @if ($block->reason) &middot; {{ $block->reason }} @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    @php $affected = $block->appointments()->count(); @endphp
                                    @if ($affected > 0)
                                        <span class="badge badge-pending">{{ $affected }} booking{{ $affected === 1 ? '' : 's' }}</span>
                                    @endif
                                    <form method="POST" action="{{ route('admin.calendar.destroy', $block) }}"
                                          onsubmit="return confirm('Unblock {{ $block->range_label }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger btn-sm">Unblock</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        {{-- Sidebar: add block + settings --}}
        <aside class="space-y-6">
            <x-ui.card title="Block a Date" subtitle="Single day or a range.">
                <form method="POST" action="{{ route('admin.calendar.store') }}" class="space-y-4" novalidate>
                    @csrf

                    <x-ui.form.input name="start_date" type="date" label="Start Date" required :value="old('start_date')" :min="$firstBookable->toDateString()" />
                    <x-ui.form.input name="end_date" type="date" label="End Date (optional)" :value="old('end_date')" :min="old('start_date')" hint="Leave blank to block a single day." />

                    <x-ui.form.select
                        name="service_id"
                        label="Scope"
                        :includeBlank="true"
                        blankLabel="All services (salon-wide)"
                        :value="old('service_id')"
                        :options="$services->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()"
                    />

                    <x-ui.form.textarea name="reason" label="Reason (internal)" rows="2" :value="old('reason')" placeholder="e.g. Provincial holiday, stock delivery" />

                    <button type="submit" class="btn-primary w-full">Block Date(s)</button>
                </form>
            </x-ui.card>

            <x-ui.card title="Operating Hours & Rules">
                <form method="POST" action="{{ route('admin.calendar.settings') }}" class="space-y-4" novalidate>
                    @csrf
                    @method('PUT')

                    <x-ui.form.input name="name" label="Salon Name" required :value="old('name', $settings->name)" />
                    <x-ui.form.input name="address" label="Address" :value="old('address', $settings->address)" />
                    <x-ui.form.input name="phone" label="Phone" :value="old('phone', $settings->phone)" />
                    <x-ui.form.input name="email" type="email" label="Email" :value="old('email', $settings->email)" />

                    <div class="bta-divider"></div>

                    <div class="space-y-2.5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Opening Hours</p>

                        @foreach ($dayNames as $key => $label)
                            @php $hours = $settings->operating_hours[$key] ?? null; @endphp
                            <div class="grid grid-cols-[1fr_auto_auto_1fr] items-center gap-2">
                                <span class="text-sm text-ink">{{ $label }}</span>
                                <input type="time" name="hours[{{ $key }}][open]" value="{{ $hours[0] ?? '' }}"
                                       class="input w-24 px-2 py-1.5 text-xs" aria-label="{{ $label }} opening time">
                                <span class="text-ink-muted">–</span>
                                <input type="time" name="hours[{{ $key }}][close]" value="{{ $hours[1] ?? '' }}"
                                       class="input w-24 px-2 py-1.5 text-xs" aria-label="{{ $label }} closing time">
                            </div>
                        @endforeach

                        <p class="input-hint">Leave both times blank to mark a day closed.</p>
                    </div>

                    <div class="bta-divider"></div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.form.input name="slot_interval_minutes" type="number" min="15" max="240" step="5" label="Slot Interval (min)" required :value="old('slot_interval_minutes', $settings->slot_interval_minutes)" />
                        <x-ui.form.input name="booking_lead_days" type="number" min="1" max="365" label="Booking Horizon (days)" required :value="old('booking_lead_days', $settings->booking_lead_days)" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.form.checkbox name="down_payment_required" value="1" :checked="old('down_payment_required', $settings->down_payment_required)" label="Require down payment" />
                        <x-ui.form.input name="down_payment_percentage" type="number" min="0" max="100" label="Down Payment (%)" required :value="old('down_payment_percentage', $settings->down_payment_percentage)" />
                    </div>

                    <button type="submit" class="btn-primary w-full">Save Settings</button>
                </form>
            </x-ui.card>
        </aside>
    </div>
@endsection
