<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Concerns\ListsRecords;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('name');
            $table->decimal('price', 8, 2)->default(0);
        });

        $this->controller = new ListingController;
    }

    // -------------------------------------------------------------- sorting

    #[Test]
    public function it_sorts_by_a_whitelisted_column(): void
    {
        $sql = $this->sortedSql(['sort' => 'name', 'direction' => 'asc']);

        $this->assertStringContainsString('order by "products"."name" asc', $sql);
    }

    #[Test]
    public function the_direction_is_asc_or_desc_and_nothing_else(): void
    {
        $this->assertStringContainsString('asc', $this->sortedSql(['sort' => 'name', 'direction' => 'asc']));
        $this->assertStringContainsString('desc', $this->sortedSql(['sort' => 'name', 'direction' => 'desc']));

        // Anything else falls to desc rather than reaching the query.
        $sql = $this->sortedSql(['sort' => 'name', 'direction' => 'asc; drop table products--']);

        $this->assertStringContainsString('order by "products"."name" desc', $sql);
        $this->assertStringNotContainsString('drop table', $sql);
    }

    #[Test]
    public function an_unknown_sort_key_is_ignored(): void
    {
        // Falls back to the default ordering rather than erroring or, worse,
        // trusting the name.
        $sql = $this->sortedSql(['sort' => 'secret_column', 'direction' => 'asc']);

        $this->assertStringNotContainsString('secret_column', $sql);
        $this->assertStringContainsString('order by "id" desc', $sql);
    }

    #[Test]
    public function a_sort_key_can_never_reach_the_sql(): void
    {
        // An order-by clause is concatenated, not bound, so this is the check
        // that matters: the request may only pick a name the application wrote.
        foreach ([
            'name; drop table products--',
            'products.name, (select 1)',
            '(case when 1=1 then name end)',
            'name`',
        ] as $payload) {
            $sql = $this->sortedSql(['sort' => $payload, 'direction' => 'asc']);

            $this->assertStringNotContainsString('drop table', strtolower($sql));
            $this->assertStringNotContainsString('select 1', strtolower($sql));
            $this->assertStringContainsString('order by "id" desc', $sql, "[{$payload}] should have been ignored");
        }

        // And the table is still there afterwards.
        $this->assertTrue(Schema::hasTable('products'));
    }

    #[Test]
    public function a_foreign_key_sorts_by_the_label_the_screen_shows(): void
    {
        $sql = $this->sortedSql(['sort' => 'category', 'direction' => 'asc']);

        // Ordering "Category" by category_id would look arbitrary to anyone
        // reading the page, so it joins and orders by the name.
        $this->assertStringContainsString('left join "categories"', $sql);
        $this->assertStringContainsString('order by "categories"."name" asc', $sql);

        // The model's own columns must still win, or the join would overwrite
        // them as it merged into the attributes.
        $this->assertStringContainsString('select "products".*', $sql);
    }

    #[Test]
    public function sorting_by_a_relation_actually_orders_the_rows(): void
    {
        $zulu = Category::query()->create(['name' => 'Zulu']);
        $alpha = Category::query()->create(['name' => 'Alpha']);

        Product::query()->create(['category_id' => $zulu->id, 'name' => 'First added']);
        Product::query()->create(['category_id' => $alpha->id, 'name' => 'Second added']);

        $request = Request::create('/', 'GET', ['sort' => 'category', 'direction' => 'asc']);
        $query = Product::query();
        $this->controller->sort($query, $request);

        $this->assertSame(['Second added', 'First added'], $query->pluck('name')->all());
    }

    // ------------------------------------------------------------ page size

    #[Test]
    public function it_honours_an_offered_page_size(): void
    {
        config()->set('isproject.per_page_options', [15, 25, 50, 100]);

        $this->assertSame(25, $this->perPage(['per_page' => 25]));
        $this->assertSame(100, $this->perPage(['per_page' => '100']));
    }

    #[Test]
    public function a_size_that_was_not_offered_falls_back_to_the_default(): void
    {
        config()->set('isproject.per_page', 15);

        // Not clamped to the nearest option — an arbitrary number in the URL is
        // not a choice anyone made on the screen.
        $this->assertSame(15, $this->perPage(['per_page' => 99999]));
        $this->assertSame(15, $this->perPage(['per_page' => 'lots']));
        $this->assertSame(15, $this->perPage(['per_page' => -5]));
        $this->assertSame(15, $this->perPage([]));
    }

    #[Test]
    public function all_means_as_many_as_we_will_render(): void
    {
        config()->set('isproject.max_per_page', 200);

        // Unbounded, "All" is a hung browser on any real table.
        $this->assertSame(200, $this->perPage(['per_page' => 'all']));
    }

    #[Test]
    public function an_offered_size_above_the_ceiling_is_clamped(): void
    {
        config()->set('isproject.per_page_options', [15, 500]);
        config()->set('isproject.max_per_page', 200);

        $this->assertSame(200, $this->perPage(['per_page' => 500]));
    }

    // ------------------------------------------------------------ generator

    #[Test]
    public function generated_controllers_carry_a_sort_whitelist(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'controller'])->assertSuccessful();

        $code = file_get_contents($this->generated('Controllers/ProductController.php'));

        $this->assertValidPhp($this->generated('Controllers/ProductController.php'));
        $this->assertStringContainsString('use ListsRecords;', $code);
        $this->assertStringContainsString("'name' => 'products.name'", $code);

        // A foreign key sorts through a join on the related table.
        $this->assertStringContainsString(
            "'category' => ['categories', 'categories.id', 'products.category_id', 'categories.name']",
            $code
        );
    }

    #[Test]
    public function generated_search_columns_are_qualified(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'controller'])->assertSuccessful();

        $code = file_get_contents($this->generated('Controllers/ProductController.php'));

        // Unqualified, "name" is ambiguous the moment a sort joins categories,
        // which also has one — searching while sorted would be a SQL error.
        $this->assertStringContainsString("where('products.name', 'like'", $code);
        $this->assertStringNotContainsString("where('name', 'like'", $code);
    }

    #[Test]
    public function generated_headings_are_sort_links(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'views'])->assertSuccessful();

        $index = file_get_contents($this->generated('views/products/index.blade.php'));

        $this->assertStringContainsString('<x-isproject::sort-header column="name" label="Name" />', $index);
        $this->assertStringContainsString('<x-isproject::per-page', $index);

        // The foreign key heading sorts under the relation, matching its label.
        $this->assertStringContainsString('column="category" label="Category"', $index);
    }

    // -------------------------------------------------------------- helpers

    /** @param  array<string, mixed>  $query */
    private function sortedSql(array $query): string
    {
        $request = Request::create('/', 'GET', $query);
        $builder = Product::query();

        $this->controller->sort($builder, $request);

        return $builder->toSql();
    }

    /** @param  array<string, mixed>  $query */
    private function perPage(array $query): int
    {
        return $this->controller->size(Request::create('/', 'GET', $query));
    }
}

/** Stands in for a generated controller. */
class ListingController
{
    use ListsRecords;

    protected function sortable(): array
    {
        return [
            'name' => 'products.name',
            'price' => 'products.price',
            'category' => ['categories', 'categories.id', 'products.category_id', 'categories.name'],
        ];
    }

    /** The trait's methods are protected; these open them up for the test. */
    public function sort($query, Request $request)
    {
        return $this->applySort($query, $request, 'id');
    }

    public function size(Request $request): int
    {
        return $this->perPage($request);
    }
}

class Category extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

class Product extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
