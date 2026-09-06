<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CrudRemoveTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The page is a development tool; Testbench boots as "testing".
        $app['config']->set('isproject.generator.enabled', true);
        $app['config']->set('isproject.generator.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    // ---------------------------------------------------------- the command

    #[Test]
    public function it_deletes_everything_the_generator_wrote(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product'])->assertSuccessful();

        foreach ($this->productFiles() as $file) {
            $this->assertFileExists($this->generated($file));
        }

        $this->artisan('isproject:crud-remove', ['model' => 'Product', '--force' => true])
            ->assertSuccessful();

        foreach ($this->productFiles() as $file) {
            $this->assertFileDoesNotExist($this->generated($file));
        }

        $this->assertDirectoryDoesNotExist($this->generated('views/products'));
    }

    #[Test]
    public function it_never_touches_the_table_or_its_rows(): void
    {
        DB::table('products')->insert(['name' => 'Blue Mug', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('isproject:crud', ['model' => 'Product'])->assertSuccessful();
        $this->artisan('isproject:crud-remove', ['model' => 'Product', '--force' => true])->assertSuccessful();

        // Deleting code is tidying up. Dropping a table is a decision taken in
        // a migration, and this command has no business making it.
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertSame(1, DB::table('products')->count());
    }

    #[Test]
    public function a_dry_run_deletes_nothing(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product'])->assertSuccessful();

        $this->artisan('isproject:crud-remove', ['model' => 'Product', '--dry-run' => true])
            ->assertSuccessful();

        $this->assertFileExists($this->generated('Models/Product.php'));
    }

    #[Test]
    public function it_leaves_other_modules_alone(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product'])->assertSuccessful();
        $this->artisan('isproject:crud', ['model' => 'Category'])->assertSuccessful();

        $this->artisan('isproject:crud-remove', ['model' => 'Product', '--force' => true])->assertSuccessful();

        $this->assertFileDoesNotExist($this->generated('Models/Product.php'));
        $this->assertFileExists($this->generated('Models/Category.php'));
        $this->assertDirectoryExists($this->generated('views/categories'));
    }

    #[Test]
    public function it_refuses_a_model_name_that_is_not_a_class_name(): void
    {
        // The name reaches file paths, so nothing but a bare class name will do.
        foreach (['../../etc/passwd', 'App\Models\Product', 'pro duct', '.env'] as $name) {
            $this->artisan('isproject:crud-remove', ['model' => $name, '--force' => true])
                ->assertFailed();
        }
    }

    #[Test]
    public function removing_something_that_was_never_generated_is_harmless(): void
    {
        $this->artisan('isproject:crud-remove', ['model' => 'Product', '--force' => true])
            ->expectsOutputToContain('Nothing generated for [Product] was found.')
            ->assertSuccessful();
    }

    // ------------------------------------------------------------- routes

    #[Test]
    public function it_removes_the_route_lines_that_point_at_the_controller(): void
    {
        $routes = $this->routesFile();

        $this->artisan('isproject:crud', ['model' => 'Product'])->assertSuccessful();
        $this->artisan('isproject:crud', ['model' => 'Category'])->assertSuccessful();

        $this->assertStringContainsString('ProductController', File::get($routes));

        $this->artisan('isproject:crud-remove', ['model' => 'Product', '--force' => true])->assertSuccessful();

        $written = File::get($routes);

        // A route pointing at a deleted controller is broken whoever wrote it.
        $this->assertStringNotContainsString('ProductController', $written);
        $this->assertStringContainsString('CategoryController', $written);
    }

    #[Test]
    public function it_leaves_a_multi_line_route_definition_for_a_human(): void
    {
        $routes = $this->routesFile();

        $this->artisan('isproject:crud', ['model' => 'Product'])->assertSuccessful();

        // Deleting one line of this would leave the file unparseable, so the
        // command reports it and steps back rather than guessing.
        File::append($routes, "\nRoute::get('extra', [\\App\\Http\\Controllers\\ProductController::class,\n    'index']);\n");

        $this->artisan('isproject:crud-remove', ['model' => 'Product', '--force' => true])
            ->expectsOutputToContain('span several lines')
            ->assertSuccessful();

        $this->assertStringContainsString("Route::get('extra'", File::get($routes));
    }

    // ------------------------------------------------------------ the page

    #[Test]
    public function the_page_requires_the_model_name_to_be_typed(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Product'])->assertSuccessful();

        $this->delete('/isproject/generator', ['model' => 'Product', 'confirm' => 'Prodcut'])
            ->assertSessionHasErrors('confirm');

        // A typo must not delete anything.
        $this->assertFileExists($this->generated('Models/Product.php'));

        $this->delete('/isproject/generator', ['model' => 'Product', 'confirm' => 'Product'])
            ->assertRedirect(route('isproject.generator.index'));

        $this->assertFileDoesNotExist($this->generated('Models/Product.php'));
    }

    #[Test]
    public function the_page_refuses_a_model_with_no_table_behind_it(): void
    {
        $this->delete('/isproject/generator', ['model' => 'Ghost', 'confirm' => 'Ghost'])
            ->assertSessionHasErrors('model');
    }

    #[Test]
    public function the_page_refuses_a_model_name_that_is_not_a_class_name(): void
    {
        $this->delete('/isproject/generator', ['model' => '../../.env', 'confirm' => '../../.env'])
            ->assertSessionHasErrors('model');
    }

    // ------------------------------------------------------------ helpers

    /** @return array<int, string> */
    private function productFiles(): array
    {
        return [
            'Models/Product.php',
            'Controllers/ProductController.php',
            'Requests/StoreProductRequest.php',
            'Requests/UpdateProductRequest.php',
            'Policies/ProductPolicy.php',
            'Factories/ProductFactory.php',
        ];
    }

    private function routesFile(): string
    {
        $path = base_path('build/routes.php');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, "<?php\n");
        config()->set('isproject.routes_file', 'build/routes.php');

        return $path;
    }
}
