@extends('layouts.customer')

@section('title', 'Promo')

@section('content')
    {{-- The same plain header services/index uses — no eyebrow, no marketing
         line — so the two pages read as a pair. The shell keeps the identical
         padding for the same reason. --}}
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow=""
            title="Promo Offers"
            description=""
        />

        @if ($promos->isNotEmpty())
            <div class="home-promo-grid">
                @foreach ($promos as $offer)
                    {{-- Picture beside the text on desktop, stacked with a 16:9
                         band on mobile. An offer with no picture gets the
                         flourish, which is what the whole media block was
                         before photos existed — so "none yet" still looks
                         deliberate rather than broken. --}}
                    <article class="home-promo-card">
                        <div class="home-promo-media">
                            @if ($offer->hasImage())
                                <img
                                    src="{{ $offer->image_url }}"
                                    alt="{{ $offer->title }}"
                                    loading="lazy"
                                >
                            @else
                                <span class="home-promo-flourish" aria-hidden="true">&#10048;</span>
                            @endif
                            <x-ui.badge status="gold" label="Limited Offer" />
                        </div>

                        <div class="home-promo-body">
                            <h3>{{ $offer->title }}</h3>

                            {{-- The description in full. `{{ }}` escapes it — so
                                 it is text and never markup — and
                                 `whitespace-pre-line` in
                                 `.home-promo-body > p` keeps the paragraphs the
                                 admin typed. Writing `e()` here as well would
                                 escape it twice and show a visitor
                                 `&amp;lt;b&amp;gt;`. --}}
                            <p>{{ $offer->description }}</p>

                            <div class="home-promo-foot">
                                <p class="home-promo-validity">Valid {{ $offer->validity_label }}</p>
                                <a href="{{ route('appointments.create') }}" class="btn-primary btn-sm shrink-0">
                                    Book Now
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <x-ui.empty title="No promos right now" />
        @endif
    </div>
@endsection
