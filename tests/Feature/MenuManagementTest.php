<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Models\MenuItem;
use IsProject\Framework\Support\Menu;
use IsProject\Framework\Support\MenuManager;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', MenuTestUser::class);

        $app['config']->set('isproject.menu', [
            ['heading' => 'Main'],
            ['label' => 'Dashboard', 'icon' => 'home', 'url' => '/'],
            ['heading' => 'Manage'],
            ['label' => 'Books', 'icon' => 'box', 'route' => 'books.index'],
        ]);
    }

    /** Something for the menu entries to point at. */
    protected function defineRoutes($router): void
    {
        $router->get('books', fn () => 'books')->name('books.index');
        $router->get('tasks', fn () => 'tasks')->name('tasks.index');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('menu_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        MenuManager::flush();
    }

    // ------------------------------------------------------ which source wins

    #[Test]
    public function an_empty_table_means_the_menu_still_comes_from_config(): void
    {
        $this->assertSame(['Main', 'Dashboard', 'Manage', 'Books'], $this->rendered());
        $this->assertFalse(app(MenuManager::class)->isManaged());
    }

    #[Test]
    public function one_row_is_enough_for_the_table_to_take_over(): void
    {
        MenuItem::query()->create([
            'type' => MenuItem::TYPE_INTERNAL,
            'label' => 'Only this',
            'url' => '/only',
            'position' => 0,
        ]);

        // Deliberately blunt: a half-managed menu, where a config entry
        // reappears among curated rows, would be worse than either source alone.
        $this->assertSame(['Only this'], $this->rendered());
    }

    #[Test]
    public function deleting_every_row_hands_the_menu_back_to_config(): void
    {
        app(MenuManager::class)->importFromConfig();
        MenuItem::query()->delete();
        MenuManager::flush();

        $this->assertSame(['Main', 'Dashboard', 'Manage', 'Books'], $this->rendered());
    }

    // ------------------------------------------------------------- importing

    #[Test]
    public function importing_copies_config_without_changing_what_renders(): void
    {
        $before = app(Menu::class)->items();

        app(MenuManager::class)->importFromConfig();
        MenuManager::flush();

        $after = app(Menu::class)->items();

        // The whole promise of the import button: it is a copy, not an edit.
        $this->assertSame($before, $after);
    }

    #[Test]
    public function importing_keeps_the_headings_and_the_nesting(): void
    {
        app(MenuManager::class)->importFromConfig();

        $this->assertSame(
            ['Main', 'Dashboard', 'Manage', 'Books'],
            MenuItem::query()->orderBy('position')->pluck('label')->take(4)->all(),
        );

        $this->assertSame(
            MenuItem::TYPE_HEADING,
            MenuItem::query()->where('label', 'Main')->value('type'),
        );
    }

    #[Test]
    public function importing_twice_does_not_duplicate_the_menu(): void
    {
        $manager = app(MenuManager::class);

        $first = $manager->importFromConfig();
        $second = $manager->importFromConfig();

        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $second);
    }

    #[Test]
    public function importing_adds_the_menu_screen_when_config_has_no_entry_for_it(): void
    {
        app(MenuManager::class)->importFromConfig();

        // Otherwise the sidebar becomes database-driven with no way back to the
        // screen that edits it.
        $this->assertTrue(
            MenuItem::query()->where('route_name', 'isproject.menu.index')->exists(),
        );
    }

    // ------------------------------------------------------------- rendering

    #[Test]
    public function an_entry_whose_route_is_missing_is_skipped_rather_than_fatal(): void
    {
        MenuItem::query()->create([
            'type' => MenuItem::TYPE_ROUTE,
            'label' => 'Gone',
            'route_name' => 'nothing.here',
            'position' => 0,
        ]);

        $this->assertSame([], app(Menu::class)->items());
    }

    #[Test]
    public function a_hidden_row_does_not_render(): void
    {
        MenuItem::query()->create([
            'type' => MenuItem::TYPE_INTERNAL, 'label' => 'Shown', 'url' => '/a', 'position' => 0,
        ]);
        MenuItem::query()->create([
            'type' => MenuItem::TYPE_INTERNAL, 'label' => 'Hidden', 'url' => '/b',
            'position' => 1, 'is_active' => false,
        ]);

        $this->assertSame(['Shown'], $this->rendered());
    }

    #[Test]
    public function an_external_row_opens_in_a_new_tab_and_is_never_active(): void
    {
        MenuItem::query()->create([
            'type' => MenuItem::TYPE_EXTERNAL,
            'label' => 'Handbook',
            'url' => 'https://example.test/books',
            'position' => 0,
        ]);

        // Stand on /books — the path half of that external address. Matching on
        // the path alone would light the row up for somebody else's site.
        $this->get('/books');

        $item = app(Menu::class)->items()[0];

        $this->assertSame('_blank', $item['target']);
        $this->assertFalse($item['active']);
        $this->assertSame('https://example.test/books', $item['url']);
    }

    #[Test]
    public function children_render_underneath_their_parent(): void
    {
        $parent = MenuItem::query()->create([
            'type' => MenuItem::TYPE_ROUTE, 'label' => 'Books', 'route_name' => 'books.index', 'position' => 0,
        ]);
        MenuItem::query()->create([
            'type' => MenuItem::TYPE_ROUTE, 'label' => 'Tasks', 'route_name' => 'tasks.index',
            'parent_id' => $parent->id, 'position' => 0,
        ]);

        $items = app(Menu::class)->items();

        $this->assertCount(1, $items);
        $this->assertSame('Tasks', $items[0]['children'][0]['label']);
    }

    // ------------------------------------------------------------ reordering

    #[Test]
    public function reordering_writes_positions_from_the_order_it_is_given(): void
    {
        [$a, $b, $c] = $this->threeRows();

        app(MenuManager::class)->reorder([
            ['id' => $c->id, 'parent' => null],
            ['id' => $a->id, 'parent' => null],
            ['id' => $b->id, 'parent' => null],
        ]);

        $this->assertSame(
            ['C', 'A', 'B'],
            MenuItem::query()->orderBy('position')->pluck('label')->all(),
        );
    }

    #[Test]
    public function reordering_can_nest_a_row(): void
    {
        [$a, $b] = $this->threeRows();

        app(MenuManager::class)->reorder([
            ['id' => $a->id, 'parent' => null],
            ['id' => $b->id, 'parent' => $a->id],
        ]);

        $this->assertSame($a->id, $b->fresh()->parent_id);
    }

    #[Test]
    public function the_menu_refuses_to_go_three_levels_deep(): void
    {
        [$a, $b, $c] = $this->threeRows();

        $this->expectException(\InvalidArgumentException::class);

        app(MenuManager::class)->reorder([
            ['id' => $a->id, 'parent' => null],
            ['id' => $b->id, 'parent' => $a->id],
            ['id' => $c->id, 'parent' => $b->id],
        ]);
    }

    #[Test]
    public function a_row_cannot_be_nested_under_itself(): void
    {
        [$a] = $this->threeRows();

        $this->expectException(\InvalidArgumentException::class);

        app(MenuManager::class)->reorder([['id' => $a->id, 'parent' => $a->id]]);
    }

    #[Test]
    public function a_heading_cannot_hold_items(): void
    {
        $heading = MenuItem::query()->create([
            'type' => MenuItem::TYPE_HEADING, 'label' => 'Group', 'position' => 0,
        ]);
        $child = MenuItem::query()->create([
            'type' => MenuItem::TYPE_INTERNAL, 'label' => 'Under', 'url' => '/u', 'position' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(MenuManager::class)->reorder([
            ['id' => $heading->id, 'parent' => null],
            ['id' => $child->id, 'parent' => $heading->id],
        ]);
    }

    #[Test]
    public function a_rejected_order_changes_nothing(): void
    {
        [$a, $b, $c] = $this->threeRows();

        try {
            app(MenuManager::class)->reorder([
                ['id' => $c->id, 'parent' => null],
                ['id' => $b->id, 'parent' => $c->id],
                ['id' => $a->id, 'parent' => $b->id],
            ]);
        } catch (\InvalidArgumentException) {
            // Expected — the assertion is that nothing moved.
        }

        $this->assertSame(
            ['A', 'B', 'C'],
            MenuItem::query()->orderBy('position')->pluck('label')->all(),
        );
    }

    #[Test]
    public function an_unknown_id_in_the_payload_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(MenuManager::class)->reorder([['id' => 9999, 'parent' => null]]);
    }

    // -------------------------------------------------------------- the screen

    #[Test]
    public function the_screen_offers_the_modules_that_are_missing(): void
    {
        app(MenuManager::class)->importFromConfig();

        $missing = collect(app(MenuManager::class)->unlisted())->pluck('route');

        // Books is in the menu; tasks is the one nobody linked to.
        $this->assertContains('tasks.index', $missing);
        $this->assertNotContains('books.index', $missing);
    }

    #[Test]
    public function adopting_a_module_adds_it_with_its_permission(): void
    {
        $this->actingAs($this->user())
            ->post('/menu/adopt', ['route' => 'tasks.index', 'label' => 'Tasks'])
            ->assertSessionHas('success');

        $item = MenuItem::query()->where('route_name', 'tasks.index')->firstOrFail();

        $this->assertSame('tasks.index', $item->permission);
        $this->assertTrue($item->is_active);
    }

    #[Test]
    public function adopting_imports_config_first_so_nothing_is_lost(): void
    {
        $this->actingAs($this->user())
            ->post('/menu/adopt', ['route' => 'tasks.index', 'label' => 'Tasks']);

        // Without the import the table would hold one row, and that row alone
        // would become the whole menu.
        $this->assertTrue(MenuItem::query()->where('label', 'Dashboard')->exists());
    }

    #[Test]
    public function a_javascript_url_is_refused(): void
    {
        $this->actingAs($this->user())
            ->post('/menu', [
                'type' => MenuItem::TYPE_EXTERNAL,
                'label' => 'Bad',
                'url' => 'javascript:alert(document.cookie)',
            ])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, MenuItem::query()->count());
    }

    #[Test]
    public function a_path_without_a_leading_slash_is_refused(): void
    {
        $this->actingAs($this->user())
            ->post('/menu', ['type' => MenuItem::TYPE_INTERNAL, 'label' => 'Bad', 'url' => 'reports'])
            ->assertSessionHasErrors('url');
    }

    #[Test]
    public function a_route_that_does_not_exist_is_refused(): void
    {
        $this->actingAs($this->user())
            ->post('/menu', ['type' => MenuItem::TYPE_ROUTE, 'label' => 'Bad', 'route_name' => 'nope.index'])
            ->assertSessionHasErrors('route_name');
    }

    #[Test]
    public function an_icon_outside_the_bundled_set_is_refused(): void
    {
        $this->actingAs($this->user())
            ->post('/menu', [
                'type' => MenuItem::TYPE_INTERNAL, 'label' => 'Bad', 'url' => '/x', 'icon' => 'skull',
            ])
            ->assertSessionHasErrors('icon');
    }

    #[Test]
    public function switching_type_clears_the_other_types_fields(): void
    {
        $item = MenuItem::query()->create([
            'type' => MenuItem::TYPE_EXTERNAL, 'label' => 'Was external',
            'url' => 'https://example.test', 'position' => 0,
        ]);

        $this->actingAs($this->user())->put("/menu/{$item->id}", [
            'type' => MenuItem::TYPE_ROUTE,
            'label' => 'Now a route',
            'route_name' => 'books.index',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $item->refresh();

        // A leftover url would come back the moment somebody switched the type
        // again, which reads as the form remembering something it should not.
        $this->assertNull($item->url);
        $this->assertSame('books.index', $item->route_name);
    }

    #[Test]
    public function deleting_a_parent_takes_its_children_with_it(): void
    {
        $parent = MenuItem::query()->create([
            'type' => MenuItem::TYPE_ROUTE, 'label' => 'Books', 'route_name' => 'books.index', 'position' => 0,
        ]);
        MenuItem::query()->create([
            'type' => MenuItem::TYPE_ROUTE, 'label' => 'Tasks', 'route_name' => 'tasks.index',
            'parent_id' => $parent->id, 'position' => 0,
        ]);

        $this->actingAs($this->user())->delete("/menu/{$parent->id}")->assertSessionHas('success');

        $this->assertSame(0, MenuItem::query()->count());
    }

    #[Test]
    public function the_arrows_move_a_row_past_its_neighbour(): void
    {
        [$a, $b] = $this->threeRows();

        $this->actingAs($this->user())->post("/menu/{$b->id}/move", ['direction' => 'up']);

        $this->assertSame(
            ['B', 'A', 'C'],
            MenuItem::query()->orderBy('position')->pluck('label')->all(),
        );
    }

    #[Test]
    public function moving_past_the_end_does_nothing(): void
    {
        [$a] = $this->threeRows();

        $this->actingAs($this->user())->post("/menu/{$a->id}/move", ['direction' => 'up']);

        $this->assertSame(
            ['A', 'B', 'C'],
            MenuItem::query()->orderBy('position')->pluck('label')->all(),
        );
    }

    #[Test]
    public function rows_created_in_the_same_second_still_reorder_correctly(): void
    {
        // Every position is 0 — the state a bulk insert leaves behind. Swapping
        // two values would leave the duplicates, and nothing would move.
        foreach (['A', 'B', 'C'] as $label) {
            MenuItem::query()->create([
                'type' => MenuItem::TYPE_INTERNAL, 'label' => $label,
                'url' => '/'.strtolower($label), 'position' => 0,
            ]);
        }

        $last = MenuItem::query()->where('label', 'C')->firstOrFail();

        $this->actingAs($this->user())->post("/menu/{$last->id}/move", ['direction' => 'up']);

        $this->assertSame(
            ['A', 'C', 'B'],
            MenuItem::query()->orderBy('position')->pluck('label')->all(),
        );
    }

    #[Test]
    public function resetting_empties_the_table(): void
    {
        app(MenuManager::class)->importFromConfig();

        $this->actingAs($this->user())->delete('/menu/reset')->assertSessionHas('success');

        $this->assertSame(0, MenuItem::query()->count());
    }

    #[Test]
    public function the_reorder_endpoint_reports_a_refusal_rather_than_failing_silently(): void
    {
        [$a, $b, $c] = $this->threeRows();

        $this->actingAs($this->user())
            ->postJson('/menu/reorder', [
                'order' => [
                    ['id' => $a->id, 'parent' => null],
                    ['id' => $b->id, 'parent' => $a->id],
                    ['id' => $c->id, 'parent' => $b->id],
                ],
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'The menu can only go two levels deep.']);
    }

    #[Test]
    public function the_screen_renders(): void
    {
        $user = $this->user();

        // Empty: the offer to take the menu over, not a tree.
        $this->actingAs($user)->get('/menu')->assertOk()->assertSee('Manage the menu here');

        app(MenuManager::class)->importFromConfig();

        $this->actingAs($user)->get('/menu')->assertOk()->assertSee('Sidebar order');
        $this->actingAs($user)->get('/menu/create')->assertOk()->assertSee('Label');
    }

    // --------------------------------------------------------------- helpers

    /**
     * Every label the sidebar would render, headings included, in order.
     *
     * @return array<int, string>
     */
    private function rendered(): array
    {
        return collect(app(Menu::class)->items())->pluck('label')->all();
    }

    /** @return array<int, MenuItem> */
    private function threeRows(): array
    {
        $rows = [];

        foreach (['A', 'B', 'C'] as $position => $label) {
            $rows[] = MenuItem::query()->create([
                'type' => MenuItem::TYPE_INTERNAL,
                'label' => $label,
                'url' => '/'.strtolower($label),
                'position' => $position,
            ]);
        }

        return $rows;
    }

    private function user(): MenuTestUser
    {
        return MenuTestUser::query()->create([
            'name' => 'Lecturer',
            'email' => 'lecturer@example.test',
            'password' => 'hashed',
        ]);
    }
}

/** A User model that exists only for these tests. */
class MenuTestUser extends Authenticatable
{
    protected $table = 'menu_users';

    protected $guarded = [];
}
