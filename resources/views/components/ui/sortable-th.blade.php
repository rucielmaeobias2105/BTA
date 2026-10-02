@props([
    'column',
    'label',
    'sort',
    'direction',
    'action',
    'params' => [],
    'align' => 'left',
])

{{--
    A sortable column header.

    The table is server-side, so sorting is a link rather than a click handler:
    the same `sort`/`direction` pair the controller reads. The next direction is
    "descending" when this column is already ascending, "ascending" otherwise,
    which is the behaviour `DataTable::sortQuery()` implements for the query
    string as well.
--}}
@php
    $isActive = $sort === $column;
    $query = \App\Support\DataTable::sortQuery($params, $column, $sort, $direction);
@endphp

<th {{ $attributes->merge(['class' => 'p-0']) }}>
    <a
        href="{{ $action }}{{ $query ? '?'.$query : '' }}"
        title="Sort by {{ strtolower($label) }}"
        aria-sort="{{ $isActive ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"
        @class([
            'flex items-center gap-1.5 px-4 py-3 transition hover:text-primary',
            'justify-end' => $align === 'right',
            'justify-center' => $align === 'center',
            'text-primary' => $isActive,
        ])
    >
        <span>{{ $label }}</span>
        <span @class(['text-[10px] leading-none', 'opacity-80' => $isActive, 'opacity-35' => ! $isActive]) aria-hidden="true">
            @if ($isActive)
                {{ $direction === 'asc' ? "\u{25B2}" : "\u{25BC}" }}
            @else
                {{ "\u{21C5}" }}
            @endif
        </span>
    </a>
</th>
