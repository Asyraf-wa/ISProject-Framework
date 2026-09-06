<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Concerns\Archivable;
use IsProject\Framework\Concerns\Auditable;
use IsProject\Framework\Models\Audit;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->archivable();
        });

        Schema::create('gadgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    // ------------------------------------------------------ the blueprint macro

    #[Test]
    public function the_blueprint_macro_adds_a_nullable_timestamp(): void
    {
        $this->assertTrue(Schema::hasColumn('widgets', 'archived_at'));

        // Nullable is the whole design: null means active.
        Widget::query()->create(['name' => 'Spanner']);

        $this->assertNull(Widget::query()->sole()->archived_at);
    }

    // ------------------------------------------------------------ the trait

    #[Test]
    public function archiving_stamps_the_column_and_hides_the_record(): void
    {
        $widget = Widget::query()->create(['name' => 'Spanner']);

        $this->assertTrue($widget->archive());
        $this->assertNotNull($widget->fresh()->archived_at);

        // Out of every ordinary query, but still very much in the table.
        $this->assertSame(0, Widget::query()->count());
        $this->assertSame(1, Widget::withArchived()->count());
        $this->assertSame(1, Widget::onlyArchived()->count());
    }

    #[Test]
    public function unarchiving_brings_it_back(): void
    {
        $widget = Widget::query()->create(['name' => 'Spanner']);
        $widget->archive();

        $this->assertTrue($widget->unarchive());
        $this->assertNull($widget->fresh()->archived_at);
        $this->assertSame(1, Widget::query()->count());
    }

    #[Test]
    public function archiving_twice_is_not_an_error_but_changes_nothing(): void
    {
        $widget = Widget::query()->create(['name' => 'Spanner']);
        $widget->archive();
        $stamp = $widget->fresh()->archived_at;

        $this->assertFalse($widget->archive());
        $this->assertFalse((new Widget)->unarchive());
        $this->assertEquals($stamp, $widget->fresh()->archived_at);
    }

    #[Test]
    public function route_binding_can_still_find_an_archived_record(): void
    {
        $widget = Widget::query()->create(['name' => 'Spanner']);
        $widget->archive();

        // Without the resolveRouteBinding override every link out of the
        // archive screen 404s — including the Restore button meant to fix it.
        $this->assertNotNull((new Widget)->resolveRouteBinding($widget->id));
    }

    #[Test]
    public function archiving_and_deleting_are_independent(): void
    {
        $archived = Widget::query()->create(['name' => 'Archived']);
        $trashed = Widget::query()->create(['name' => 'Trashed']);

        $archived->archive();
        $trashed->delete();

        $this->assertSame(0, Widget::query()->count());
        $this->assertSame(['Archived'], Widget::onlyArchived()->pluck('name')->all());
        $this->assertSame(['Trashed'], Widget::onlyTrashed()->pluck('name')->all());
    }

    #[Test]
    public function a_trashed_record_stays_out_of_the_archive(): void
    {
        $widget = Widget::query()->create(['name' => 'Spanner']);
        $widget->archive();
        $widget->delete();

        // Trash wins: a deleted record is out of every list, archive included.
        $this->assertSame(0, Widget::onlyArchived()->count());
        $this->assertSame(1, Widget::onlyTrashed()->withArchived()->count());
    }

    // ------------------------------------------------------------- the audit

    #[Test]
    public function it_records_archived_and_unarchived_as_their_own_events(): void
    {
        $widget = Widget::query()->create(['name' => 'Spanner']);
        Audit::query()->delete();

        $widget->archive();
        $widget->unarchive();

        // Not two anonymous "updated archived_at" rows: the trail should say
        // what happened.
        $this->assertSame(
            [Audit::ARCHIVED, Audit::UNARCHIVED],
            Audit::query()->orderBy('id')->pluck('event')->all()
        );
    }

    // ---------------------------------------------------------- the generator

    #[Test]
    public function it_generates_the_archive_screen_when_the_column_exists(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Widget'])->assertSuccessful();

        $this->assertFileExists($this->generated('views/widgets/archived.blade.php'));

        $model = file_get_contents($this->generated('Models/Widget.php'));
        $this->assertStringContainsString('use IsProject\Framework\Concerns\Archivable;', $model);
        $this->assertStringContainsString('Archivable', $model);

        $controller = $this->generated('Controllers/WidgetController.php');
        $this->assertValidPhp($controller);

        $code = file_get_contents($controller);
        foreach (['archived', 'archive', 'restore', 'purge'] as $method) {
            $this->assertStringContainsString("public function {$method}(", $code);
        }

        // The ternary must be parenthesised, or the search, sort and paginate
        // chained after it would apply to only one of the two branches.
        $this->assertStringContainsString('$query = ($state === \'deleted\'', $code);
    }

    #[Test]
    public function a_table_without_the_column_gets_none_of_it(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Gadget'])->assertSuccessful();

        $this->assertFileDoesNotExist($this->generated('views/gadgets/archived.blade.php'));

        $model = file_get_contents($this->generated('Models/Gadget.php'));
        $this->assertStringNotContainsString('Archivable', $model);

        $code = file_get_contents($this->generated('Controllers/GadgetController.php'));
        $this->assertStringNotContainsString('public function archived(', $code);
        $this->assertStringNotContainsString('public function purge(', $code);
    }

    #[Test]
    public function the_policy_gains_the_matching_methods(): void
    {
        $this->artisan('isproject:crud', ['model' => 'Widget'])->assertSuccessful();

        $policy = file_get_contents($this->generated('Policies/WidgetPolicy.php'));

        $this->assertValidPhp($this->generated('Policies/WidgetPolicy.php'));
        $this->assertStringContainsString('public function archive(', $policy);
        $this->assertStringContainsString('public function purge(', $policy);
    }

    #[Test]
    public function the_archive_routes_are_registered_above_the_resource(): void
    {
        $routes = base_path('build/routes.php');
        File::ensureDirectoryExists(dirname($routes));
        File::put($routes, "<?php\n");
        config()->set('isproject.routes_file', 'build/routes.php');

        $this->artisan('isproject:crud', ['model' => 'Widget', '--only' => 'routes'])->assertSuccessful();

        $written = File::get($routes);

        foreach (['widgets.archived', 'widgets.archive', 'widgets.restore', 'widgets.purge'] as $name) {
            $this->assertStringContainsString("'{$name}'", $written);
        }

        // Laravel matches in registration order: below the resource,
        // widgets/{widget} would capture "archived" as an id.
        $this->assertLessThan(
            strpos($written, "Route::resource('widgets'"),
            strpos($written, 'widgets/archived'),
            'the archive screen must be registered before the resource route'
        );
    }

    // ----------------------------------------------------- the make command

    #[Test]
    public function the_command_writes_a_migration_rather_than_altering_the_table(): void
    {
        $this->artisan('isproject:archivable', ['table' => 'gadgets'])->assertSuccessful();

        $written = File::glob(base_path('database/migrations/*_add_archived_at_to_gadgets_table.php'));

        $this->assertCount(1, $written);
        $this->assertStringContainsString('$table->archivable();', File::get($written[0]));

        // The column must NOT exist yet: writing a migration and running it are
        // two steps on purpose, so migrations stay the source of truth.
        $this->assertFalse(Schema::hasColumn('gadgets', 'archived_at'));

        File::delete($written);
    }

    #[Test]
    public function the_command_refuses_a_table_that_does_not_exist(): void
    {
        $this->artisan('isproject:archivable', ['table' => 'nothing_like_this'])->assertFailed();
    }

    #[Test]
    public function the_command_says_so_when_the_column_is_already_there(): void
    {
        $this->artisan('isproject:archivable', ['table' => 'widgets'])->assertSuccessful();

        $this->assertCount(0, File::glob(base_path('database/migrations/*_add_archived_at_to_widgets_table.php')));
    }
}

/** Models that exist only for these tests. */
class Widget extends Model
{
    use Archivable, Auditable, SoftDeletes;

    protected $guarded = [];
}

class Gadget extends Model
{
    protected $guarded = [];
}
