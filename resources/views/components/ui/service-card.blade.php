@props([
    'service',
    'showRating' => true,
    'cta' => true,
])

@php
    $avg = $service->reviews_avg_rating ?? null;
    $count = $service->reviews_count ?? 0;
    $unavailable = $service->isUnavailable();
    $cheapest = $service->variants->min('price') ?? $service->price;
@endphp

<article class="group flex flex-col overflow-hidden rounded-card border border-gold/25 bg-cream shadow-card transition duration-300 hover:-translate-y-0.5 hover:border-gold/50 hover:shadow-card-hover">
    <div class="relative aspect-[4/3] overflow-hidden bg-linen">
        @if ($service->photo_url)
            <img src="{{ $service->photo_url }}" alt="{{ $service->name }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-linen to-gold-light/40">
                <svg class="h-12 w-12 text-gold-dark/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9.75 6.75 4.5 12l5.25 5.25M14.25 6.75 19.5 12l-5.25 5.25"/>
                </svg>
            </div>
        @endif

        <div class="absolute left-3 top-3 flex flex-wrap gap-1.5">
            <span class="badge badge-gold">{{ $service->category }}</span>
            @if ($unavailable)
                <x-ui.badge status="sold_out" label="Sold Out" />
            @endif
        </div>
    </div>

    <div class="flex flex-1 flex-col p-4">
        <h3 class="font-display text-base font-semibold leading-snug text-primary">
            {{ $service->name }}
        </h3>

        @if ($service->description)
            <p class="mt-1.5 line-clamp-2 text-sm text-ink-muted">{{ $service->description }}</p>
        @endif

        @if ($showRating && $count > 0)
            <div class="mt-2.5 flex items-center gap-1.5">
                <x-ui.star-rating :value="round((float) $avg)" :interactive="false" size="sm" />
                <span class="text-xs text-ink-muted">({{ $count }})</span>
            </div>
        @endif

        <div class="mt-auto pt-4">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <p class="text-lg font-semibold text-primary">
                        ₱{{ number_format((float) $cheapest, 2) }}
                    </p>
                    <p class="text-xs text-ink-muted">{{ $service->duration_label }}</p>
                </div>

                @if ($cta)
                    <a href="{{ route('appointments.create', ['services' => $service->slug]) }}"
                       class="btn-primary btn-sm shrink-0">
                        Book Now
                    </a>
                @endif
            </div>
        </div>
    </div>
</article>
