@extends('layouts.admin')

@section('title', 'Low-Stock Tags')
@section('heading', 'Low-Stock / Not-Available Tagging')

@section('content')
    <x-ui.page-header
        eyebrow="Stock Status"
        title="Low-Stock & Availability Tags"
        description="Tag items as Low Stock, Sold Out or Best Seller. Low Stock is suggested automatically when quantity drops to the reorder threshold."
    >
        <x-slot:actions>
            <a href="{{ route('admin.inventory.index') }}" class="btn-secondary btn-sm">Manage Items</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert type="info" class="mb-6">
        The suggestion below compares each item's quantity against its reorder threshold. Choosing a
        tag explicitly overrides that — useful when an item is temporarily unavailable for another reason.
    </x-ui.alert>

    {{-- Filters --}}
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ([
            'attention' => 'Needs Attention',
            'low_stock' => 'Low Stock',
            'sold_out' => 'Sold Out',
            'best_seller' => 'Best Seller',
            'override' => 'Manually Overridden',
            'all' => 'All Items',
        ] as $value => $label)
            <a href="{{ route('admin.tags.index', ['filter' => $value]) }}"
               class="rounded-pill px-3.5 py-1.5 text-sm font-medium transition {{ $filter === $value ? 'bg-primary text-cream' : 'bg-cream text-ink hover:bg-linen' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="grid gap-6 2xl:grid-cols-3">
        <div class="2xl:col-span-2">
            <form method="POST" action="{{ route('admin.tags.bulk') }}" x-data="bulkTagger()" novalidate>
                @csrf
                @method('PATCH')

                <x-ui.card title="Items" subtitle="Select rows to tag several at once, or tag an item inline.">
                    <x-slot:actions>
                        <div class="flex items-end gap-2" x-show="selected.length > 0" x-cloak>
                            <select name="status_tag" class="input w-40 py-1.5 text-xs" x-model="bulkTag">
                                <option value="">Choose tag…</option>
                                @foreach ($tagOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-primary btn-sm" x-bind:disabled="bulkTag === ''">Apply to <span x-text="selected.length"></span></button>
                        </div>
                    </x-slot:actions>

                    @if ($items->isEmpty())
                        <x-ui.empty title="No items in this view" description="Try a different filter." />
                    @else
                        <div class="overflow-x-auto">
                            <table class="bta-table">
                                <thead>
                                    <tr>
                                        <th class="w-10">
                                            <input type="checkbox" class="checkbox" x-model="all" @change="toggleAll" aria-label="Select all">
                                        </th>
                                        <th>Item</th>
                                        <th class="text-right">Quantity</th>
                                        <th class="text-right">Reorder At</th>
                                        <th>Suggested</th>
                                        <th>Current Tag</th>
                                        <th>Change To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $item)
                                        @php $suggested = $item->suggestedTag(); @endphp
                                        <tr @class(['bg-status-low-stock-bg/25' => $item->isLowOnStock()])>
                                            <td>
                                                <input type="checkbox" class="checkbox" name="items[]" value="{{ $item->id }}"
                                                       x-model="selected" aria-label="Select {{ $item->name }}">
                                            </td>
                                            <td>
                                                <p class="font-medium text-primary">{{ $item->name }}</p>
                                                <p class="font-mono text-xs text-ink-muted">{{ $item->sku }}</p>
                                            </td>
                                            <td class="whitespace-nowrap text-right font-medium text-primary">{{ $item->stock_label }}</td>
                                            <td class="whitespace-nowrap text-right text-ink">{{ $item->reorder_threshold }} {{ $item->unit }}</td>
                                            <td><x-ui.badge :status="$suggested->badge()" :label="$suggested->label()" /></td>
                                            <td>
                                                <x-ui.badge :status="$item->status_tag->badge()" :label="$item->status_tag->label()" />
                                                @if ($item->hasManualOverride())
                                                    <span class="mt-1 block text-[10px] italic text-ink-muted">override</span>
                                                @endif
                                            </td>
                                            <td>
                                                <form method="POST" action="{{ route('admin.tags.update', $item) }}" class="flex items-center gap-1.5">
                                                    @csrf
                                                    @method('PATCH')
                                                    <select name="status_tag" class="input w-36 py-1.5 text-xs" onchange="this.form.submit()">
                                                        @foreach ($tagOptions as $value => $label)
                                                            <option value="{{ $value }}" @selected($item->status_tag->value === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <noscript><button type="submit" class="btn-primary btn-sm">Set</button></noscript>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-5">{{ $items->links() }}</div>
                    @endif
                </x-ui.card>
            </form>
        </div>

        {{-- Per-service impact --}}
        <aside class="space-y-6">
            <x-ui.card title="Service Impact" subtitle="Services that depend on a low-stock or sold-out item.">
                @if ($suggestions->isEmpty())
                    <p class="text-sm text-ink-muted">Nothing critical right now — no service depends on a flagged item.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($suggestions as $row)
                            <li class="rounded-xl border border-status-low-stock/25 bg-status-low-stock-bg/30 p-3.5">
                                <p class="text-sm font-medium text-primary">{{ $row['service'] }}</p>
                                <ul class="mt-1.5 space-y-1">
                                    @foreach ($row['items'] as $linked)
                                        <li class="flex items-center justify-between gap-2 text-xs">
                                            <span class="text-ink">{{ $linked['name'] }}</span>
                                            <x-ui.badge :status="$linked['status']" />
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="How the suggestion works">
                <ul class="space-y-2.5 text-sm text-ink-muted">
                    <li class="flex gap-2.5">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold" />
                        <span><span class="font-medium text-primary">quantity &le; 0</span> &rarr; Sold Out</span>
                    </li>
                    <li class="flex gap-2.5">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold" />
                        <span><span class="font-medium text-primary">quantity &le; reorder threshold</span> &rarr; Low Stock</span>
                    </li>
                    <li class="flex gap-2.5">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold" />
                        <span>otherwise &rarr; Available</span>
                    </li>
                    <li class="flex gap-2.5">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold" />
                        <span>A <span class="font-medium text-primary">Best Seller</span> tag survives quantity changes until the item runs out.</span>
                    </li>
                </ul>
            </x-ui.card>
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bulkTagger', () => ({
                selected: [],
                all: false,
                bulkTag: '',

                toggleAll() {
                    document.querySelectorAll('input[name="items[]"]').forEach((box) => {
                        box.checked = this.all;
                    });

                    this.selected = this.all
                        ? Array.from(document.querySelectorAll('input[name="items[]"]')).map((box) => box.value)
                        : [];
                },
            }));
        });
    </script>
@endpush
