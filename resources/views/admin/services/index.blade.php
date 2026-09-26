@extends('layouts.admin')

@section('title', 'Services')
@section('heading', 'Service Management')

@section('content')
    <x-ui.page-header
        eyebrow="Catalogue"
        title="Services"
        description="Add, edit and remove services, including short/long hair style variants."
    >
        <x-slot:actions>
            <a href="{{ route('admin.services.create') }}" class="btn-primary btn-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add Service
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.services.index') }}" class="bta-card mb-6 p-5">
        <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-6">
                <x-ui.form.input name="search" label="Search" placeholder="Name, description or category" :value="$filters['search'] ?? null" />
            </div>
            <div class="md:col-span-4">
                <x-ui.form.select
                    name="category"
                    label="Category"
                    :includeBlank="true"
                    blankLabel="All Categories"
                    :value="$filters['category'] ?? null"
                    :options="$categories->mapWithKeys(fn ($c) => [$c => $c])->all()"
                />
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="btn-primary flex-1">Filter</button>
                @if (array_filter($filters))
                    <a href="{{ route('admin.services.index') }}" class="btn-ghost">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if ($services->isEmpty())
        <x-ui.empty title="No services found" description="Add your first service to get started.">
            <x-slot:action>
                <a href="{{ route('admin.services.create') }}" class="btn-primary">Add Service</a>
            </x-slot:action>
        </x-ui.empty>
    @else
        <div class="bta-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="bta-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Category</th>
                            <th>Variants</th>
                            <th class="text-right">Price</th>
                            <th class="text-right">Duration</th>
                            <th class="text-center">Items</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($services as $service)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        @if ($service->photo_url)
                                            <img src="{{ $service->photo_url }}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover">
                                        @else
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-linen text-gold-dark">
                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M9.75 6.75 4.5 12l5.25 5.25M14.25 6.75 19.5 12l-5.25 5.25"/></svg>
                                            </span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-primary">{{ $service->name }}</p>
                                            <p class="truncate text-xs text-ink-muted">{{ \Illuminate\Support\Str::limit($service->description, 46) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap"><span class="badge badge-gold">{{ $service->category }}</span></td>
                                <td class="text-center">
                                    <span class="text-sm font-medium text-primary">{{ $service->variants->count() }}</span>
                                </td>
                                <td class="whitespace-nowrap text-right font-medium text-primary">₱{{ number_format((float) $service->price, 2) }}</td>
                                <td class="whitespace-nowrap text-right text-ink">{{ $service->duration_label }}</td>
                                <td class="text-center text-ink">{{ $service->inventory_items_count }}</td>
                                <td class="text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <x-ui.badge :status="$service->is_active ? 'confirmed' : 'cancelled'" :label="$service->is_active ? 'Active' : 'Hidden'" />
                                        @if ($service->is_featured)
                                            <x-ui.badge status="best_seller" label="Featured" />
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1.5">
                                        <a href="{{ route('admin.services.variants', $service) }}" class="btn-ghost btn-sm" title="Variants">Variants</a>
                                        <a href="{{ route('admin.services.edit', $service) }}" class="btn-secondary btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.services.destroy', $service) }}"
                                              onsubmit="return confirm('Delete “{{ $service->name }}”? Historical bookings keep their saved details.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $services->links() }}</div>
    @endif
@endsection
