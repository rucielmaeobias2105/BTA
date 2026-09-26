@extends('layouts.customer')

@section('title', 'Dashboard')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        {{-- Greeting --}}
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                @if ($user->profile_photo_path)
                    <img src="{{ Storage::url($user->profile_photo_path) }}" alt="" class="h-14 w-14 rounded-full object-cover ring-2 ring-gold ring-offset-2 ring-offset-cream">
                @else
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary font-display text-lg font-semibold text-cream ring-2 ring-gold ring-offset-2 ring-offset-cream">
                        {{ $user->initials }}
                    </span>
                @endif

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gold-dark">Welcome Back</p>
                    <h1 class="mt-0.5 font-display text-2xl font-bold tracking-tight text-primary sm:text-3xl">
                        {{ $user->first_name }}
                    </h1>
                </div>
            </div>

            <a href="{{ route('appointments.create') }}" class="btn-primary">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Book Appointment
            </a>
        </div>

        {{-- Stat cards --}}
        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Upcoming', $stats['upcoming'], 'heroicon-o-calendar-days', 'text-status-confirmed', 'bg-status-confirmed-bg'],
                ['Pending', $stats['pending'], 'heroicon-o-clock', 'text-status-pending', 'bg-status-pending-bg'],
                ['Completed', $stats['completed'], 'heroicon-o-check-circle', 'text-status-completed', 'bg-status-completed-bg'],
                ['Unread Alerts', $unreadCount, 'heroicon-o-bell', 'text-primary', 'bg-gold/20'],
            ] as [$label, $value, $icon, $text, $bg])
                <div class="bta-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</p>
                            <p class="mt-2 font-display text-3xl font-bold text-primary">{{ $value }}</p>
                        </div>
                        <span class="flex h-10 w-10 items-center justify-center rounded-full {{ $bg }} {{ $text }}">
                            <x-dynamic-component :component="$icon" class="h-5 w-5" />
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Upcoming appointment snapshot --}}
            <div class="space-y-6 lg:col-span-2">
                <x-ui.card title="Your Next Appointment" accent="maroon">
                    <x-slot:actions>
                        <a href="{{ route('appointments.index') }}" class="text-xs font-medium text-primary underline underline-offset-2 hover:text-primary-dark">View all</a>
                    </x-slot:actions>

                    @if ($upcoming)
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.badge :status="$upcoming->status->badge()" :label="$upcoming->status->label()" />
                                    <span class="text-xs text-ink-muted">{{ $upcoming->reference_number }}</span>
                                </div>

                                <h3 class="mt-3 font-display text-xl font-semibold text-primary">{{ $upcoming->service_names }}</h3>

                                <dl class="mt-3 space-y-2 text-sm">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                                        <dd class="text-ink">{{ $upcoming->date_time_label }}</dd>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        <dd class="font-semibold text-primary">₱{{ number_format((float) $upcoming->total_amount, 2) }}</dd>
                                    </div>

                                    @if ($upcoming->preferredStylist)
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                                            <dd class="text-ink">{{ $upcoming->preferredStylist->full_name }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2 sm:w-40 sm:flex-col">
                                <a href="{{ route('appointments.show', $upcoming) }}" class="btn-secondary btn-sm flex-1 sm:w-full">View</a>
                                <a href="{{ route('appointments.reschedule', $upcoming) }}" class="btn-gold btn-sm flex-1 sm:w-full">Reschedule</a>
                                <a href="{{ route('appointments.cancel', $upcoming) }}" class="btn-danger btn-sm flex-1 sm:w-full">Cancel</a>
                            </div>
                        </div>
                    @else
                        <x-ui.empty
                            title="No upcoming appointments"
                            description="Book your next treatment and we'll see you soon."
                        >
                            <x-slot:action>
                                <a href="{{ route('appointments.create') }}" class="btn-primary">Book an Appointment</a>
                            </x-slot:action>
                        </x-ui.empty>
                    @endif
                </x-ui.card>

                {{-- Recent activity --}}
                <x-ui.card title="Recent Activity">
                    @if ($recent->isEmpty())
                        <p class="text-sm text-ink-muted">No past visits on record yet.</p>
                    @else
                        <ul class="divide-y divide-primary/8">
                            @foreach ($recent as $appointment)
                                <li class="flex items-center justify-between gap-4 py-3.5 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink">{{ $appointment->service_names }}</p>
                                        <p class="text-xs text-ink-muted">{{ $appointment->preferred_date->format('M j, Y') }}</p>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-3">
                                        <x-ui.badge :status="$appointment->status->badge()" :label="$appointment->status->label()" />
                                        @if ($appointment->canBeRated())
                                            <a href="{{ route('appointments.rate.create', $appointment) }}" class="text-xs font-medium text-primary underline underline-offset-2">Rate</a>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>

            {{-- Quick links --}}
            <aside class="space-y-6">
                <x-ui.card title="Quick Links">
                    <div class="space-y-2.5">
                        @foreach ([
                            ['Book Appointment', 'heroicon-o-calendar-days', route('appointments.create')],
                            ['My Appointments', 'heroicon-o-calendar-days', route('appointments.index')],
                            ['Browse Services', 'heroicon-o-sparkles', route('services.index')],
                            ['Notifications', 'heroicon-o-bell', route('notifications.index'), $unreadCount],
                            ['Edit Profile', 'heroicon-o-user-circle', route('profile.edit')],
                            ['Contact Us', 'heroicon-o-chat-bubble-left-right', route('contact.create')],
                        ] as $link)
                            <a href="{{ $link[2] }}" class="flex items-center gap-3 rounded-xl border border-primary/12 bg-white/50 px-4 py-3 text-sm font-medium text-ink transition hover:border-gold hover:bg-gold/8">
                                <x-dynamic-component :component="$link[1]" class="h-5 w-5 shrink-0 text-primary" />
                                <span class="flex-1">{{ $link[0] }}</span>
                                @if (($link[3] ?? 0) > 0)
                                    <span class="rounded-pill bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-cream">{{ $link[3] }}</span>
                                @endif
                                <svg class="h-4 w-4 shrink-0 text-gold-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            </a>
                        @endforeach
                    </div>
                </x-ui.card>

                @if ($awaitingRating > 0)
                    <div class="rounded-card bg-gold/12 p-5">
                        <p class="font-display text-base font-semibold text-primary">
                            {{ $awaitingRating }} visit{{ $awaitingRating === 1 ? '' : 's' }} awaiting your review
                        </p>
                        <p class="mt-1 text-sm text-ink-muted">Let others know how we did.</p>
                        <a href="{{ route('appointments.index', ['status' => 'completed']) }}" class="btn-secondary btn-sm mt-4">Rate Now</a>
                    </div>
                @endif

                <x-ui.card title="Your Rating">
                    <div class="flex items-center gap-4">
                        <p class="font-display text-4xl font-bold text-primary">{{ $averageRating ?: '—' }}</p>
                        <div>
                            <x-ui.star-rating :value="round($averageRating)" :interactive="false" />
                            <p class="mt-1 text-xs text-ink-muted">{{ $reviews }} review{{ $reviews === 1 ? '' : 's' }} from our clients</p>
                        </div>
                    </div>
                </x-ui.card>
            </aside>
        </div>
    </div>
@endsection
