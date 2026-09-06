<?php

namespace IsProject\Framework\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Sorting and page size for a generated index screen.
 *
 * Lives in the package rather than in every generated controller so a fix
 * reaches modules that were generated months ago, without regenerating them.
 */
trait ListsRecords
{
    /**
     * Columns this screen may be sorted by.
     *
     * Generated controllers override this. Anything not named here cannot be
     * sorted on — see applySort() for why that matters.
     *
     * @return array<string, string|array{0: string, 1: string, 2: string, 3: string}>
     */
    protected function sortable(): array
    {
        return [];
    }

    /**
     * Apply ?sort= and ?direction= to a query.
     *
     * The sort key is looked up in sortable() rather than used directly. An
     * order-by clause is not a bound parameter — whatever reaches it is
     * concatenated into SQL — so the request may only *choose* from a list the
     * application wrote, never supply a column name of its own.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    protected function applySort(Builder $query, Request $request, ?string $fallback = null): Builder
    {
        $sortable = $this->sortable();
        $key = (string) $request->query('sort', '');

        if (! array_key_exists($key, $sortable)) {
            return $fallback ? $query->orderByDesc($fallback) : $query;
        }

        $direction = strtolower((string) $request->query('direction')) === 'asc' ? 'asc' : 'desc';
        $target = $sortable[$key];

        // A plain column on this table.
        if (is_string($target)) {
            return $query->orderBy($target, $direction);
        }

        // A related table: sort by the name the screen actually shows, not by
        // the foreign key, because ordering "Category" by category_id looks
        // arbitrary to anyone reading the page.
        [$table, $foreign, $owner, $column] = $target;

        return $query
            // Without this the join's columns would be merged into the model's
            // attributes and quietly overwrite them.
            ->select($query->getModel()->getTable().'.*')
            ->leftJoin($table, $foreign, '=', $owner)
            ->orderBy($column, $direction);
    }

    /**
     * How many rows per page, from ?per_page=.
     *
     * "All" is honoured up to a ceiling. An unbounded All on a table with a
     * hundred thousand rows is a hung browser and an exhausted memory limit,
     * so it means "as many as we will render at once" — and if there are more
     * than that, the pagination links appear and say so.
     */
    protected function perPage(Request $request): int
    {
        $options = $this->perPageOptions();
        $max = (int) config('isproject.max_per_page', 200);
        $requested = $request->query('per_page');

        if ($requested === 'all') {
            return $max;
        }

        $requested = (int) $requested;

        return in_array($requested, $options, true)
            ? min($requested, $max)
            : (int) config('isproject.per_page', 15);
    }

    /** @return array<int, int> */
    protected function perPageOptions(): array
    {
        return array_values(array_filter(
            array_map('intval', (array) config('isproject.per_page_options', [15, 25, 50, 100])),
            fn (int $size) => $size > 0,
        ));
    }
}
