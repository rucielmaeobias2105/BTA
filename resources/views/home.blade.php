@extends('layouts.customer')

@section('title', 'Glow & Beauty Lounge')

@section('content')
    {{-- ================= HERO ================= --}}
    <section class="relative overflow-hidden bg-linen">
        {{-- Decorative gold arc echoing the mockup's curve --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-0 hidden w-1/2 lg:block">
            <div class="absolute inset-y-0 left-0 w-24 -skew-x-6 bg-linen"></div>
        </div>

        <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-2 lg:gap-6 lg:py-20 lg:px-8">
            {{-- Copy panel --}}
            <div class="relative z-10 animate-fade-in-up">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-dark">Welcome to</p>

                <h1 class="mt-3 font-display text-5xl font-bold leading-[1.05] tracking-tight text-primary sm:text-6xl">
                    Balai ti Arjud
                </h1>

                <p class="mt-2 font-display text-2xl italic text-sienna sm:text-3xl">
                    Glow &amp; Co. Beauty Lounge
                </p>

                <p class="mt-6 max-w-lg text-[15px] leading-relaxed text-ink-muted">
                    Where beauty meets hospitality. Book your services online and let our therapists
                    pamper you — from glow manicures and lash extensions to relaxing massage and spa
                    packages in the heart of Abra.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('appointments.create') }}" class="btn-primary btn-lg">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                        </svg>
                        Book an Appointment
                    </a>
                    <a href="{{ route('services.index') }}" class="btn-secondary btn-lg">
                        Explore Our Services
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>

                {{-- Trust row --}}
                <div class="mt-10 grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-4">
                    @foreach ([
                        ['Professional Therapists', 'M9.75 11.25 11.25 13.5 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                        ['Safe & Clean Environment', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                        ['Quality Products', 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z'],
                        ['Relax · Rejuvenate · Feel Beautiful', 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z'],
                    ] as [$label, $path])
                        <div class="flex flex-col items-center gap-2.5 text-center sm:flex-row sm:text-left">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg>
                            </span>
                            <span class="text-xs font-medium leading-snug text-ink">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Salon photo --}}
            <div class="relative z-10 animate-fade-in-up">
                <div class="relative overflow-hidden rounded-card border border-gold/30 shadow-panel">
                    <img src="{{ asset('images/hero-salon.jpg') }}" alt="Inside Balai ti Arjud beauty lounge"
                         class="aspect-[5/4] w-full object-cover" fetchpriority="high">
                </div>

                {{-- Floating rating chip --}}
                @if ($reviewCount > 0)
                    <div class="absolute -bottom-5 left-5 flex items-center gap-3 rounded-card border border-gold/30 bg-cream px-4 py-3 shadow-card">
                        <div class="flex -space-x-2">
                            @foreach (['JP', 'MR', 'AL'] as $initial)
                                <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-cream bg-sienna text-[10px] font-semibold text-cream">{{ $initial }}</span>
                            @endforeach
                        </div>
                        <div class="leading-tight">
                            <x-ui.star-rating :value="round($averageRating)" :interactive="false" size="sm" />
                            <p class="mt-0.5 text-xs text-ink-muted"><span class="font-semibold text-primary">{{ $reviewCount }}</span> happy clients</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ================= PROMO STRIP ================= --}}
    @if ($promo)
        <section class="border-y border-gold/30 bg-gold/10">
            <div class="mx-auto flex max-w-7xl flex-col items-center gap-4 px-4 py-6 sm:flex-row sm:px-6 lg:px-8">
                @if ($promo->image_url)
                    <img src="{{ $promo->image_url }}" alt="{{ $promo->title }}" class="h-20 w-28 rounded-xl object-cover">
                @endif
                <div class="flex-1 text-center sm:text-left">
                    <x-ui.badge status="gold" label="Limited Offer" />
                    <h3 class="mt-2 font-display text-xl font-semibold text-primary">{{ $promo->title }}</h3>
                    <p class="mt-1 text-sm text-ink-muted">{{ \Illuminate\Support\Str::limit(strip_tags($promo->description), 180) }}</p>
                    <p class="mt-1.5 text-xs font-medium text-gold-dark">Valid {{ $promo->validity_label }}</p>
                </div>
                <a href="{{ route('services.index') }}" class="btn-primary shrink-0">Book This Offer</a>
            </div>
        </section>
    @endif

    {{-- ================= FEATURED SERVICES ================= --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="mb-10 text-center">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-gold-dark">Our Signature Treatments</p>
            <h2 class="mt-3 font-display text-3xl font-bold tracking-tight text-primary sm:text-4xl">
                Signature Services
            </h2>
            <p class="mx-auto mt-3 max-w-2xl text-sm text-ink-muted">
                Hand-picked favourites our clients love, performed by our professional therapists.
            </p>
        </div>

        @if ($featured->isEmpty())
            <x-ui.empty
                title="Services are being prepared"
                description="Our menu is being updated. Please check back shortly."
            />
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $service)
                    <x-ui.service-card :service="$service" />
                @endforeach
            </div>
        @endif

        <div class="mt-10 text-center">
            <a href="{{ route('services.index') }}" class="btn-secondary">View All Services</a>
        </div>
    </section>

    {{-- ================= CATEGORY PILLS ================= --}}
    @if ($categories !== [])
        <section class="border-y border-primary/10 bg-cream">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 class="text-center font-display text-2xl font-bold tracking-tight text-primary">Browse by Category</h2>

                <div class="mt-7 flex flex-wrap justify-center gap-2.5">
                    @foreach ($categories as $category)
                        <a href="{{ route('services.refined', ['category' => $category]) }}"
                           class="rounded-pill border border-primary/20 bg-linen/60 px-5 py-2.5 text-sm font-medium text-ink transition hover:border-gold hover:bg-gold/10 hover:text-primary">
                            {{ $category }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ================= CTA ================= --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-card bg-primary px-6 py-14 text-center shadow-panel sm:px-12">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(201,162,75,0.28),transparent_55%),radial-gradient(circle_at_80%_75%,rgba(232,201,192,0.2),transparent_50%)]"></div>

            <div class="relative">
                <h2 class="font-display text-3xl font-bold tracking-tight text-cream sm:text-4xl">
                    Ready to glow?
                </h2>
                <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-cream/80">
                    Reserve your slot in just a few clicks. Choose your treatments, pick a time that
                    suits you, and let our therapists do the rest.
                </p>

                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('appointments.create') }}" class="btn-lg inline-flex rounded-pill bg-cream px-7 py-3.5 text-base font-medium text-primary transition hover:bg-gold-light">
                        Book an Appointment
                    </a>
                    <a href="{{ route('contact.create') }}" class="btn-lg inline-flex rounded-pill border border-cream/50 px-7 py-3.5 text-base font-medium text-cream transition hover:bg-cream/10">
                        Contact Us
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
