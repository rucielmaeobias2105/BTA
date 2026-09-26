@extends('layouts.admin')

@section('title', 'Inventory')
@section('heading', 'Inventory Management')

@section('content')
    <x-ui.page-header
        eyebrow="Stock"
        title="Inventory Items"
        description="Track stock levels, reorder thresholds and which services consume each item."
    >
        <x-slot:actions>
            <a href="{{ route('admin.tags.index') }}" class="btn-gold btn-sm">
                @if ($lowStockCount > 0)
                    <span class="rounded-pill bg-primary px-1.5 py-0.5 text-[10px] text-cream">{{ $lowStockCount }}</span>
                @endif
                Low-Stock Tags
            </a>
            @can('admin.inventory.manage')
                <a href="{{ route('admin.inventory.create') }}" class="btn-primary btn-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Add Item
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="bta-card flex items-center gap-4 p-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-status-low-stock-bg text-status-low-stock">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126Z"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Low Stock</p>
                <p class="font-display text-2xl font-bold text-primary">{{ $lowStockCount }}</p>
            </div>
        </div>

        <div class="bta-card flex items-center gap-4 p-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-status-sold-out-bg text-status-sold-out">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m18.36 5.64-12.72 12.72M6.34 5.64l12.72 12.72"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Sold Out</p>
                <p class="font-display text-2xl font-bold text-primary">{{ $soldOutCount }}</p>
            </div>
        </div>

        <div class="bta-card flex items-center gap-4 p-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
            </span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Items</p>
                <p class="font-display text-2xl font-bold text-primary">{{ $items->total() }}</p>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.inventory.index') }}" class="bta-card mb-6 p-5">
        <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-4">
                <x-ui.form.input name="search" label="Search" placeholder="Name, SKU or supplier" :value="$filters['search'] ?? null" />
            </div>
            <div class="md:col-span-3">
                <x-ui.form.select
                    name="category"
                    label="Category"
                    :includeBlank="true"
                    blankLabel="All Categories"
                    :value="$filters['category'] ?? null"
                    :options="$categories->mapWithKeys(fn ($c) => [$c => $c])->all()"
                />
            </div>
            <div class="md:col-span-3">
                <x-ui.form.select
                    name="tag"
                    label="Status Tag"
                    :includeBlank="true"
                    blankLabel="All Tags"
                    :value="$filters['tag'] ?? null"
                    :options="$tagOptions"
                />
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="btn-primary flex-1">Filter</button>
                @if (array_filter($filters))
                    <a href="{{ route('admin.inventory.index') }}" class="btn-ghost">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if ($items->isEmpty())
        <x-ui.empty title="No items found" description="Add your first inventory item to get started.">
            <x-slot:action>
                @can('admin.inventory.manage')
                    <a href="{{ route('admin.inventory.create') }}" class="btn-primary">Add Item</a>
                @endcan
            </x-slot:action>
        </x-ui.empty>
    @else
        <div class="bta-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="bta-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Reorder At</th>
                            <th>Supplier</th>
                            <th>Tag</th>
                            <th>Linked Services</th>
                            @can('admin.inventory.manage')
                                <th class="text-right">Actions</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr @class(['bg-status-low-stock-bg/25' => $item->isLowOnStock()])>
                                <td>
                                    <p class="font-medium text-primary">{{ $item->name }}</p>
                                    <p class="font-mono text-xs text-ink-muted">{{ $item->sku }}</p>
                                </td>
                                <td class="whitespace-nowrap"><span class="badge badge-gold">{{ $item->category }}</span></td>
                                <td class="whitespace-nowrap text-right">
                                    <span @class([
                                        'font-semibold',
                                        'text-status-sold-out' => $item->isSoldOut(),
                                        'text-status-low-stock' => $item->isLowOnStock() && ! $item->isSoldOut(),
                                        'text-primary' => ! $item->isLowOnStock(),
                                    ])>{{ $item->stock_label }}</span>
                                    @if ($item->hasManualOverride())
                                        <span class="block text-[10px] italic text-ink-muted">manual override</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right text-ink">{{ $item->reorder_threshold }} {{ $item->unit }}</td>
                                <td class="text-ink">{{ $item->supplier ?: '—' }}</td>
                                <td><x-ui.badge :status="$item->status_tag->badge()" :label="$item->status_tag->label()" /></td>
                                <td>
                                    @if ($item->services_count > 0)
                                        <span class="text-sm text-ink">{{ $item->services_count }} service{{ $item->services_count === 1 ? '' : 's' }}</span>
                                    @else
                                        <span class="text-sm text-ink-muted">—</span>
                                    @endif
                                </td>
                                @can('admin.inventory.manage')
                                    <td>
                                        <div class="flex justify-end gap-1.5">
                                            <a href="{{ route('admin.inventory.edit', $item) }}" class="btn-secondary btn-sm">Edit</a>
                                            <form method="POST" action="{{ route('admin.inventory.destroy', $item) }}"
                                                  onsubmit="return confirm('Delete “{{ $item->name }}”?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $items->links() }}</div>
    @endif
@endsection
