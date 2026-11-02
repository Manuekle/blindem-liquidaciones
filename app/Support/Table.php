<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

// Server-side sorting and page size for list tables, read from ?sort=&dir=&per_page= (see partials/th-sort and
// partials/table-footer). Only whitelisted keys sort, so the query string can never reach an arbitrary column.
class Table
{
    public const PAGE_SIZES = [12, 25, 50];

    /** @param  array<string, mixed>  $columns  sort key => column name or subquery builder */
    public static function sort(Builder $query, Request $request, array $columns): Builder
    {
        $key = (string) $request->query('sort');
        if (! array_key_exists($key, $columns)) {
            return $query->latest()->orderByDesc($query->getModel()->getQualifiedKeyName());
        }

        return $query->orderBy($columns[$key], $request->query('dir') === 'desc' ? 'desc' : 'asc')
            ->orderBy($query->getModel()->getQualifiedKeyName());
    }

    public static function perPage(Request $request): int
    {
        $size = (int) $request->query('per_page');

        return in_array($size, self::PAGE_SIZES, true) ? $size : self::PAGE_SIZES[0];
    }
}
