<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Http\Middleware\EnsureGeneratorIsEnabled;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GeneratorPageTest extends TestCase
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
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    // ------------------------------------------------------------- gating

    #[Test]
    public function it_is_disabled_in_production_whatever_the_config_says(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config()->set('isproject.generator.enabled', true);

        $this->assertFalse(
            EnsureGeneratorIsEnabled::enabled(),
            'the generator must never be reachable in production'
        );
    }

    #[Test]
    public function it_defaults_to_the_local_environment_only(): void
    {
        config()->set('isproject.generator.enabled', null);

        $this->app->detectEnvironment(fn () => 'local');
        $this->assertTrue(EnsureGeneratorIsEnabled::enabled());

        $this->app->detectEnvironment(fn () => 'staging');
        $this->assertFalse(EnsureGeneratorIsEnabled::enabled());
    }

    #[Test]
    public function the_middleware_hides_the_page_when_disabled(): void
    {
        config()->set('isproject.generator.enabled', false);

        $this->get('/isproject/generator')->assertNotFound();
    }

    // -------------------------------------------------------------- listing

    #[Test]
    public function it_lists_the_database_tables(): void
    {
        $response = $this->get('/isproject/generator');

        $response->assertOk()
            ->assertSee('products')
            ->assertSee('categories')
            ->assertSee('Generate CRUD');
    }

    #[Test]
    public function it_hides_framework_tables(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->text('exception');
        });

        $this->get('/isproject/generator')->assertOk()->assertDontSee('failed_jobs');
    }

    #[Test]
    public function it_hides_this_packages_own_tables(): void
    {
        // Matched by the "isproject_*" pattern rather than listed one by one,
        // so a table added by a future version is covered too. Nobody wants
        // "generate CRUD for isproject_permission_role" offered to them.
        $response = $this->get('/isproject/generator')->assertOk();

        foreach (['isproject_settings', 'isproject_roles', 'isproject_permission_role', 'isproject_audits'] as $table) {
            $response->assertDontSee($table);
        }
    }

    #[Test]
    public function it_describes_what_a_table_will_produce(): void
    {
        $this->get('/isproject/generator')
            ->assertOk()
            ->assertSee('Product')          // singular model name
            ->assertSee('/products')        // route it will live at
            ->assertSee('soft deletes')     // detected from deleted_at
            ->assertSee('category_id');     // column preview
    }

    // ----------------------------------------------------------- generating

    #[Test]
    public function it_generates_a_module_from_the_button(): void
    {
        $response = $this->post('/isproject/generator', [
            'table' => 'products',
            'model' => 'Product',
            'targets' => ['model', 'controller', 'views', 'report'],
        ]);

        $response->assertRedirect(route('isproject.generator.index'))
            ->assertSessionHas('success');

        $this->assertValidPhp($this->generated('Models/Product.php'));
        $this->assertValidPhp($this->generated('Controllers/ProductController.php'));
        $this->assertFileExists($this->generated('views/products/index.blade.php'));
        $this->assertFileExists($this->generated('views/products/report.blade.php'));
    }

    #[Test]
    public function it_refuses_a_table_that_is_not_on_the_connection(): void
    {
        $this->post('/isproject/generator', [
            'table' => 'wp_users; DROP TABLE products',
            'targets' => ['model'],
        ])->assertSessionHasErrors('table');

        $this->assertFileDoesNotExist($this->generated('Models/WpUser.php'));
    }

    #[Test]
    public function it_refuses_a_table_that_config_ignores(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
        });

        $this->post('/isproject/generator', [
            'table' => 'failed_jobs',
            'targets' => ['model'],
        ])->assertSessionHasErrors('table');
    }

    #[Test]
    public function it_rejects_a_model_name_that_is_not_a_class_name(): void
    {
        $this->post('/isproject/generator', [
            'table' => 'products',
            'model' => '../../etc/passwd',
            'targets' => ['model'],
        ])->assertSessionHasErrors('model');
    }

    #[Test]
    public function it_requires_at_least_one_target(): void
    {
        $this->post('/isproject/generator', [
            'table' => 'products',
            'targets' => [],
        ])->assertSessionHasErrors('targets');
    }

    #[Test]
    public function it_rejects_an_unknown_target(): void
    {
        $this->post('/isproject/generator', [
            'table' => 'products',
            'targets' => ['wipe-database', 'model'],
        ])->assertSessionHasErrors('targets.0');
    }

    #[Test]
    public function it_does_not_overwrite_edits_unless_asked(): void
    {
        $this->post('/isproject/generator', ['table' => 'products', 'targets' => ['model']]);

        File::put($this->generated('Models/Product.php'), '<?php // edited by hand');

        $this->post('/isproject/generator', ['table' => 'products', 'targets' => ['model']]);
        $this->assertStringContainsString('edited by hand', File::get($this->generated('Models/Product.php')));

        $this->post('/isproject/generator', ['table' => 'products', 'targets' => ['model'], 'force' => '1']);
        $this->assertStringNotContainsString('edited by hand', File::get($this->generated('Models/Product.php')));
    }
}
