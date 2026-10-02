@extends('layouts.admin')

@section('title', 'Inventory')
@section('heading', 'Inventory Management')

@section('content')
    @php
        // The Actions column is capability-gated, so the empty rows have to
        // span whatever the admin who is looking actually gets. The Category
        // column is gone, which took this down from 6/5 to 5/4.
        $canManage = auth('admin')->user()?->can('admin.inventory.manage');
        $columns = $canManage ? 5 : 4;
    @endphp

    {{-- The export carries the search box, so the file is the list the admin is
         looking at rather than the whole catalogue. It has no date filter, unlike
         the sales export: quantity is overwritten on every booking rather than
         versioned, so there is no "stock as it was on" reading for a date range
         to select. --}}
    <x-ui.admin-table
        add-label="Add Item"
        :add-href="$canManage ? route('admin.inventory.create') : null"
        :search="$search"
    >
        {{-- The `actions` slot rather than `header`: the add button stays, and the
         export joins it on the same row. The link carries the search box, so the
         file is the list the admin is looking at rather than the whole
         catalogue.

         There is deliberately no date filter on it, unlike the sales export:
         quantity is overwritten on every booking rather than versioned, so there
         is no "stock as it was on" reading for a range to select. A filter that
         cannot change the answer is worse than no filter. --}}
        <x-slot:actions>
            <a href="{{ route('admin.inventory.export', array_filter(['search' => $search])) }}" class="btn-secondary btn-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export Inventory CSV
            </a>
        </x-slot:actions>
        <table class="bta-table">
            <thead>
                <tr>
                    <x-ui.sortable-th column="name" label="Item" :sort="$sort" :direction="$direction" :action="route('admin.inventory.index')" :params="['search' => $search]" />
                    <x-ui.sortable-th column="quantity" label="Quantity" :sort="$sort" :direction="$direction" :action="route('admin.inventory.index')" :params="['search' => $search]" align="right" />
                    <x-ui.sortable-th column="status_tag" label="Tag" :sort="$sort" :direction="$direction" :action="route('admin.inventory.index')" :params="['search' => $search]" />
                    <x-ui.sortable-th column="expiry_date" label="Expiry" :sort="$sort" :direction="$direction" :action="route('admin.inventory.index')" :params="['search' => $search]" />
                    @can('admin.inventory.manage')
                        <th class="text-right">Actions</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr data-row x-show="isShown({{ $loop->index }})">
                        <td>
                            <p class="font-medium text-primary">{{ $item->name }}</p>
                            <p class="font-mono text-xs text-ink-muted">{{ $item->sku }}</p>
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <span @class([
                                'font-semibold',
                                'text-status-sold-out' => $item->isSoldOut(),
                                'text-status-low-stock' => $item->isLowOnStock() && ! $item->isSoldOut(),
                                'text-primary' => ! $item->isLowOnStock(),
                            ])>{{ $item->stock_label }}</span>
                        </td>
                        <td><x-ui.badge :status="$item->status_tag->badge()" :label="$item->status_tag->label()" /></td>
                        <td class="whitespace-nowrap text-ink">{{ $item->expiry_date?->format('M j, Y') ?? '—' }}</td>
                        @can('admin.inventory.manage')
                            <td>
                                <div class="flex items-center justify-end gap-1.5">
                                    <x-ui.icon-action
                                        label="Edit {{ $item->name }}"
                                        icon="heroicon-o-pencil-square"
                                        tone="primary"
                                        :href="route('admin.inventory.edit', $item)"
                                    />

                                    {{-- The row carries no form of its own. Clicking
                                         opens the confirmation dialog further down,
                                         which holds the single real form and posts
                                         to the URL the row hands it. --}}
                                    <button
                                        type="button"
                                        class="icon-action icon-action-danger"
                                        title="Delete {{ $item->name }}"
                                        aria-label="Delete {{ $item->name }}"
                                        @click="$dispatch('confirm-inventory-delete', {
                                            label: @js($item->name),
                                            action: @js(route('admin.inventory.destroy', $item)),
                                        })"
                                    >
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columns }}" class="py-12 text-center text-sm text-ink-muted">
                            No items yet. Use “Add Item” to add what the salon actually holds.
                        </td>
                    </tr>
                @endforelse

                {{-- Shown when the search box has filtered every row away. --}}
                @if ($items->isNotEmpty())
                    <tr x-show="total === 0" x-cloak>
                        <td colspan="{{ $columns }}" class="py-12 text-center text-sm text-ink-muted">No data available</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </x-ui.admin-table>
@endsection

@push('modals')
    @can('admin.inventory.manage')
        {{--
            Delete an item. One dialog, one real form, and the row decides which
            item it is about — the same arrangement as the calendar's "Mark Date
            as Available", so a destructive action in this panel always looks
            and behaves the same way.
        --}}
        <div
            x-data="{ open: false, label: '', action: '' }"
            x-show="open"
            x-cloak
            @confirm-inventory-delete.window="label = $event.detail.label; action = $event.detail.action; open = true"
            @keydown.escape.window="open = false"
            class="fixed inset-0 z-[60] flex items-end justify-center overflow-y-auto p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-item-title"
        >
            <div class="modal-backdrop fixed inset-0" @click="open = false" aria-hidden="true"></div>

            <div @click.stop class="modal-panel max-w-md">
                <form method="POST" :action="action">
                    @csrf
                    @method('DELETE')

                    <div class="flex items-start justify-between gap-4 border-b border-line/70 px-5 py-4">
                        <h2 id="delete-item-title" class="font-display text-lg font-semibold text-primary">
                            Delete Item
                        </h2>
                        <button type="button" @click="open = false" class="btn-ghost btn-sm" aria-label="Close">&times;</button>
                    </div>

                    <div class="px-5 py-5">
                        <p class="text-sm leading-relaxed text-ink">
                            Are you sure you want to delete
                            <span class="font-semibold text-primary" x-text="label"></span>?
                            It will be removed from the stockroom and will no longer be counted.
                        </p>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2 border-t border-line/70 bg-linen/50 px-5 py-4">
                        <button type="button" class="btn-ghost" @click="open = false">Cancel</button>
                        <button type="submit" class="btn-danger">Delete Item</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endpush
