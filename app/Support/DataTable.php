<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Query-string state for the admin catalogue tables: column sort.
 *
 * The tables themselves are filtered and paged in the browser by
 * `Alpine.data('adminTable')`, so only the sort has to reach the database. That
 * is deliberate — the reference admin paged and filtered its tables on the
 * client too, and a live-as-you-type search cannot work if every keystroke is a
 * round trip.
 *
 * A `?search=` is still accepted, but only to seed the search box. Filtering on
 * it as well would mean a reload mid-search narrows the row set twice over, and
 * clearing the box would then leave the admin looking at a subset they never
 * asked for.
 */
class DataTable
{
    public const DIRECTIONS = ['asc', 'desc'];

    /**
     * The requested sort as a [column, direction] pair, both validated against
     * the columns the screen actually allows.
     *
     * @param  array<int, string>  $sortable
     * @return array{0: string, 1: string}
     */
    public static function sort(Request $request, array $sortable, string $default): array
    {
        $column = (string) $request->query('sort', $default);
        $direction = strtolower((string) $request->query('direction', 'asc'));

        return [
            in_array($column, $sortable, true) ? $column : $default,
            in_array($direction, self::DIRECTIONS, true) ? $direction : 'asc',
        ];
    }

    /**
     * Order by a column that has already been through `sort()`, so the value is
     * known to be on the allow-list.
     *
     * @param  array<int, string>  $sortable
     */
    public static function applySort(
        Builder $query,
        string $column,
        string $direction,
        array $sortable,
        string $default,
    ): Builder {
        if (! in_array($column, $sortable, true)) {
            $column = $default;
        }

        return $query->orderBy($column, $direction);
    }

    /**
     * The sort query string a column header should link to, flipping the
     * direction when the column is already the active one.
     *
     * `$params` carries the current search term so a sort click reloads with
     * the admin's filter still in the box rather than silently clearing it.
     *
     * @param  array<string, mixed>  $params
     */
    public static function sortQuery(array $params, string $column, string $sort, string $direction): string
    {
        return http_build_query([
            ...array_filter($params, static fn ($value) => $value !== null && $value !== ''),
            'sort' => $column,
            'direction' => $column === $sort && $direction === 'asc' ? 'desc' : 'asc',
        ]);
    }
}
