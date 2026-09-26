{{-- Shared inventory create/edit form. --}}
@extends('layouts.admin')

@section('title', $item->exists ? 'Edit Item' : 'Add Item')
@section('heading', $item->exists ? 'Edit Inventory Item' : 'Add Inventory Item')

@section('content')
    <a href="{{ route('admin.inventory.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        Back to inventory
    </a>

    <x-ui.page-header
        eyebrow="Stock"
        :title="$item->exists ? 'Edit Inventory Item' : 'Add Inventory Item'"
        :description="$item->exists
            ? 'Update stock levels, thresholds and linked services for “'.$item->name.'”.'
            : 'Create a new tracked item and link it to the services that consume it.'"
    />

    <x-ui.alert type="error" class="mb-6" :dismissible="false">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </x-ui.alert>

    @php
        $action = $item->exists ? route('admin.inventory.update', $item) : route('admin.inventory.store');
        $linked = $item->exists ? ($linkedServiceIds ?? []) : [];
        $quantities = old('quantities', $item->exists ? $item->services->mapWithKeys(fn ($s) => [$s->id => $s->pivot->quantity_per_service])->all() : []);
    @endphp

    <form method="POST" action="{{ $action }}" x-data="itemForm()" novalidate>
        @csrf
        @if ($item->exists) @method('PUT') @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-ui.card title="Item Details">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.form.input name="name" label="Item Name" required :value="old('name', $item->name)" placeholder="e.g. Gelish Top Coat" />
                        <x-ui.form.input name="sku" label="SKU" required :value="old('sku', $item->sku)" placeholder="INV-NC-003" />
                    </div>

                    <div class="mt-5">
                        <x-ui.form.input
                            name="category"
                            label="Category"
                            required
                            :value="old('category', $item->category)"
                            list="bta-item-categories"
                            placeholder="e.g. Nail Care"
                        />
                        <datalist id="bta-item-categories">
                            @foreach ($categories as $category)
                                <option value="{{ $category }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-3">
                        <x-ui.form.input name="quantity" type="number" step="0.01" min="0" label="Quantity / Stock Level" required :value="old('quantity', $item->quantity ?? 0)" />
                        <x-ui.form.select name="unit" label="Unit" required :value="old('unit', $item->unit ?? 'pcs')" :options="collect($units)->mapWithKeys(fn ($u) => [$u => $u])->all()" />
                        <x-ui.form.input name="reorder_threshold" type="number" step="0.01" min="0" label="Reorder Threshold" required :value="old('reorder_threshold', $item->reorder_threshold ?? 0)" />
                    </div>

                    <p class="mt-3 text-xs text-ink-muted" x-show="suggestion" x-cloak>
                        <span class="font-medium text-status-low-stock">Suggested tag:</span>
                        <span x-text="suggestion"></span>
                    </p>

                    <div class="mt-5">
                        <x-ui.form.input name="supplier" label="Supplier (optional)" :value="old('supplier', $item->supplier)" placeholder="e.g. Beauty Depot PH" />
                    </div>

                    <div class="mt-5">
                        <x-ui.form.textarea name="notes" label="Notes" rows="3" :value="old('notes', $item->notes)" />
                    </div>
                </x-ui.card>

                {{-- Linked services (many-to-many) --}}
                <x-ui.card title="Linked Services" subtitle="Which services consume this item. Booking them reduces the quantity automatically.">
                    @if ($allServices->isEmpty())
                        <p class="text-sm text-ink-muted">No active services yet.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($allServices as $service)
                                <label class="flex flex-wrap items-center gap-3 rounded-xl border border-primary/12 bg-linen/50 px-4 py-3 transition hover:border-gold">
                                    <input type="checkbox" class="checkbox" :name="`services[]`" :value="{{ $service->id }}" x-model="selected">

                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-ink">{{ $service->name }}</span>
                                        <span class="block text-xs text-ink-muted">{{ $service->category }}</span>
                                    </span>

                                    <span class="flex items-center gap-2" x-show="selected.includes({{ $service->id }})" x-cloak>
                                        <label class="text-xs text-ink-muted" for="qty-{{ $service->id }}">Used per booking</label>
                                        <input
                                            id="qty-{{ $service->id }}"
                                            :name="`quantities[{{ $service->id }}]`"
                                            type="number" step="0.01" min="0.01"
                                            class="input w-24 py-1.5 text-sm"
                                            x-model="quantities[{{ $service->id }}]"
                                        >
                                        <span class="text-xs text-ink-muted">{{ $item->unit ?? 'pcs' }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            </div>

            <aside class="space-y-6">
                <x-ui.card title="Status Tag" subtitle="Auto-suggested from quantity; you can override.">
                    <x-ui.form.select
                        name="status_tag"
                        label="Tag"
                        :includeBlank="true"
                        blankLabel="Auto (derive from quantity)"
                        :value="old('status_tag', $item->exists ? $item->status_tag->value : null)"
                        :options="\App\Enums\ItemTag::assignableOptions()"
                    />
                </x-ui.card>

                <x-ui.card title="Visibility">
                    <x-ui.form.checkbox name="is_active" value="1" :checked="old('is_active', $item->exists ? $item->is_active : true)" label="Active" />
                </x-ui.card>

                <div class="flex flex-col gap-3">
                    <button type="submit" class="btn-primary w-full">{{ $item->exists ? 'Save Changes' : 'Create Item' }}</button>
                    <a href="{{ route('admin.inventory.index') }}" class="btn-ghost w-full">Cancel</a>
                </div>
            </aside>
        </div>
    </form>
@endsection

@push('scripts')
    @php
        $itemSeed = [
            'selected' => collect(old('services', $linked))->map(fn ($id) => (int) $id)->values(),
            'quantities' => collect($quantities)->map(fn ($v) => (float) $v),
        ];
    @endphp

    <script>
        /**
         * Linked-service picker + live "suggested tag" hint. The suggestion is
         * advisory only: the server re-derives it in resolveTag().
         */
        document.addEventListener('alpine:init', () => {
            Alpine.data('itemForm', () => ({
                selected: {!! json_encode($itemSeed['selected'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                quantities: {!! json_encode($itemSeed['quantities'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                suggestion: '',

                init() {
                    this.refresh();

                    ['quantity', 'reorder_threshold'].forEach((field) => {
                        const input = document.querySelector('[name="' + field + '"]');

                        if (input) input.addEventListener('input', () => this.refresh());
                    });
                },

                refresh() {
                    const quantity = parseFloat((document.querySelector('[name="quantity"]') || {}).value || 0);
                    const threshold = parseFloat((document.querySelector('[name="reorder_threshold"]') || {}).value || 0);

                    if (Number.isNaN(quantity) || Number.isNaN(threshold)) {
                        this.suggestion = '';
                        return;
                    }

                    if (quantity <= 0) {
                        this.suggestion = 'Sold Out / Unavailable';
                    } else if (quantity <= threshold) {
                        this.suggestion = 'Low Stock (quantity is at or below the reorder threshold)';
                    } else {
                        this.suggestion = 'Available';
                    }
                },
            }));
        });
    </script>
@endpush
