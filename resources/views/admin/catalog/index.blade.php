@extends('layouts.admin')

@section('title', 'Services & Items')
@section('heading', 'Services & Item Management')

@section('content')
    <x-ui.page-header
        eyebrow="Catalogue Overview"
        title="Services & Items"
        description="One combined view of everything on your menu and in your stockroom."
    >
        <x-slot:actions>
            <a href="{{ route('admin.services.create') }}" class="btn-secondary btn-sm">Add Service</a>
            <a href="{{ route('admin.inventory.create') }}" class="btn-primary btn-sm">Add Item</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.catalog.index') }}" class="bta-card mb-6 p-5">
        <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-5">
                <x-ui.form.input name="search" label="Search" placeholder="Search services and items at once" :value="$filters['search'] ?? null" />
            </div>
            <div class="md:col-span-3">
                <x-ui.form.select
                    name="type"
                    label="Show"
                    :value="$filters['type'] ?? 'all'"
                    :options="['all' => 'Services & Items', 'service' => 'Services only', 'item' => 'Items only']"
                />
            </div>
            <div class="md:col-span-2">
                <x-ui.form.input name="category" label="Category" placeholder="Exact match" :value="$filters['category'] ?? null" />
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="btn-primary flex-1">Search</button>
                @if (array_filter($filters))
                    <a href="{{ route('admin.catalog.index') }}" class="btn-ghost">Clear</a>
                @endif
            </div>
        </div>
    </form>

    <div class="grid gap-6 2xl:grid-cols-2">
        {{-- Services --}}
        <x-ui.card title="Services" :subtitle="$services->total().' matching'">
            <x-slot:actions>
                <a href="{{ route('admin.services.index') }}" class="text-xs font-medium text-primary underline underline-offset-2">Manage</a>
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
                                    {{ $service->category }} &middot; {{ $service->duration_label }}
                                    @if ($service->variants->count() > 0)
                                        &middot; {{ $service->variants->count() }} variant{{ $service->variants->count() === 1 ? '' : 's' }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="text-sm font-semibold text-primary">₱{{ number_format((float) $service->price, 2) }}</span>
                                <a href="{{ route('admin.services.edit', $service) }}" class="btn-ghost btn-sm">Edit</a>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4">{{ $services->links() }}</div>
            @endif
        </x-ui.card>

        {{-- Items --}}
        <x-ui.card title="Inventory Items" :subtitle="$items->total().' matching'">
            <x-slot:actions>
                <a href="{{ route('admin.inventory.index') }}" class="text-xs font-medium text-primary underline underline-offset-2">Manage</a>
            </x-slot:actions>

            @if ($items->isEmpty())
                <p class="text-sm text-ink-muted">No items match.</p>
            @else
                <ul class="divide-y divide-primary/8">
                    @foreach ($items as $item)
                        <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-primary">{{ $item->name }}</p>
                                <p class="truncate text-xs text-ink-muted">
                                    {{ $item->category }} &middot; reorder at {{ $item->reorder_threshold }} {{ $item->unit }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-ui.badge :status="$item->status_tag->badge()" :label="$item->status_tag->label()" />
                                <a href="{{ route('admin.inventory.edit', $item) }}" class="btn-ghost btn-sm">Edit</a>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4">{{ $items->links() }}</div>
            @endif
        </x-ui.card>
    </div>
@endsection
