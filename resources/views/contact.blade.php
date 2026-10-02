@extends('layouts.customer')

@section('title', 'Contact Us')

@section('content')
    {{-- Follows the MCA Café Contact layout in structure only: a stack of
         detail cards beside the message form. No hero banner and no
         page-header, so this starts directly under the navbar like the other
         public pages. The form, its fields, validation and route are
         unchanged. --}}
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">

        <div class="grid gap-6 lg:grid-cols-5 lg:gap-8">
            {{-- Details --}}
            <div class="space-y-4 lg:col-span-2">
                @php
                    $hours = collect(\App\Models\SalonSetting::dayNames())
                        ->map(fn ($hoursPair, $key) => [
                            'label' => $hoursPair,
                            'value' => $settings->operating_hours[$key] ?? null,
                        ])
                        ->values();
                    $openDays = $hours->whereNotNull('value');
                @endphp

                <div class="bta-card flex gap-4 p-5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-primary">Address</p>
                        <p class="mt-1 text-sm leading-relaxed text-ink-muted">
                            {{ config('salon.address') }}
                        </p>
                    </div>
                </div>

                <div class="bta-card flex gap-4 p-5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v6l3.75 2.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary">Operating Hours</p>
                        @if ($openDays->isEmpty())
                            <p class="mt-1 text-sm text-ink-muted">Hours to be announced.</p>
                        @else
                            {{-- Collapsed to a range when every open day shares the
                                 same times, which is the common case, and listed in
                                 full when they differ.

                                 The times are shown 12-hour, via
                                 `SalonSetting::formatTimeForDisplay`. What is
                                 stored stays 24-hour "HH:MM" — the availability
                                 rules parse it that way, so the conversion belongs
                                 here at the edge and nowhere else. --}}
                            @php $first = $openDays->first()['value']; $last = $openDays->last()['value']; $uniform = $openDays->every(fn ($day) => $day['value'] === $first); @endphp
                            @if ($uniform)
                                <p class="mt-1 text-sm text-ink-muted">
                                    {{ $openDays->pluck('label')->first() }} – {{ $openDays->pluck('label')->last() }}<br>
                                    {{ \App\Models\SalonSetting::formatTimeForDisplay($first[0]) }} – {{ \App\Models\SalonSetting::formatTimeForDisplay($last[1]) }}
                                </p>
                            @else
                                <dl class="mt-1 space-y-0.5 text-sm text-ink-muted">
                                    @foreach ($hours as $day)
                                        <div class="flex justify-between gap-3">
                                            <dt>{{ $day['label'] }}</dt>
                                            <dd class="text-right font-medium text-primary">
                                                {{ $day['value'] ? \App\Models\SalonSetting::formatTimeForDisplay($day['value'][0]).' – '.\App\Models\SalonSetting::formatTimeForDisplay($day['value'][1]) : 'Closed' }}
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="bta-card flex gap-4 p-5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-primary">Phone</p>
                        <a href="tel:{{ config('salon.phone_e164') }}" class="mt-1 block text-sm text-ink-muted transition hover:text-primary">
                            {{ config('salon.phone_display') }}
                        </a>
                    </div>
                </div>

                <div class="bta-card flex gap-4 p-5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                        <x-salon.facebook-icon />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary">Facebook</p>
                        <a
                            href="{{ config('salon.facebook_url') }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-1 inline-flex items-center gap-1.5 break-all text-sm text-ink-muted transition hover:text-primary"
                        >{{ config('salon.facebook_url') }}</a>
                    </div>
                </div>

                <div class="bta-card flex gap-4 p-5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-primary">Email</p>
                        <a href="mailto:{{ config('salon.email') }}" class="mt-1 block text-sm text-ink-muted transition hover:text-primary">
                            {{ config('salon.email') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- Form --}}
            <div class="lg:col-span-3">
                <form method="POST" action="{{ route('contact.store') }}" class="bta-card p-6 sm:p-8" novalidate>
                    @csrf

                    <div class="flex items-center justify-center gap-2 text-center">
                        <span class="text-gold-dark">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21.75 2.25 2.25 10.5l6.75 3 3 6.75 9.75-18Z"/></svg>
                        </span>
                        <h2 class="font-display text-lg font-semibold text-primary sm:text-xl">Send Us a Message</h2>
                    </div>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <x-ui.form.input
                            name="name"
                            label="Full Name"
                            icon="heroicon-o-user"
                            required
                            autocomplete="name"
                            :value="auth()->user()?->full_name"
                        />
                        <x-ui.form.input
                            name="email"
                            type="email"
                            label="Email Address"
                            icon="heroicon-o-envelope"
                            required
                            autocomplete="email"
                            :value="auth()->user()?->email"
                        />
                    </div>

                    <div class="mt-5">
                        <x-ui.form.select
                            name="topic"
                            label="Topic"
                            required
                            :includeBlank="true"
                            blankLabel="Select a topic…"
                            :options="$topics"
                        />
                    </div>

                    <div class="mt-5">
                        <x-ui.form.textarea
                            name="message"
                            label="Message"
                            required
                            rows="7"
                            placeholder="Write your message here…"
                            hint="Please include as much detail as you can so we can assist you properly."
                        />
                    </div>

                    <button type="submit" class="btn-primary btn-lg mt-6 w-full">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21.75 2.25 2.25 10.5l6.75 3 3 6.75 9.75-18Z"/></svg>
                        Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
