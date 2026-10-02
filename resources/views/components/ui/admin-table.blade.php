@props([
    'addLabel' => null,
    'addHref' => null,
    'search' => null,
    'perPage' => 10,
    'emptyMessage' => 'No data available',
])

{{--
    The white card every admin list sits in, matching the reference's
    `content-card`: the add button top-right inside the card, then a search box
    on the left with the entries-per-page select on the right of the same row,
    then the table, then the pager below it and right-aligned.

    No page header — the top bar already carries the page title, and the
    reference list pages have nothing above the card either. No search
    submit and no clear button: `adminTable` filters as the admin types.

Three optional slots let a screen put its own controls in the same card
     instead of reaching for a second one:

      - `header` replaces the add-button row at the very top of the card. The
        Reports screen uses it for its date-range summary, with both export
        buttons on the right where the add button would be.
      - `actions` adds controls to that same row *alongside* the add button, for
        a list that wants to keep the default row and add to it. The Inventory
        screen uses it for its export. Mutually exclusive with `header`, since
        both claim the top row.
      - `filters` sits between that row and the search/entries toolbar, for
        controls that shape the rows rather than the list view — the Reports
        date-range filters.

     A screen that wants the toolbar gone entirely can still use this card with
     an empty `filters`; nothing here depends on them existing.

    The slot is a whole `<table>`; each of its body rows carries `data-row` and
    `x-show="isShown($loop->index)"`. Rows without `data-row` — the "nothing
    here yet" row, the "no matches" row — are left out of the index on purpose.
--}}
<div class="bta-card p-5" x-data="adminTable(@js(['search' => $search, 'perPage' => $perPage]))">
    @if (isset($header))
        {{ $header }}
    @elseif (isset($actions) || $addHref)
        {{-- `gap-2` and the export first: an export is a read of this list, so it
             sits left of the add button rather than taking the primary slot. The
             row renders even with no add button, so a list whose only top-row
             control is an export still gets one. --}}
        <div class="mb-5 flex flex-wrap items-center justify-end gap-2">
            @isset($actions)
                {{ $actions }}
            @endisset

            @if ($addHref)
                <a href="{{ $addHref }}" class="btn-primary btn-sm">
                    <x-heroicon-o-plus class="h-4 w-4" />
                    {{ $addLabel }}
                </a>
            @endif
        </div>
    @endif

    @isset($filters)
        {{ $filters }}
    @endisset

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <label class="flex items-center gap-2">
            <span class="text-sm text-ink-muted">Search:</span>
            {{-- `value` is rendered as well as bound, so a deep link with a
                 `?search=` arrives with the box already filled rather than
                 flashing empty until Alpine takes over. --}}
            <input
                type="search"
                x-model="search"
                value="{{ $search }}"
                class="input w-56 py-1.5 text-sm"
                placeholder="Search"
                aria-label="Search"
            >
        </label>

        <label class="flex items-center gap-2 sm:ml-auto">
            <select x-model.number="perPage" class="input w-20 py-1.5 pr-8 text-sm" aria-label="Entries per page">
                <template x-for="option in perPageOptions" :key="option">
                    <option :value="option" x-text="option"></option>
                </template>
            </select>
            <span class="text-sm text-ink-muted">entries per page</span>
        </label>
    </div>

    <div class="overflow-x-auto">
        {{ $slot }}
    </div>

    <div class="mt-4 flex items-center justify-end gap-1" x-show="hasPages" x-cloak>
        <button
            type="button"
            class="table-page-btn"
            :disabled="isFirstPage"
            @click="go(page - 1)"
            aria-label="Previous page"
        >&laquo;</button>

        <template x-for="number in pageNumbers" :key="number">
            <button
                type="button"
                class="table-page-btn"
                :class="isCurrentPage(number) && 'table-page-btn-current'"
                :aria-current="isCurrentPage(number) ? 'page' : false"
                @click="go(number)"
                x-text="number"
            ></button>
        </template>

        <button
            type="button"
            class="table-page-btn"
            :disabled="isLastPage"
            @click="go(page + 1)"
            aria-label="Next page"
        >&raquo;</button>
    </div>
</div>
