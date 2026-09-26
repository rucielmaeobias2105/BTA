@extends('layouts.customer')

@section('title', 'Refined Grid View')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow="Refined Grid View"
            title="Browse by Category"
            description="A cleaner, category-first view of our menu. Tap a category to narrow the grid."
        />

        {{-- Category tab bar --}}
        <div class="mb-7 flex flex-wrap items-center gap-2">
            <a href="{{ route('services.refined', ['category' => 'all', 'search' => $search]) }}"
               class="rounded-pill px-4 py-2 text-sm font-medium transition {{ $activeCategory === 'all' ? 'bg-primary text-cream shadow-card' : 'bg-cream text-ink hover:bg-linen' }}">
                All Services
            </a>

            @foreach ($categories as $category)
                <a href="{{ route('services.refined', ['category' => $category, 'search' => $search]) }}"
                   class="rounded-pill px-4 py-2 text-sm font-medium transition {{ $activeCategory === $category ? 'bg-primary text-cream shadow-card' : 'bg-cream text-ink hover:bg-linen' }}">
                    {{ $category }}
                </a>
            @endforeach
        </div>

        {{-- Inline search --}}
        <form method="GET" action="{{ route('services.refined') }}" class="mb-8 max-w-md">
            @if ($activeCategory !== 'all')
                <input type="hidden" name="category" value="{{ $activeCategory }}">
            @endif

            <x-ui.form.input
                name="search"
                label="Search within {{ $activeCategory === 'all' ? 'all services' : $activeCategory }}"
                placeholder="Search services…"
                :value="$search"
                icon="heroicon-o-magnifying-glass"
            />
        </form>

        @if ($services->isEmpty())
            <x-ui.empty
                title="No services in this category yet"
                description="Try another category, or view the complete menu."
            >
                <x-slot:action>
                    <a href="{{ route('services.refined', ['category' => 'all']) }}" class="btn-secondary">View All Services</a>
                </x-slot:action>
            </x-ui.empty>
        @else
            {{-- Refined grid: 4-up on desktop, tighter cards than the list view --}}
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($services as $service)
                    <x-ui.service-card :service="$service" />
                @endforeach
            </div>

            <div class="mt-10">{{ $services->links() }}</div>
        @endif
    </div>
@endsection
