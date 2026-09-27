@extends('layouts.customer')

@section('title', 'Home')

@section('content')
    {{-- ================= HERO ================= --}}
    <section class="home-hero">
        <div class="home-hero-copy">
            <div class="home-floral home-floral-top" aria-hidden="true">&#10048;</div>
            <div class="home-floral home-floral-bottom" aria-hidden="true">&#10048;</div>

            <p class="home-kicker">WELCOME TO</p>

            <h1 class="home-hero-title">Balai ti Arjud</h1>

            <p class="home-script">Glow &amp; Co. Beauty Lounge</p>

            <p class="home-description">
                Where beauty meets hospitality. Book your services online and let our therapists
                pamper you — from glow manicures and lash extensions to relaxing massage and spa
                packages in the heart of Abra.
            </p>

            <div class="home-hero-buttons">
                <a href="{{ route('appointments.create') }}" class="btn-primary btn-lg">Book an Appointment</a>
                <a href="{{ route('services.index') }}" class="btn-secondary btn-lg">
                    Explore Our Services
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
            </div>

            {{-- Trust row --}}
            <div class="home-features">
                @foreach ([
                    ['Professional Therapists', 'M9.75 11.25 11.25 13.5 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                    ['Safe & Clean Environment', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                    ['Quality Products', 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z'],
                    ['Relax · Rejuvenate · Feel Beautiful', 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z'],
                ] as [$label, $path])
                    <div class="home-feature">
                        <span class="home-feature-icon">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg>
                        </span>
                        <span>{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="home-hero-media">
            <img src="{{ asset('images/hero2.jpg') }}" alt="Inside Balai ti Arjud beauty lounge"
                 fetchpriority="high">
            <div class="home-gold-arc" aria-hidden="true"></div>
        </div>
    </section>

    {{-- ================= SPECIAL OFFERS ================= --}}
    @if ($promos->isNotEmpty())
        <section id="offers" class="home-section" data-tab-title="PROMO | {{ config('app.name') }}">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="home-head">
                    <p class="home-eyebrow">Special Offers</p>
                    <h2 class="home-title">Current promos, just for you</h2>
                    <p class="home-sub">
                        Limited-time packages and discounts while they last. Book early to secure your slot.
                    </p>
                </div>

                <div class="home-promo-grid">
                    @foreach ($promos as $offer)
                        <article class="home-promo-card">
                            <div class="home-promo-media">
                                @if ($offer->image_url)
                                    <img src="{{ $offer->image_url }}" alt="{{ $offer->title }}" loading="lazy">
                                @else
                                    <span class="home-promo-flourish" aria-hidden="true">&#10048;</span>
                                @endif
                                <x-ui.badge status="gold" label="Limited Offer" />
                            </div>

                            <div class="home-promo-body">
                                <h3>{{ $offer->title }}</h3>
                                <p>{{ \Illuminate\Support\Str::limit(strip_tags($offer->description), 140) }}</p>

                                <div class="home-promo-foot">
                                    <p class="home-promo-validity">Valid {{ $offer->validity_label }}</p>
                                    <a href="{{ route('appointments.create') }}" class="btn-primary btn-sm shrink-0">
                                        Book This Offer
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <button type="button" class="home-to-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Back to top">
        &#8679;
    </button>
@endsection
