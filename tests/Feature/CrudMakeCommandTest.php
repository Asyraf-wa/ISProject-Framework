<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CrudMakeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    #[Test]
    public function it_fails_when_the_table_does_not_exist(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Ghost'])
            ->assertFailed();
    }

    #[Test]
    public function it_generates_the_whole_stack(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product'])->assertSuccessful();

        foreach ([
            'Models/Product.php',
            'Controllers/ProductController.php',
            'Requests/StoreProductRequest.php',
            'Requests/UpdateProductRequest.php',
            'Policies/ProductPolicy.php',
            'Factories/ProductFactory.php',
        ] as $file) {
            $this->assertValidPhp($this->generated($file));
        }

        foreach (['index', 'create', 'edit', 'show', '_form', '_guide'] as $view) {
            $this->assertFileExists($this->generated("views/products/{$view}.blade.php"));
        }
    }

    #[Test]
    public function the_form_screens_are_full_width_and_include_the_guidance_panel(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'views'])->assertSuccessful();

        foreach (['create', 'edit'] as $screen) {
            $view = file_get_contents($this->generated("views/products/{$screen}.blade.php"));

            $this->assertStringContainsString("@include('products._guide')", $view);
            $this->assertStringContainsString('col-12 col-xl-8', $view);
            $this->assertStringContainsString('col-12 col-xl-4', $view);

            // The old layout centred the form in a narrow column; it now spans
            // the content area with the guidance beside it.
            $this->assertStringNotContainsString('justify-content-center', $view);
        }
    }

    #[Test]
    public function the_guidance_panel_explains_the_columns_constraints(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'views'])->assertSuccessful();

        $guide = file_get_contents($this->generated('views/products/_guide.blade.php'));

        $this->assertStringContainsString('<li>Sku</li>', $guide);
        $this->assertStringContainsString('<li>Price</li>', $guide);

        // Nullable columns are not required, so they are not listed as such.
        $this->assertStringNotContainsString('<li>Description</li>', $guide);

        // A NOT NULL boolean posts through its hidden input whatever the user
        // does, so it carries no asterisk and must not be listed either.
        $this->assertStringNotContainsString('<li>Is Featured</li>', $guide);

        $this->assertStringContainsString('Must not already be used by another record.', $guide);
        $this->assertStringContainsString('two decimal places', $guide);
        $this->assertStringContainsString('Pick an existing category', $guide);
        $this->assertStringContainsString('Whole numbers only', $guide);
    }

    #[Test]
    public function every_required_field_is_marked_in_the_form_and_listed_in_the_guide(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'views'])->assertSuccessful();

        $form = file_get_contents($this->generated('views/products/_form.blade.php'));
        $guide = file_get_contents($this->generated('views/products/_guide.blade.php'));

        // The panel tells developers to look for the asterisk, so the two counts
        // have to agree — a chip with no matching asterisk is a lie.
        $this->assertSame(
            substr_count($form, 'is-required-mark'),
            substr_count($guide, '<li>'),
            'the guidance panel must list exactly the fields the form asterisks'
        );

        $this->assertSame(5, substr_count($guide, '<li>'));
    }

    #[Test]
    public function it_leaves_no_placeholders_behind(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product'])->assertSuccessful();

        foreach (glob(base_path($this->sandbox.'/**/*.*')) as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/%%[a-zA-Z]+%%/',
                file_get_contents($file),
                basename($file).' still contains an unsubstituted placeholder'
            );
        }
    }

    #[Test]
    public function the_model_reflects_the_schema(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'model'])->assertSuccessful();

        $model = file_get_contents($this->generated('Models/Product.php'));

        $this->assertStringContainsString("protected \$table = 'products';", $model);
        $this->assertStringContainsString('use HasFactory, SoftDeletes, Auditable;', $model);
        $this->assertStringContainsString("'price' => 'decimal:2',", $model);
        $this->assertStringContainsString("'is_featured' => 'boolean',", $model);
        $this->assertStringContainsString('public function category(): BelongsTo', $model);

        // Timestamps and the primary key must never be mass assignable.
        $this->assertStringNotContainsString("'created_at',", $model);
        $this->assertStringNotContainsString("'id',", $model);
    }

    #[Test]
    public function generated_models_audit_themselves_unless_config_says_otherwise(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'model'])->assertSuccessful();

        $model = file_get_contents($this->generated('Models/Product.php'));

        $this->assertStringContainsString('use IsProject\Framework\Concerns\Auditable;', $model);

        config()->set('isproject.audit.generated_models', false);

        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'model', '--force' => true])
            ->assertSuccessful();

        $model = file_get_contents($this->generated('Models/Product.php'));

        $this->assertStringNotContainsString('Auditable', $model);
        $this->assertStringContainsString('use HasFactory, SoftDeletes;', $model);
    }

    #[Test]
    public function validation_rules_come_from_the_columns(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'requests'])->assertSuccessful();

        $store = file_get_contents($this->generated('Requests/StoreProductRequest.php'));
        $update = file_get_contents($this->generated('Requests/UpdateProductRequest.php'));

        $this->assertStringContainsString("'unique:products,sku'", $store);
        $this->assertStringContainsString("'exists:categories,id'", $store);
        $this->assertStringContainsString("'description' => ['nullable', 'string']", $store);
        $this->assertStringContainsString("'name' => ['required', 'string']", $store);

        // Editing a record must not collide with itself.
        $this->assertStringContainsString(
            "Rule::unique('products', 'sku')->ignore(\$this->route('product'))",
            $update
        );
    }

    #[Test]
    public function it_derives_the_route_wildcard_from_the_uri_not_the_model(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->timestamps();
        });

        $this->artisan('isproject:crud', ['model' => 'PurchaseOrder', '--only' => 'requests'])
            ->assertSuccessful();

        $update = file_get_contents($this->generated('Requests/UpdatePurchaseOrderRequest.php'));

        // Route::resource('purchase-orders') binds {purchase_order}.
        $this->assertStringContainsString("\$this->route('purchase_order')", $update);
        $this->assertStringNotContainsString("\$this->route('purchaseOrder')", $update);
    }

    #[Test]
    public function it_skips_existing_files_unless_forced(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'model'])->assertSuccessful();

        file_put_contents($this->generated('Models/Product.php'), '<?php // edited by hand');

        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'model'])->assertSuccessful();
        $this->assertStringContainsString('edited by hand', file_get_contents($this->generated('Models/Product.php')));

        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'model', '--force' => true])
            ->assertSuccessful();
        $this->assertStringNotContainsString('edited by hand', file_get_contents($this->generated('Models/Product.php')));
    }

    #[Test]
    public function it_appends_each_resource_route_exactly_once(): void
    {
        $routes = $this->sandbox.'/web.php';

        File::ensureDirectoryExists(base_path($this->sandbox));
        File::put(base_path($routes), "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");

        config()->set('isproject.routes_file', $routes);

        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'routes'])->assertSuccessful();
        $this->artisan('isproject:crud', ['model' => 'Category', '--only' => 'routes'])->assertSuccessful();
        // Re-running must not duplicate a route that is already registered.
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'routes'])->assertSuccessful();

        $contents = File::get(base_path($routes));

        $this->assertSame(1, substr_count($contents, "Route::resource('products'"));
        $this->assertSame(1, substr_count($contents, "Route::resource('categories'"));
        $this->assertStringContainsString("->middleware(['auth'])", $contents);

        // Exactly one blank line between statements, and a trailing newline.
        $this->assertStringNotContainsString("\n\n\n", $contents);
        $this->assertStringEndsWith(";\n", $contents);
    }

    #[Test]
    public function the_report_route_is_always_registered_above_the_resource(): void
    {
        $routes = $this->sandbox.'/web.php';

        File::ensureDirectoryExists(base_path($this->sandbox));
        File::put(base_path($routes), "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");

        config()->set('isproject.routes_file', $routes);

        // Resource first, mimicking a module generated before reports existed.
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'routes', '--except' => 'report'])
            ->assertSuccessful();

        $this->assertStringNotContainsString('products.report', File::get(base_path($routes)));

        // Adding the report later must splice it in above, not append below.
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'routes,report'])
            ->assertSuccessful();

        $contents = File::get(base_path($routes));

        $report = strpos($contents, "'products.report'");
        $resource = strpos($contents, "Route::resource('products'");

        $this->assertNotFalse($report, 'the report route was never written');
        $this->assertNotFalse($resource);
        $this->assertLessThan(
            $resource,
            $report,
            'products/{product} would capture "report" as an id if it were registered first'
        );

        // And still exactly once each.
        $this->assertSame(1, substr_count($contents, "'products.report'"));
        $this->assertSame(1, substr_count($contents, "Route::resource('products'"));
    }

    #[Test]
    public function it_generates_a_report_screen_with_filters_and_csv_export(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product', '--only' => 'controller,report'])
            ->assertSuccessful();

        $this->assertValidPhp($this->generated('Controllers/ProductController.php'));
        $this->assertFileExists($this->generated('views/products/report.blade.php'));

        $controller = file_get_contents($this->generated('Controllers/ProductController.php'));

        $this->assertStringContainsString('public function report(', $controller);
        $this->assertStringContainsString('exportCsv', $controller);
        // created_at exists, so the report gets a date range.
        $this->assertStringContainsString("whereDate('created_at', '>=', \$from)", $controller);
        // category_id is a foreign key, so it becomes a dropdown filter.
        $this->assertStringContainsString("\$request->filled('category_id')", $controller);
        // price is numeric, so it becomes a summary total.
        $this->assertStringContainsString("sum('price')", $controller);

        $report = file_get_contents($this->generated('views/products/report.blade.php'));

        $this->assertStringContainsString("'export' => 'csv'", $report);
        $this->assertStringContainsString('@media print', $report);
    }

    #[Test]
    public function crud_all_covers_every_table_but_the_ignored_ones(): void
    {
        $this->artisan('isproject:crud-all', ['--only' => 'model'])->assertSuccessful();

        $this->assertFileExists($this->generated('Models/Product.php'));
        $this->assertFileExists($this->generated('Models/Category.php'));

        // migrations is in config('isproject.ignored_tables').
        $this->assertFileDoesNotExist($this->generated('Models/Migration.php'));
    }
}
