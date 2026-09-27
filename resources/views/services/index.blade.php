@extends('layouts.customer')

@section('title', 'Services')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow=""
            title="Browse Services"
            description=""
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
      

   <!--     <p class="mb-5 text-sm text-ink-muted">
            Showing <span class="font-semibold text-primary">{{ $services->total() }}</span>
            {{ \Illuminate\Support\Str::plural('service', $services->total()) }}
        </p> -->

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
