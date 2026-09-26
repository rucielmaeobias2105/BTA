@extends('layouts.customer')

@section('title', 'My Appointments')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow="My Bookings"
            title="My Appointments"
            description="View, cancel, reschedule or rate your appointments."
        >
            <x-slot:actions>
                <a href="{{ route('appointments.create') }}" class="btn-primary btn-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    New Booking
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Status filter — action links only, no input fields --}}
        <div class="mb-6 flex flex-wrap gap-2">
            @foreach ($statusOptions as $value => $label)
                @php $active = $status === $value; @endphp
                <a href="{{ route('appointments.index', ['status' => $value === 'all' ? null : $value]) }}"
                   class="rounded-pill px-4 py-2 text-sm font-medium transition {{ $active ? 'bg-primary text-cream shadow-card' : 'bg-cream text-ink hover:bg-linen' }}">
                    {{ $label }}
                    @if ($value !== 'all' && ($counts[$value] ?? 0) > 0)
                        <span class="ml-1 opacity-70">{{ $counts[$value] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        @if ($appointments->isEmpty())
            <x-ui.empty
                title="No appointments here"
                description="You don't have any {{ $status === 'all' ? '' : strtolower($status) }} appointments right now."
            >
                <x-slot:action>
                    <a href="{{ route('appointments.create') }}" class="btn-primary">Book an Appointment</a>
                </x-slot:action>
            </x-ui.empty>
        @else
            <div class="space-y-4">
                @foreach ($appointments as $appointment)
                    @php $canRate = $appointment->canBeRated(); @endphp
                    <article class="bta-card p-5 sm:p-6">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                            {{-- Left: details --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.badge :status="$appointment->status->badge()" :label="$appointment->status->label()" />
                                    <span class="text-xs text-ink-muted">Ref: {{ $appointment->reference_number }}</span>
                                </div>

                                <h3 class="mt-3 font-display text-lg font-semibold text-primary">
                                    {{ $appointment->service_names }}
                                </h3>

                                <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                                        <dd class="text-ink">{{ $appointment->date_time_label }}</dd>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        <dd class="font-semibold text-primary">₱{{ number_format((float) $appointment->total_amount, 2) }}</dd>
                                    </div>

                                    @if ($appointment->preferredStylist)
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                                            <dd class="text-ink">{{ $appointment->preferredStylist->full_name }}</dd>
                                        </div>
                                    @endif

                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"/></svg>
                                        <dd>
                                            <x-ui.badge :status="$appointment->down_payment_status->badge()" :label="$appointment->down_payment_status->label()" />
                                        </dd>
                                    </div>
                                </dl>

                                @if ($appointment->cancellation_reason)
                                    <p class="mt-3 rounded-lg bg-status-cancelled-bg/50 px-3.5 py-2.5 text-xs text-ink">
                                        <span class="font-semibold text-status-cancelled">Reason:</span>
                                        {{ $appointment->cancellation_reason }}
                                    </p>
                                @endif
                            </div>

                            {{-- Right: action buttons only --}}
                            <div class="flex shrink-0 flex-wrap gap-2 sm:w-44 sm:flex-col">
                                <a href="{{ route('appointments.show', $appointment) }}" class="btn-secondary btn-sm flex-1 sm:w-full">
                                    View
                                </a>

                                @if ($appointment->canBeRescheduled())
                                    <a href="{{ route('appointments.reschedule', $appointment) }}" class="btn-gold btn-sm flex-1 sm:w-full">
                                        Reschedule
                                    </a>
                                @endif

                                @if ($canRate)
                                    <a href="{{ route('appointments.rate.create', $appointment) }}" class="btn-primary btn-sm flex-1 sm:w-full">
                                        Rate
                                    </a>
                                @elseif ($appointment->review->isNotEmpty())
                                    <a href="{{ route('appointments.show', $appointment) }}#review" class="btn-ghost btn-sm flex-1 sm:w-full">
                                        Your Review
                                    </a>
                                @endif

                                @if ($appointment->canBeCancelled())
                                    <a href="{{ route('appointments.cancel', $appointment) }}" class="btn-danger btn-sm flex-1 sm:w-full">
                                        Cancel
                                    </a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-8">{{ $appointments->links() }}</div>
        @endif
    </div>
@endsection
