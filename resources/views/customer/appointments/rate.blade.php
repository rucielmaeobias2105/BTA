@extends('layouts.customer')

@section('title', 'Rate Service')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <a href="{{ route('appointments.show', $appointment) }}" class="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to appointment
        </a>

        <x-ui.page-header
            eyebrow="Feedback"
            title="Rate Service"
            description="Your feedback helps our team keep improving."
        />

        <x-ui.alert type="error" class="mb-6" :dismissible="false">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>

        {{-- Appointment recap --}}
        <div class="bta-card mb-6 border-l-4 border-l-gold p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Your Visit</p>
            <h2 class="mt-1.5 font-display text-lg font-semibold text-primary">{{ $appointment->service_names }}</h2>
            <p class="mt-0.5 text-sm text-ink-muted">
                {{ $appointment->preferred_date->format('M j, Y') }} at {{ $appointment->time_label }}
                @if ($appointment->preferredStylist)
                    &middot; with {{ $appointment->preferredStylist->full_name }}
                @endif
            </p>
        </div>

        <form method="POST" action="{{ route('appointments.rate.store', $appointment) }}" class="space-y-6" novalidate>
            @csrf

            <x-ui.card title="Star Rating" subtitle="Tap a star to rate your experience (1–5).">
                <x-ui.star-rating name="rating" :value="old('rating', 0)" size="lg" />
            </x-ui.card>

            <x-ui.card title="Your Review">
                <x-ui.form.textarea
                    name="message"
                    label="Review Message"
                    required
                    rows="5"
                    :value="old('message')"
                    placeholder="What did you enjoy? Anything we could improve?"
                    hint="Minimum of 5 characters. Reviews are visible to our team."
                />
            </x-ui.card>

            <div class="flex flex-col gap-3 sm:flex-row-reverse">
                <button type="submit" class="btn-primary sm:min-w-44">Submit Review</button>
                <a href="{{ route('appointments.show', $appointment) }}" class="btn-ghost sm:min-w-44">Maybe Later</a>
            </div>
        </form>
    </div>
@endsection
