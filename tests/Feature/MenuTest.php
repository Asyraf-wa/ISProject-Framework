<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use IsProject\Framework\Support\Menu;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MenuTest extends TestCase
{
    private function menu(array $definition): array
    {
        return (new Menu)->items($definition);
    }

    /**
     * Register a named route the way the application would.
     *
     * Laravel builds its route-name lookup once, when routes load at boot, so a
     * route declared mid-test stays invisible to Route::has() until the lookup
     * is refreshed. Doing that here keeps these tests honest about real
     * behaviour rather than making Menu defensive about a test-only quirk.
     */
    private function defineRoute(string $uri, string $name): void
    {
        Route::get($uri, fn () => '')->name($name);
        Route::getRoutes()->refreshNameLookups();
    }

    #[Test]
    public function it_resolves_named_routes(): void
    {
        $this->defineRoute('/products', 'products.index');

        $items = $this->menu([
            ['label' => 'Products', 'icon' => 'box', 'route' => 'products.index'],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('Products', $items[0]['label']);
        $this->assertSame('box', $items[0]['icon']);
        $this->assertStringEndsWith('/products', $items[0]['url']);
    }

    #[Test]
    public function it_skips_entries_whose_route_does_not_exist_yet(): void
    {
        // A menu may name a module before the developer has generated it.
        $this->assertSame([], $this->menu([
            ['label' => 'Invoices', 'route' => 'invoices.index'],
        ]));
    }

    #[Test]
    public function it_marks_the_current_page_active(): void
    {
        $this->defineRoute('/products', 'products.index');
        $this->defineRoute('/categories', 'categories.index');

        $this->get('/products');

        $items = $this->menu([
            ['label' => 'Products', 'route' => 'products.index'],
            ['label' => 'Categories', 'route' => 'categories.index'],
        ]);

        $this->assertTrue($items[0]['active']);
        $this->assertFalse($items[1]['active']);
    }

    #[Test]
    public function a_nested_url_keeps_its_parent_active(): void
    {
        $this->defineRoute('/products', 'products.index');
        $this->defineRoute('/products/create', 'products.create');

        $this->get('/products/create');

        $items = $this->menu([
            ['label' => 'Products', 'route' => 'products.index'],
        ]);

        $this->assertTrue($items[0]['active'], 'products/* should keep the parent highlighted');
    }

    #[Test]
    public function the_dashboard_is_not_active_on_every_page(): void
    {
        $this->defineRoute('/reports', 'reports.index');

        $this->get('/reports');

        $items = $this->menu([
            ['label' => 'Dashboard', 'url' => '/', 'active' => '/'],
            ['label' => 'Reports', 'route' => 'reports.index'],
        ]);

        $this->assertFalse($items[0]['active']);
        $this->assertTrue($items[1]['active']);
    }

    #[Test]
    public function it_filters_entries_by_gate(): void
    {
        $this->defineRoute('/admin', 'admin.index');
        $this->defineRoute('/products', 'products.index');

        Gate::define('manage-admin', fn ($user = null) => false);

        $items = $this->menu([
            ['label' => 'Admin', 'route' => 'admin.index', 'can' => 'manage-admin'],
            ['label' => 'Products', 'route' => 'products.index'],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('Products', $items[0]['label']);
    }

    #[Test]
    public function a_parent_survives_on_its_children_alone(): void
    {
        $this->defineRoute('/reports/monthly', 'reports.monthly');

        $items = $this->menu([
            ['label' => 'Reports', 'icon' => 'list', 'children' => [
                ['label' => 'Monthly', 'route' => 'reports.monthly'],
                ['label' => 'Missing', 'route' => 'reports.nope'],
            ]],
        ]);

        $this->assertCount(1, $items);
        $this->assertNull($items[0]['url']);
        $this->assertCount(1, $items[0]['children']);
        $this->assertSame('Monthly', $items[0]['children'][0]['label']);
    }

    #[Test]
    public function an_active_child_opens_its_parent(): void
    {
        $this->defineRoute('/reports/monthly', 'reports.monthly');

        $this->get('/reports/monthly');

        $items = $this->menu([
            ['label' => 'Reports', 'children' => [
                ['label' => 'Monthly', 'route' => 'reports.monthly'],
            ]],
        ]);

        $this->assertTrue($items[0]['active']);
    }

    #[Test]
    public function it_drops_headings_left_with_nothing_under_them(): void
    {
        $this->defineRoute('/products', 'products.index');

        $items = $this->menu([
            ['heading' => 'Manage'],
            ['label' => 'Products', 'route' => 'products.index'],
            ['heading' => 'Reporting'],           // everything below was filtered out
            ['label' => 'Invoices', 'route' => 'invoices.index'],
        ]);

        $this->assertSame(['Manage', 'Products'], array_column($items, 'label'));
    }
}
