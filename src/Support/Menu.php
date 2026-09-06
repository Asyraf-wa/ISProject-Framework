<?php

namespace IsProject\Framework\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Turns the menu definition in config('isproject.menu') into a normalised tree
 * the sidebar can render: resolved URLs, active state and permission filtering.
 *
 * An entry whose route does not exist yet is dropped rather than throwing, so a
 * menu can name a module before the student has generated it.
 *
 * The definition comes from MenuManager, which reads the menu items table when
 * one has been curated and config('isproject.menu') when it has not. This class
 * does not know or care which: the two sources share one shape.
 */
class Menu
{
    public function __construct(private ?MenuManager $manager = null) {}

    /**
     * @param  array<int, array<string, mixed>>|null  $definition
     * @return array<int, array<string, mixed>>
     */
    public function items(?array $definition = null): array
    {
        $definition ??= ($this->manager ?? app(MenuManager::class))->definition();

        $items = [];

        foreach ($definition as $entry) {
            if ($item = $this->normalise((array) $entry)) {
                $items[] = $item;
            }
        }

        return $this->dropEmptyHeadings($items);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function normalise(array $entry): ?array
    {
        if (isset($entry['heading'])) {
            return ['type' => 'heading', 'label' => $entry['heading']];
        }

        if (! $this->permitted($entry)) {
            return null;
        }

        $children = [];

        foreach ($entry['children'] ?? [] as $child) {
            if ($item = $this->normalise((array) $child)) {
                $children[] = $item;
            }
        }

        $url = $this->url($entry);

        // Nothing to link to and nothing underneath: not worth a row.
        if ($url === null && $children === []) {
            return null;
        }

        $active = $this->active($entry, $url)
            || (bool) array_filter($children, fn (array $child) => $child['active'] ?? false);

        return [
            'type' => 'link',
            'label' => $entry['label'] ?? '',
            'icon' => $entry['icon'] ?? 'circle',
            'url' => $url,
            'active' => $active,
            'children' => $children,
            'badge' => $entry['badge'] ?? null,
            'target' => ($entry['target'] ?? null) === '_blank' ? '_blank' : null,
        ];
    }

    /**
     * Gate check. Accepts 'can' => 'ability' or 'can' => ['ability', Model::class].
     *
     * @param  array<string, mixed>  $entry
     */
    private function permitted(array $entry): bool
    {
        if (! isset($entry['can'])) {
            return true;
        }

        $can = (array) $entry['can'];
        $ability = array_shift($can);

        return Gate::allows($ability, $can === [] ? [] : $can);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function url(array $entry): ?string
    {
        if (! empty($entry['route'])) {
            return Route::has($entry['route'])
                ? route($entry['route'], $entry['parameters'] ?? [])
                : null;
        }

        return isset($entry['url']) ? url($entry['url']) : null;
    }

    /**
     * Active when the current request matches one of the entry's patterns, or
     * — with no patterns given — the entry's own path and anything beneath it.
     *
     * @param  array<string, mixed>  $entry
     */
    private function active(array $entry, ?string $url): bool
    {
        if (isset($entry['active'])) {
            return request()->is((array) $entry['active']);
        }

        if ($url === null) {
            return false;
        }

        // A link that leaves the site is never "where you are", and matching on
        // its path would light up the sidebar for an unrelated host that
        // happens to share a path with one of ours.
        if (($entry['target'] ?? null) === '_blank') {
            return false;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path === ''
            ? request()->is('/')
            : request()->is($path, $path.'/*');
    }

    /**
     * A heading with no items after it — because they were all filtered out by
     * permissions or missing routes — would render as a stray label.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function dropEmptyHeadings(array $items): array
    {
        $kept = [];

        foreach ($items as $index => $item) {
            if ($item['type'] !== 'heading') {
                $kept[] = $item;

                continue;
            }

            $next = $items[$index + 1] ?? null;

            if ($next !== null && $next['type'] !== 'heading') {
                $kept[] = $item;
            }
        }

        return $kept;
    }
}
