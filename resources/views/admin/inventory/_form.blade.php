{{--
    Shared inventory create/edit fields: a name, when the stock came in, when it
    runs out, and how much there is.

    Two fields that used to be here are gone.

    The Category dropdown listed the categories the admin created, active only.
    The columns behind it — the gold badge in the list, the sort, the Category
    column in the CSV export — were never about the stock: they re-filed the
    service catalogue under a second set of names, and an admin had to pick the
    same label twice in two different places for it to match. So the item is no
    longer asked to choose one, and `InventoryItem::DEFAULT_CATEGORY` is written
    instead. All three of those columns have since been removed as well, so
    nothing collects, shows or exports it. Existing rows keep the category they
    were saved with.

    The Unit dropdown went the same way, for the same reason it was never worth
    being a question: the salon counts in pieces. See `DEFAULT_UNIT`.

    `x-ui.page-header` is gone because the top bar already says which page this
    is, and this is the Add Category form's shape — one card, fields stacked in a
    single readable column, one submit button below a divider.
--}}
<form method="POST" action="{{ $action }}" novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif


    <div class="max-w-2xl space-y-6">
        <x-ui.form.input
            name="name"
            label="Item Name"
            required
            :value="old('name', $item->name)"
            placeholder="e.g. Gelish Top Coat"
        />

        <div class="grid gap-6 sm:grid-cols-2">
            <x-ui.form.input
                name="date_in"
                type="date"
                label="Date In"
                required
                :value="old('date_in', $item->date_in?->format('Y-m-d'))"
            />

            <x-ui.form.input
                name="expiry_date"
                type="date"
                label="Expiry Date"
                :value="old('expiry_date', $item->expiry_date?->format('Y-m-d'))"
            />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <x-ui.form.input
                name="quantity"
                type="number"
                step="0.01"
                min="0"
                max="9999999"
                label="Quantity"
                required
                :value="old('quantity', $item->quantity ?? 0)"
            />
        </div>
    </div>

    <div class="mt-7 border-t border-primary/10 pt-6">
        <button type="submit" class="btn-primary">
            <x-heroicon-o-check class="h-4 w-4" />
            {{ $submitLabel }}
        </button>
    </div>
</form>
