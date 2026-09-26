@extends('layouts.customer')

@section('title', 'Browse Services')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow="Our Menu"
            title="Browse Services"
            description="Explore our full treatment menu. Filter by category or price to find the perfect service for you."
        >
            <x-slot:actions>
                <a href="{{ route('services.refined') }}" class="btn-secondary btn-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                    </svg>
                    Refined Grid
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Search + filters --}}
        <form method="GET" action="{{ route('services.index') }}" class="bta-card mb-8 p-5">
            <div class="grid gap-4 md:grid-cols-12">
                <div class="md:col-span-5">
                    <x-ui.form.input
                        name="search"
                        label="Search"
                        placeholder="Search services…"
                        :value="$filters['search'] ?? null"
                        icon="heroicon-o-magnifying-glass"
                    />
                </div>

                <div class="md:col-span-3">
                    <x-ui.form.select
                        name="category"
                        label="Category"
                        :value="$filters['category'] ?? null"
                        :options="collect($categories)->mapWithKeys(fn ($c) => [$c => $c])->prepend('All Categories', '')->all()"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3 md:col-span-3">
                    <x-ui.form.input
                        name="min_price"
                        type="number"
                        step="0.01"
                        min="0"
                        label="Min Price"
                        placeholder="{{ number_format($priceRange['min'], 0) }}"
                        :value="$filters['min_price'] ?? null"
                        prefix="₱"
                    />
                    <x-ui.form.input
                        name="max_price"
                        type="number"
                        step="0.01"
                        min="0"
                        label="Max Price"
                        placeholder="{{ number_format($priceRange['max'], 0) }}"
                        :value="$filters['max_price'] ?? null"
                        prefix="₱"
                    />
                </div>

                <div class="flex items-end gap-2 md:col-span-1">
                    <button type="submit" class="btn-primary w-full" aria-label="Apply filters">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    </button>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-primary/10 pt-4">
                <span class="text-xs font-medium text-ink-muted">Quick filters:</span>

                <a href="{{ route('services.index') }}"
                   class="rounded-pill px-3 py-1.5 text-xs font-medium transition {{ ! ($filters['category'] ?? null) ? 'bg-primary text-cream' : 'bg-linen text-ink hover:bg-linen/70' }}">All</a>

                @foreach (array_slice($categories, 0, 6) as $category)
                    <a href="{{ route('services.index', ['category' => $category]) }}"
                       class="rounded-pill px-3 py-1.5 text-xs font-medium transition {{ ($filters['category'] ?? null) === $category ? 'bg-primary text-cream' : 'bg-linen text-ink hover:bg-linen/70' }}">{{ $category }}</a>
                @endforeach

                @if (array_filter($filters))
                    <a href="{{ route('services.index') }}" class="ml-auto text-xs text-primary underline underline-offset-2 hover:text-primary-dark">Clear all filters</a>
                @endif
            </div>
        </form>

        <p class="mb-5 text-sm text-ink-muted">
            Showing <span class="font-semibold text-primary">{{ $services->total() }}</span>
            {{ \Illuminate\Support\Str::plural('service', $services->total()) }}
        </p>

        @if ($services->isEmpty())
            <x-ui.empty
                title="No services match your filters"
                description="Try widening your price range or choosing a different category."
            >
                <x-slot:action>
                    <a href="{{ route('services.index') }}" class="btn-secondary">Reset Filters</a>
                </x-slot:action>
            </x-ui.empty>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <x-ui.service-card :service="$service" />
                @endforeach
            </div>

            <div class="mt-10">{{ $services->links() }}</div>
        @endif
    </div>
@endsection
