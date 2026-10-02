@props([
    'service',
    'cta' => true,
])

{{--
    The customer-facing service card: category, name, price and a way through to
    the booking form.

    There is no image, no variant table and no rating here — the catalogue a
    service belongs to is described by what it is and what it costs, and a photo
    placeholder or a variant price range only took space away from that.
--}}
@php
    $unavailable = $service->isUnavailable();
@endphp

<article class="group flex flex-col rounded-card border border-gold/25 bg-cream p-5 shadow-card transition duration-300 hover:-translate-y-0.5 hover:border-gold/50 hover:shadow-card-hover">
    <div class="flex flex-wrap items-center gap-1.5">
        <span class="badge badge-gold">{{ $service->category }}</span>
        @if ($unavailable)
            <x-ui.badge status="sold_out" label="Sold Out" />
        @endif
    </div>

    <h3 class="mt-2.5 font-display text-lg font-semibold leading-snug text-primary">
        {{ $service->name }}
    </h3>

    @if ($service->description)
        <p class="mt-1.5 line-clamp-3 flex-1 text-sm text-ink-muted">{{ $service->description }}</p>
    @endif

    <div class="mt-auto flex items-end justify-between gap-3 pt-4">
        <p class="text-lg font-semibold text-primary">{{ \App\Support\PriceFormatter::display($service->price) }}</p>

        @if ($cta)
            <a href="{{ route('appointments.create', ['services' => $service->slug]) }}"
               class="btn-primary btn-sm shrink-0">
                Book Now
            </a>
        @endif
    </div>
</article>
