@extends('layouts.customer')

@section('title', 'Appointment '.$appointment->reference_number)

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <nav class="mb-6 flex items-center gap-2 text-sm text-ink-muted" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-primary">Dashboard</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('appointments.index') }}" class="transition hover:text-primary">My Appointments</a>
            <span aria-hidden="true">/</span>
            <span class="truncate font-medium text-primary">{{ $appointment->reference_number }}</span>
        </nav>

        {{-- Header --}}
        <div class="bta-card mb-6 overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-primary/10 bg-primary px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gold">Booking Reference</p>
                    <h1 class="mt-1 font-display text-2xl font-bold tracking-tight text-cream">
                        {{ $appointment->reference_number }}
                    </h1>
                </div>
                <x-ui.badge :status="$appointment->status->badge()" :label="$appointment->status->label()" class="!bg-cream/15 !text-cream" />
            </div>

            <div class="grid gap-5 p-6 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Date &amp; Time</p>
                    <p class="mt-1.5 font-medium text-primary">{{ $appointment->preferred_date->format('M j, Y') }}</p>
                    <p class="text-sm text-ink-muted">{{ $appointment->time_label }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Amount</p>
                    <p class="mt-1.5 font-display text-xl font-bold text-primary">
                        ₱{{ number_format((float) $appointment->total_amount, 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Down Payment</p>
                    <p class="mt-1.5 font-medium text-primary">
                        {{ $appointment->down_payment_amount ? '₱'.number_format((float) $appointment->down_payment_amount, 2) : '—' }}
                    </p>
                    <div class="mt-1"><x-ui.badge :status="$appointment->down_payment_status->badge()" :label="$appointment->down_payment_status->label()" /></div>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">GCash Reference</p>
                    <p class="mt-1.5 font-medium text-primary">{{ $appointment->down_payment_reference ?: '—' }}</p>
                    <p class="text-xs text-ink-muted">Verified manually by our team</p>
                </div>
            </div>

            @if ($appointment->canBeCancelled() || $appointment->canBeRescheduled() || $appointment->canBeRated())
                <div class="flex flex-wrap gap-2 border-t border-primary/10 bg-linen/50 px-6 py-4">
                    @if ($appointment->canBeRated())
                        <a href="{{ route('appointments.rate.create', $appointment) }}" class="btn-primary btn-sm">Rate Service</a>
                    @endif
                    @if ($appointment->canBeRescheduled())
                        <a href="{{ route('appointments.reschedule', $appointment) }}" class="btn-gold btn-sm">Reschedule</a>
                    @endif
                    @if ($appointment->canBeCancelled())
                        <a href="{{ route('appointments.cancel', $appointment) }}" class="btn-danger btn-sm">Cancel Appointment</a>
                    @endif
                </div>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Booking summary (read-only) --}}
            <div class="space-y-6 lg:col-span-2">
                <x-ui.card title="Booking Summary" subtitle="Read-only">
                    <dl class="space-y-4 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Customer</dt>
                            <dd class="mt-1 font-medium text-primary">{{ $appointment->customer_name }}</dd>
                            <dd class="text-ink-muted">{{ $appointment->customer_phone }}</dd>
                            @if ($appointment->customer_email)
                                <dd class="text-ink-muted">{{ $appointment->customer_email }}</dd>
                            @endif
                        </div>

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Service(s)</dt>
                            <ul class="mt-2 space-y-2">
                                @foreach ($appointment->serviceLines as $line)
                                    <li class="flex items-start justify-between gap-3 rounded-xl bg-linen/60 px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="font-medium text-ink">{{ $line->display_name }}</p>
                                            <p class="text-xs text-ink-muted">
                                                {{ $line->duration_minutes }} min &middot; Qty {{ $line->quantity }}
                                            </p>
                                        </div>
                                        <p class="shrink-0 font-semibold text-primary">
                                            ₱{{ number_format($line->line_total, 2) }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-3 text-right text-sm text-ink-muted">
                                Total duration:
                                <span class="font-medium text-primary">{{ $appointment->total_duration }} min</span>
                            </p>
                        </div>
                    </dl>
                </x-ui.card>

                {{-- Preferences --}}
                <x-ui.card title="Your Preferences & Notes">
                    <dl class="space-y-4 text-sm">
                        @foreach ([
                            'Allergies' => $appointment->allergies,
                            'Last Service(s) Availed' => $appointment->last_services_availed,
                            'Special Request' => $appointment->special_request,
                            'Cancellation Reason' => $appointment->cancellation_reason,
                            'Reschedule Reason' => $appointment->reschedule_reason,
                        ] as $label => $value)
                            @if ($value)
                                <div class="border-b border-primary/5 pb-4 last:border-0 last:pb-0">
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</dt>
                                    <dd class="mt-1 text-ink">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach

                        @if (! $appointment->allergies && ! $appointment->last_services_availed && ! $appointment->special_request && ! $appointment->cancellation_reason && ! $appointment->reschedule_reason)
                            <p class="text-ink-muted">No additional notes for this appointment.</p>
                        @endif

                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Preferred Stylist</dt>
                            <dd class="mt-1 text-ink">{{ $appointment->preferredStylist?->full_name ?? 'No preference' }}</dd>
                        </div>
                    </dl>
                </x-ui.card>

                {{-- Review --}}
                <div id="review">
                    @if ($appointment->review->isNotEmpty())
                        @php $review = $appointment->review->first(); @endphp
                        <x-ui.card title="Your Review">
                            <x-ui.star-rating :value="$review->rating" :interactive="false" />
                            <p class="mt-3 text-sm leading-relaxed text-ink">{{ $review->message }}</p>
                            <p class="mt-3 text-xs text-ink-muted">Submitted {{ $review->created_at->format('M j, Y') }}</p>
                        </x-ui.card>
                    @elseif ($appointment->canBeRated())
                        <x-ui.card title="Rate Your Experience" subtitle="Only completed appointments can be rated.">
                            <p class="text-sm text-ink-muted">How did we do? Your feedback helps us improve.</p>
                            <a href="{{ route('appointments.rate.create', $appointment) }}" class="btn-primary mt-4">Rate Service</a>
                        </x-ui.card>
                    @endif
                </div>
            </div>

            {{-- Status history --}}
            <aside>
                <x-ui.card title="Status History" subtitle="Every change to this appointment.">
                    @if ($appointment->statusHistory->isEmpty())
                        <p class="text-sm text-ink-muted">No history recorded yet.</p>
                    @else
                        <ol class="relative space-y-5 border-l border-primary/15 pl-5">
                            @foreach ($appointment->statusHistory as $entry)
                                <li class="relative">
                                    <span class="absolute -left-[1.6rem] top-1 h-2.5 w-2.5 rounded-full bg-primary ring-4 ring-cream"></span>
                                    <p class="text-sm font-medium text-primary">{{ $entry->arrow }}</p>
                                    <p class="mt-0.5 text-xs text-ink-muted">
                                        {{ $entry->created_at->format('M j, Y g:i A') }}
                                    </p>
                                    <p class="text-xs text-ink-muted">
                                        by {{ $entry->actorLabel() }}
                                    </p>
                                    @if ($entry->note)
                                        <p class="mt-1.5 text-xs italic text-ink-muted">{{ $entry->note }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-ui.card>
            </aside>
        </div>
    </div>
@endsection
