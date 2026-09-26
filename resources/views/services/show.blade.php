@extends('layouts.customer')

@section('title', $service->name)

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <nav class="mb-6 flex items-center gap-2 text-sm text-ink-muted" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="transition hover:text-primary">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('services.index') }}" class="transition hover:text-primary">Services</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('services.index', ['category' => $service->category]) }}" class="transition hover:text-primary">{{ $service->category }}</a>
            <span aria-hidden="true">/</span>
            <span class="truncate font-medium text-primary">{{ $service->name }}</span>
        </nav>

        <div class="grid gap-8 lg:grid-cols-5">
            {{-- Media --}}
            <div class="lg:col-span-3">
                <div class="overflow-hidden rounded-card border border-gold/30 bg-cream shadow-panel">
                    @if ($service->photo_url)
                        <img src="{{ $service->photo_url }}" alt="{{ $service->name }}" class="aspect-[4/3] w-full object-cover">
                    @else
                        <div class="flex aspect-[4/3] w-full items-center justify-center bg-gradient-to-br from-linen to-gold-light/40">
                            <svg class="h-20 w-20 text-gold-dark/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9.75 6.75 4.5 12l5.25 5.25M14.25 6.75 19.5 12l-5.25 5.25"/>
                            </svg>
                        </div>
                    @endif
                </div>

                @if ($reviews->isNotEmpty())
                    <div class="mt-8">
                        <h2 class="font-display text-xl font-semibold text-primary">What Our Clients Say</h2>

                        <div class="mt-4 space-y-4">
                            @foreach ($reviews as $review)
                                <article class="bta-card p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-primary">{{ $review->customer_name }}</p>
                                            <p class="text-xs text-ink-muted">{{ $review->created_at->format('M j, Y') }}</p>
                                        </div>
                                        <x-ui.star-rating :value="$review->rating" :interactive="false" size="sm" />
                                    </div>
                                    <p class="mt-3 text-sm leading-relaxed text-ink">{{ $review->message }}</p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Details + booking --}}
            <div class="lg:col-span-2">
                <div class="bta-card p-6 lg:sticky lg:top-28">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-gold">{{ $service->category }}</span>
                        @if ($service->is_featured)
                            <x-ui.badge status="best_seller" label="Best Seller" />
                        @endif
                        @if ($service->isUnavailable())
                            <x-ui.badge status="sold_out" label="Sold Out" />
                        @endif
                    </div>

                    <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-primary">{{ $service->name }}</h1>

                    <div class="mt-4 flex items-end gap-4">
                        <p class="font-display text-3xl font-semibold text-primary">
                            ₱{{ number_format((float) $service->price, 2) }}
                        </p>
                        <p class="pb-1.5 text-sm text-ink-muted">{{ $service->duration_label }}</p>
                    </div>

                    @if ($service->description)
                        <p class="mt-4 text-sm leading-relaxed text-ink-muted">{{ $service->description }}</p>
                    @endif

                    @if ($service->variants->isNotEmpty())
                        <div class="mt-6 border-t border-primary/10 pt-5">
                            <h2 class="text-sm font-semibold text-primary">Choose a Variant</h2>

                            <ul class="mt-3 space-y-2">
                                @foreach ($service->variants as $variant)
                                    <li class="flex items-center justify-between gap-3 rounded-xl border border-primary/10 bg-linen/50 px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-ink">{{ $variant->name }}</p>
                                            <p class="text-xs text-ink-muted">
                                                {{ $variant->effectiveDuration() }} min
                                                @if ($variant->is_default)
                                                    &middot; <span class="text-gold-dark">Default</span>
                                                @endif
                                            </p>
                                        </div>
                                        <p class="shrink-0 text-sm font-semibold text-primary">
                                            ₱{{ number_format((float) $variant->price, 2) }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mt-6 space-y-2.5 border-t border-primary/10 pt-5">
                        <a href="{{ route('appointments.create', ['services' => $service->slug]) }}" class="btn-primary w-full btn-lg">
                            Book Now
                        </a>
                        <a href="{{ route('services.index', ['category' => $service->category]) }}" class="btn-ghost w-full">
                            More {{ $service->category }} services
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-16">
                <h2 class="font-display text-2xl font-bold tracking-tight text-primary">You May Also Like</h2>

                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($related as $item)
                        <x-ui.service-card :service="$item" :show-rating="false" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
