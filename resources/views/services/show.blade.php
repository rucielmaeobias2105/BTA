@extends('layouts.customer')

@section('title', $service->name)

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        {{--
            The breadcrumb that stood here — Home / Services / <category> /
            <service name> — has been removed, and its `mb-6` with it so the page
            does not open with an empty band where it was.

            It was hand-written markup rather than a shared component, so nothing
            else on the site was affected: the only other mention of a breadcrumb
            anywhere in `resources/views` is a comment in the customer's
            appointments list, which describes dialog navigation and has no
            breadcrumb of its own.

            The category it linked to is still reachable — it is the chip in the
            card below — and the service name is the page's own `<h1>`, so nothing
            that was on the page has been lost with the trail.
        --}}
        <div class="grid gap-8 lg:grid-cols-5">
            {{-- Details + booking. The catalogue is name, price and
                 description — no photo, no variant table, no duration. --}}
            <div class="lg:col-span-3">
                <div class="bta-card p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-gold">{{ $service->category }}</span>
                        @if ($service->isUnavailable())
                            <x-ui.badge status="sold_out" label="Sold Out" />
                        @endif
                    </div>

                    <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-primary">{{ $service->name }}</h1>

                    <p class="mt-4 font-display text-3xl font-semibold text-primary">
                        {{ \App\Support\PriceFormatter::display($service->price) }}
                    </p>

                @if ($service->description)
                    <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-ink-muted">{{ $service->description }}</p>
                @endif
            </div>

            <div class="lg:col-span-2">
                <div class="bta-card p-6 lg:sticky lg:top-28">
                    <h2 class="font-display text-lg font-semibold text-primary">Book this service</h2>

                    <div class="mt-5 space-y-2.5">
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
                        <x-ui.service-card :service="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
