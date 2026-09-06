<?php

namespace IsProject\Framework\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use IsProject\Framework\Models\MenuItem;
use Throwable;

/**
 * Where the sidebar's definition comes from.
 *
 * Two sources, and the rule between them is deliberately blunt: if the table
 * holds any row, it is the menu; if it holds none, config('isproject.menu') is.
 * That way installing the migration changes nothing, deleting every row hands
 * control back to config, and there is never a half-managed menu where an entry
 * in config quietly reappears among rows somebody curated.
 *
 * The result is memoised for the request. The sidebar renders once per page, so
 * this is one query per page — cheap, and immune to the staleness a cache store
 * would introduce the moment somebody drags a row.
 */
class MenuManager
{
    /** How deep the manager lets the tree go. */
    public const MAX_DEPTH = 2;

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $memo = null;

    public function __construct(private PermissionRegistry $permissions) {}

    /** Forget the memoised definition. Called for you whenever a row changes. */
    public static function flush(): void
    {
        self::$memo = null;
    }

    /**
     * The menu, in the shape config('isproject.menu') uses.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definition(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            $rows = MenuItem::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get();
        } catch (Throwable) {
            // The table may not exist yet — the shell renders before the first
            // migration, and a sidebar is not worth a 500.
            return self::$memo = $this->configured();
        }

        return self::$memo = $rows->isEmpty()
            ? $this->configured()
            : $this->tree($rows);
    }

    /** Whether the menu is coming from the table rather than from config. */
    public function isManaged(): bool
    {
        try {
            return MenuItem::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The whole tree including switched-off rows, for the management screen.
     *
     * @return Collection<int, MenuItem>
     */
    public function all(): Collection
    {
        return MenuItem::query()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Copy config('isproject.menu') into the table, so there is something to
     * edit rather than an empty screen.
     *
     * Returns the number of rows written. Refuses to run twice: a second import
     * would duplicate every entry, and the button is easy to press again.
     */
    public function importFromConfig(): int
    {
        if ($this->isManaged()) {
            return 0;
        }

        return DB::transaction(function () {
            return $this->insert($this->configured(), null) + $this->ensureManagerLink();
        });
    }

    /**
     * Make sure the menu screen is itself in the menu.
     *
     * A config published before this feature existed has no entry for it. Copy
     * that in and the sidebar becomes database-driven with no way back to the
     * screen that edits it — a dead end reachable only by remembering the URL.
     */
    private function ensureManagerLink(): int
    {
        $route = 'isproject.menu.index';

        if (! Route::has($route) || MenuItem::query()->where('route_name', $route)->exists()) {
            return 0;
        }

        MenuItem::query()->create([
            'type' => MenuItem::TYPE_ROUTE,
            'label' => 'Menu',
            'icon' => 'menu',
            'route_name' => $route,
            'permission' => $route,
            'is_active' => true,
            'position' => $this->nextPosition(),
        ]);

        return 1;
    }

    /**
     * Modules with a listing route that nothing in the menu points at.
     *
     * This is the answer to "I generated it and it is not in the sidebar": the
     * routes exist, so they can be discovered and offered, rather than leaving
     * somebody to guess the route name.
     *
     * @return array<int, array{route: string, label: string}>
     */
    public function unlisted(): array
    {
        $known = $this->routeNamesInUse();
        $missing = [];

        foreach ($this->permissions->modules() as $module) {
            $index = $module['abilities']['index'] ?? null;

            // The framework's own screens are already in the default menu, and
            // a route that takes parameters has no single page to link to.
            if ($index === null || str_starts_with($module['key'], 'isproject')) {
                continue;
            }

            if (! in_array($index['name'], $known, true) && ! str_contains($index['uri'], '{')) {
                $missing[] = ['route' => $index['name'], 'label' => $module['label']];
            }
        }

        return $missing;
    }

    /**
     * Apply a new order.
     *
     * The payload is a flat list of {id, parent} in the order they should
     * appear. Positions are assigned from that order rather than trusted from
     * the browser, so a payload cannot invent gaps or duplicates.
     *
     * @param  array<int, array{id: mixed, parent: mixed}>  $order
     *
     * @throws \InvalidArgumentException when the payload would build an illegal tree
     */
    public function reorder(array $order): void
    {
        $rows = MenuItem::query()->get()->keyBy('id');
        $seen = [];
        $positions = [];
        $updates = [];

        foreach ($order as $entry) {
            $id = (int) ($entry['id'] ?? 0);
            $parent = ($entry['parent'] ?? null) === null ? null : (int) $entry['parent'];

            if (! $rows->has($id) || isset($seen[$id])) {
                throw new \InvalidArgumentException('The new order does not match the menu.');
            }

            if ($parent !== null && ($parent === $id || ! $rows->has($parent))) {
                throw new \InvalidArgumentException('An item cannot be nested under that one.');
            }

            $seen[$id] = true;

            $key = $parent ?? 0;
            $positions[$key] = ($positions[$key] ?? -1) + 1;

            $updates[] = ['id' => $id, 'parent_id' => $parent, 'position' => $positions[$key]];
        }

        // Depth is checked against the finished shape, not row by row: moving a
        // parent under another row and moving its children out can arrive in
        // either order within one payload.
        $this->assertDepth($updates, $rows);

        DB::transaction(function () use ($updates) {
            foreach ($updates as $update) {
                MenuItem::query()->whereKey($update['id'])->update([
                    'parent_id' => $update['parent_id'],
                    'position' => $update['position'],
                    'updated_at' => now(),
                ]);
            }
        });

        self::flush();
    }

    /** The next position at the top level, so a new row lands at the end. */
    public function nextPosition(?int $parentId = null): int
    {
        return (int) MenuItem::query()
            ->where('parent_id', $parentId)
            ->max('position') + 1;
    }

    /**
     * Route names that may be linked to, for the picker.
     *
     * @return array<int, string>
     */
    public function linkableRoutes(): array
    {
        $names = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || str_contains($route->uri(), '{') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $names[] = $name;
        }

        sort($names);

        return array_values(array_unique($names));
    }

    /**
     * Rows that would be illegal parents for the given item: itself, and
     * anything already nested, since the tree stops at MAX_DEPTH.
     *
     * @return Collection<int, MenuItem>
     */
    public function possibleParents(?MenuItem $item = null): Collection
    {
        return MenuItem::query()
            ->whereNull('parent_id')
            ->where('type', '!=', MenuItem::TYPE_HEADING)
            ->when($item?->exists, fn ($query) => $query->whereKeyNot($item->getKey()))
            ->orderBy('position')
            ->get();
    }

    /**
     * @param  array<int, array{id: int, parent_id: ?int, position: int}>  $updates
     * @param  Collection<int, MenuItem>  $rows
     */
    private function assertDepth(array $updates, Collection $rows): void
    {
        $parents = [];

        foreach ($updates as $update) {
            $parents[$update['id']] = $update['parent_id'];
        }

        // Anything the payload left out keeps the parent it has.
        foreach ($rows as $row) {
            $parents[$row->id] ??= $row->parent_id;
        }

        foreach ($parents as $id => $parent) {
            if ($parent === null) {
                continue;
            }

            if (($parents[$parent] ?? null) !== null) {
                throw new \InvalidArgumentException('The menu can only go two levels deep.');
            }

            if (($rows[$parent]->type ?? null) === MenuItem::TYPE_HEADING) {
                throw new \InvalidArgumentException('A heading cannot hold items. Use it to label the group instead.');
            }
        }
    }

    /**
     * @param  Collection<int, MenuItem>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function tree(Collection $rows): array
    {
        $children = $rows->whereNotNull('parent_id')->groupBy('parent_id');

        return $rows
            ->whereNull('parent_id')
            ->map(fn (MenuItem $item) => $item->definition(
                ($children[$item->id] ?? collect())
                    ->map(fn (MenuItem $child) => $child->definition())
                    ->all(),
            ))
            ->values()
            ->all();
    }

    /**
     * Write a config-shaped definition into the table.
     *
     * @param  array<int, array<string, mixed>>  $definition
     */
    private function insert(array $definition, ?int $parentId, int $depth = 1): int
    {
        $written = 0;
        $position = 0;

        foreach ($definition as $entry) {
            $entry = (array) $entry;
            $children = (array) ($entry['children'] ?? []);

            $item = MenuItem::query()->create([
                'parent_id' => $parentId,
                'position' => $position++,
                'type' => $this->typeOf($entry),
                'label' => $entry['heading'] ?? $entry['label'] ?? 'Untitled',
                'icon' => $entry['icon'] ?? null,
                'route_name' => $entry['route'] ?? null,
                'url' => $entry['url'] ?? null,
                'permission' => is_array($entry['can'] ?? null) ? ($entry['can'][0] ?? null) : ($entry['can'] ?? null),
                'badge' => $entry['badge'] ?? null,
                'opens_in_new_tab' => ($entry['target'] ?? null) === '_blank',
                'is_active' => true,
            ]);

            $written++;

            if ($children !== [] && $depth < self::MAX_DEPTH) {
                $written += $this->insert($children, $item->id, $depth + 1);
            }
        }

        return $written;
    }

    /** @param  array<string, mixed>  $entry */
    private function typeOf(array $entry): string
    {
        if (isset($entry['heading'])) {
            return MenuItem::TYPE_HEADING;
        }

        if (! empty($entry['route'])) {
            return MenuItem::TYPE_ROUTE;
        }

        return str_starts_with((string) ($entry['url'] ?? ''), 'http')
            ? MenuItem::TYPE_EXTERNAL
            : MenuItem::TYPE_INTERNAL;
    }

    /** @return array<int, string> */
    private function routeNamesInUse(): array
    {
        if (! $this->isManaged()) {
            return $this->routeNamesIn($this->configured());
        }

        return MenuItem::query()->whereNotNull('route_name')->pluck('route_name')->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $definition
     * @return array<int, string>
     */
    private function routeNamesIn(array $definition): array
    {
        $names = [];

        foreach ($definition as $entry) {
            $entry = (array) $entry;

            if (! empty($entry['route'])) {
                $names[] = $entry['route'];
            }

            $names = array_merge($names, $this->routeNamesIn((array) ($entry['children'] ?? [])));
        }

        return $names;
    }

    /** @return array<int, array<string, mixed>> */
    private function configured(): array
    {
        return (array) config('isproject.menu', []);
    }
}
