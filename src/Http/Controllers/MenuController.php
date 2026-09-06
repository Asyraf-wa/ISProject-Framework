<?php

namespace IsProject\Framework\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use IsProject\Framework\Models\MenuItem;
use IsProject\Framework\Support\Icons;
use IsProject\Framework\Support\MenuManager;

/**
 * Managing the sidebar from the browser.
 *
 * Everything here writes rows; nothing writes code. The sidebar reads whatever
 * is in the table, so a change is visible on the next page load with no build
 * step and no file to edit.
 */
class MenuController extends Controller
{
    public function __construct(private MenuManager $menus) {}

    public function index(): View
    {
        return view('isproject::menu.index', [
            'items' => $this->menus->all(),
            'managed' => $this->menus->isManaged(),
            'unlisted' => $this->menus->unlisted(),
            'configCount' => count((array) config('isproject.menu', [])),
        ]);
    }

    public function create(Request $request): View
    {
        $item = new MenuItem([
            'type' => (string) $request->query('type', MenuItem::TYPE_ROUTE),
            'route_name' => (string) $request->query('route', ''),
            'label' => (string) $request->query('label', ''),
            'is_active' => true,
        ]);

        return view('isproject::menu.form', $this->formData($item));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, null);

        $validated['position'] = $this->menus->nextPosition($validated['parent_id'] ?? null);

        $item = MenuItem::query()->create($validated);

        return redirect()
            ->route('isproject.menu.index')
            ->with('success', "Added [{$item->label}] to the menu.");
    }

    public function edit(MenuItem $item): View
    {
        return view('isproject::menu.form', $this->formData($item));
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $item->update($this->validated($request, $item));

        return redirect()
            ->route('isproject.menu.index')
            ->with('success', "Saved [{$item->label}].");
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        $label = $item->label;
        $children = $item->children()->count();

        $item->delete();

        $note = $children > 0
            ? " Its {$children} sub-".Str::plural('item', $children).' went with it.'
            : '';

        return back()->with('success', "Removed [{$label}] from the menu.".$note);
    }

    /** Switch a row on or off without losing it. */
    public function toggle(MenuItem $item): RedirectResponse
    {
        $item->update(['is_active' => ! $item->is_active]);

        return back()->with(
            'success',
            "[{$item->label}] is now ".($item->is_active ? 'shown' : 'hidden').'.',
        );
    }

    /**
     * Nudge one row past its neighbour.
     *
     * The drag-and-drop is the fast way; this is the one that works from a
     * keyboard, on a touch screen, and with JavaScript switched off. WCAG 2.5.7
     * asks for exactly this — a dragging movement must not be the only route to
     * an outcome.
     */
    public function move(Request $request, MenuItem $item): RedirectResponse
    {
        $direction = $request->input('direction') === 'up' ? 'up' : 'down';

        $siblings = MenuItem::query()
            ->where('parent_id', $item->parent_id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $index = $siblings->search(fn (MenuItem $row) => $row->is($item));
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $target < 0 || $target >= $siblings->count()) {
            return back();
        }

        // Rewrite every sibling's position from the reordered list: positions
        // can start out duplicated (two rows created in the same second), and
        // swapping two values would leave those duplicates in place.
        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $position => $row) {
                $row->update(['position' => $position]);
            }
        });

        return back()->with('success', "Moved [{$item->label}].");
    }

    /** Drag-and-drop lands here: a flat list of {id, parent} in display order. */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array', 'max:500'],
            'order.*.id' => ['required', 'integer'],
            'order.*.parent' => ['nullable', 'integer'],
        ]);

        try {
            $this->menus->reorder($validated['order']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Menu order saved.']);
    }

    /** Copy config('isproject.menu') into the table so there is something to edit. */
    public function import(): RedirectResponse
    {
        $written = $this->menus->importFromConfig();

        return back()->with(
            $written > 0 ? 'success' : 'error',
            $written > 0
                ? "Imported {$written} ".Str::plural('entry', $written).' from config. The menu is now managed here.'
                : 'Nothing was imported: the menu is already managed here.',
        );
    }

    /** Add a discovered module in one click. */
    public function adopt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'route' => ['required', 'string', 'max:191'],
            'label' => ['required', 'string', 'max:255'],
        ]);

        if (! Route::has($validated['route'])) {
            return back()->with('error', 'That route no longer exists.');
        }

        // Importing first means the click does not silently discard the config
        // menu by making this the only row in the table.
        $this->menus->importFromConfig();

        $item = MenuItem::query()->create([
            'type' => MenuItem::TYPE_ROUTE,
            'label' => $validated['label'],
            'icon' => 'list',
            'route_name' => $validated['route'],
            'permission' => $validated['route'],
            'is_active' => true,
            'position' => $this->menus->nextPosition(),
        ]);

        return back()->with('success', "Added [{$item->label}] to the menu. Drag it where you want it.");
    }

    /** Empty the table, handing the sidebar back to config. */
    public function reset(): RedirectResponse
    {
        MenuItem::query()->delete();
        MenuManager::flush();

        return back()->with('success', 'Menu emptied. The sidebar is reading config/isproject.php again.');
    }

    /** @return array<string, mixed> */
    private function formData(MenuItem $item): array
    {
        return [
            'item' => $item,
            'types' => MenuItem::TYPES,
            'icons' => Icons::names(),
            'routes' => $this->menus->linkableRoutes(),
            'parents' => $this->menus->possibleParents($item),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?MenuItem $item): array
    {
        $type = (string) $request->input('type');

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(MenuItem::TYPES))],
            'label' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', Rule::in(Icons::names())],

            // Rule::in over the live route list: a name that does not resolve
            // would render as a row that silently disappears, which reads as a
            // bug rather than as a typo.
            'route_name' => [
                Rule::requiredIf($type === MenuItem::TYPE_ROUTE),
                'nullable', 'string', Rule::in($this->menus->linkableRoutes()),
            ],

            'url' => [
                Rule::requiredIf(in_array($type, [MenuItem::TYPE_INTERNAL, MenuItem::TYPE_EXTERNAL], true)),
                'nullable', 'string', 'max:255',
                function (string $attribute, mixed $value, callable $fail) use ($type) {
                    if ($type === MenuItem::TYPE_EXTERNAL && ! Str::startsWith($value, ['http://', 'https://'])) {
                        $fail('An external link must start with http:// or https://.');
                    }

                    // Anything but a leading slash or an http(s) scheme —
                    // javascript:, data:, a bare host — is refused. A menu is a
                    // list of links somebody clicks without reading them.
                    if ($type === MenuItem::TYPE_INTERNAL && ! Str::startsWith($value, '/')) {
                        $fail('A path must start with / — for example /reports.');
                    }
                },
            ],

            'permission' => ['nullable', 'string', 'max:191'],
            'badge' => ['nullable', 'string', 'max:30'],
            'parent_id' => [
                'nullable',
                Rule::exists('isproject_menu_items', 'id')->whereNull('parent_id'),
            ],
            'opens_in_new_tab' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // A row that already holds children cannot become a child itself: the
        // tree stops at two levels.
        if (($validated['parent_id'] ?? null) && $item?->children()->exists()) {
            abort(422, 'That item has sub-items of its own, so it cannot be nested.');
        }

        if (($validated['parent_id'] ?? null) && $item?->is(MenuItem::query()->find($validated['parent_id']))) {
            abort(422, 'An item cannot be nested under itself.');
        }

        // Every read is ?? null: a nullable field that was not submitted at all
        // is absent from the validated array, not present and empty, and the
        // form is not the only thing that posts here.
        //
        // Fields belonging to the other types are nulled rather than left, or
        // they would reappear the moment somebody switched the type back.
        return [
            'type' => $validated['type'],
            'label' => $validated['label'],
            'icon' => $validated['type'] === MenuItem::TYPE_HEADING ? null : ($validated['icon'] ?? null),
            'route_name' => $validated['type'] === MenuItem::TYPE_ROUTE
                ? ($validated['route_name'] ?? null)
                : null,
            'url' => in_array($validated['type'], [MenuItem::TYPE_INTERNAL, MenuItem::TYPE_EXTERNAL], true)
                ? ($validated['url'] ?? null)
                : null,
            'permission' => ($validated['permission'] ?? null) ?: null,
            'badge' => ($validated['badge'] ?? null) ?: null,
            'parent_id' => $validated['type'] === MenuItem::TYPE_HEADING ? null : ($validated['parent_id'] ?? null),
            'opens_in_new_tab' => $request->boolean('opens_in_new_tab'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
