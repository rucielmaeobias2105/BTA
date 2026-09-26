@extends('layouts.customer')

@section('title', 'About Us')

@section('content')
    {{-- Hero banner --}}
    <section class="relative overflow-hidden bg-primary">
        <img src="{{ asset('images/hero-banner.jpg') }}" alt="Balai ti Arjud interior"
             class="absolute inset-0 h-full w-full object-cover opacity-25">

        <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold">About Us</p>
            <h1 class="mt-3 max-w-3xl font-display text-4xl font-bold leading-tight tracking-tight text-cream sm:text-5xl">
                Beauty meets hospitality in the heart of Abra
            </h1>
            <p class="mt-4 max-w-2xl text-sm leading-relaxed text-cream/80">
                Balai ti Arjud — Glow &amp; Co. Beauty Lounge is a relaxing retreat where skilled
                therapists, quality products and genuine care come together.
            </p>
        </div>
    </section>

    {{-- Story --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-dark">Our Story</p>
                <h2 class="mt-3 font-display text-3xl font-bold tracking-tight text-primary">
                    A salon built around how you should feel
                </h2>

                <div class="mt-5 space-y-4 text-sm leading-relaxed text-ink-muted">
                    <p>
                        What started as a small neighbourhood salon has grown into a full-service beauty
                        lounge offering hair, nails, lashes, facials, massage and spa treatments — all
                        under one roof.
                    </p>
                    <p>
                        We believe a beauty appointment should feel like a break from a busy week, not
                        another task to get through. That means unhurried consultations, therapists who
                        listen, and products we genuinely stand behind.
                    </p>
                    <p>
                        Booking online is our way of making that easier: reserve your slot, tell us about
                        any sensitivities, and arrive to a chair that's already prepared for you.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Professional Therapists', 'Trained in the latest techniques across hair, nails, lashes and skin.', 'heroicon-o-academic-cap'],
                    ['Safe & Clean Environment', 'Tools are sanitised between every client and surfaces are wiped down regularly.', 'heroicon-o-shield-check'],
                    ['Quality Products', 'Professional-grade brands in every category, from colour to cleanser.', 'heroicon-o-sparkles'],
                    ['Relax · Rejuvenate · Feel Beautiful', 'Every treatment is designed around how you want to feel afterwards.', 'heroicon-o-heart'],
                ] as [$title, $body, $icon])
                    <div class="bta-card p-6">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                            <x-dynamic-component :component="$icon" class="h-5 w-5" />
                        </span>
                        <h3 class="mt-4 font-display text-base font-semibold text-primary">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm text-ink-muted">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Categories --}}
    @if ($categories !== [])
        <section class="border-y border-primary/10 bg-cream">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-dark">What We Offer</p>
                    <h2 class="mt-3 font-display text-3xl font-bold tracking-tight text-primary">Explore our services</h2>
                </div>

                <div class="mt-9 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($categories as $category)
                        <a href="{{ route('services.refined', ['category' => $category]) }}"
                           class="group flex items-center justify-between gap-3 rounded-card border border-primary/12 bg-linen/40 px-5 py-4 transition hover:border-gold hover:bg-gold/10">
                            <span class="font-medium text-primary">{{ $category }}</span>
                            <svg class="h-4 w-4 shrink-0 text-gold-dark transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Hours + CTA --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-2">
            <x-ui.card title="Opening Hours" subtitle="Appointments must fall within these hours.">
                <dl class="space-y-2.5 text-sm">
                    @foreach (\App\Models\SalonSetting::dayNames() as $key => $label)
                        @php $hours = $settings->operating_hours[$key] ?? null; @endphp
                        <div class="flex items-center justify-between gap-3 border-b border-primary/5 pb-2.5 last:border-0 last:pb-0">
                            <dt class="text-ink-muted">{{ $label }}</dt>
                            <dd class="font-medium text-primary">
                                {{ $hours ? substr($hours[0], 0, 5).' – '.substr($hours[1], 0, 5) : 'Closed' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>

            <div class="flex flex-col justify-center rounded-card bg-primary p-8 text-center">
                <h2 class="font-display text-2xl font-bold tracking-tight text-cream">Come visit us</h2>
                <p class="mt-3 text-sm leading-relaxed text-cream/80">
                    {{ $settings->address }}<br>
                    <a href="tel:{{ $settings->phone }}" class="underline underline-offset-2 hover:text-gold">{{ $settings->phone }}</a>
                </p>

                <div class="mt-7 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('appointments.create') }}" class="rounded-pill bg-cream px-6 py-3 text-sm font-medium text-primary transition hover:bg-gold-light">
                        Book an Appointment
                    </a>
                    <a href="{{ route('contact.create') }}" class="rounded-pill border border-cream/50 px-6 py-3 text-sm font-medium text-cream transition hover:bg-cream/10">
                        Contact Us
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
