@extends('layouts.admin')

@section('title', 'Services & Items')
@section('heading', 'Services & Item Management')

@section('content')
    <x-ui.card title="Services" :subtitle="$services->total().' matching'">
        <x-slot:actions>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.services.index') }}" class="text-xs font-medium text-primary underline underline-offset-2">Manage</a>
                @can('admin.catalog.manage')
                    <a href="{{ route('admin.services.create') }}" class="btn-primary btn-sm">Add Service</a>
                @endcan
            </div>
        </x-slot:actions>

        @if ($services->isEmpty())
            <p class="text-sm text-ink-muted">No services match.</p>
        @else
            <ul class="divide-y divide-primary/8">
                @foreach ($services as $service)
                    <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-primary">{{ $service->name }}</p>
                            <p class="truncate text-xs text-ink-muted">
                                {{ $service->category }}
                                @if ($service->duration_label !== '')
                                    &middot; {{ $service->duration_label }}
                                @endif
                                @if ($service->variants->count() > 0)
                                    &middot; {{ $service->variants->count() }} variant{{ $service->variants->count() === 1 ? '' : 's' }}
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="text-sm font-semibold text-primary">{{ \App\Support\PriceFormatter::display($service->price) }}</span>
                            @can('admin.catalog.manage')
                                <a href="{{ route('admin.services.edit', $service) }}" class="btn-ghost btn-sm">Edit</a>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $services->links() }}</div>
        @endif
    </x-ui.card>
@endsection
