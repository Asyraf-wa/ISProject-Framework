<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Support\Assets;
use IsProject\Framework\Support\Pwa;
use IsProject\Framework\Support\Settings;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AssetVersionTest extends TestCase
{
    use RefreshDatabase;

    private string $published;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', AssetTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('asset_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        // Stand in for `vendor:publish --tag=isproject-assets`.
        $this->published = public_path('vendor/isproject');
        File::ensureDirectoryExists($this->published);
        File::put($this->published.'/isproject.css', '/* first */');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('vendor'));

        parent::tearDown();
    }

    // ------------------------------------------------------------ versioning

    #[Test]
    public function a_published_asset_carries_a_version(): void
    {
        $this->assertMatchesRegularExpression(
            '~/vendor/isproject/isproject\.css\?v=[0-9a-f]{8}$~',
            app(Assets::class)->url('isproject.css'),
        );
    }

    #[Test]
    public function changing_the_file_changes_the_url(): void
    {
        $before = app(Assets::class)->url('isproject.css');

        // The whole bug: re-publishing after an upgrade changes the bytes but
        // not the URL, so browsers go on serving the copy they already have.
        File::put($this->published.'/isproject.css', '/* second, and longer than the first */');
        touch($this->published.'/isproject.css', time() + 10);
        app()->forgetInstance(Assets::class);

        $this->assertNotSame($before, app(Assets::class)->url('isproject.css'));
    }

    #[Test]
    public function the_same_file_keeps_the_same_url(): void
    {
        // Otherwise every request would bust the cache, which is the opposite
        // failure and quietly costs every visitor the download.
        $first = app(Assets::class)->url('isproject.css');

        app()->forgetInstance(Assets::class);

        $this->assertSame($first, app(Assets::class)->url('isproject.css'));
    }

    #[Test]
    public function an_unpublished_asset_gets_no_version_rather_than_a_broken_one(): void
    {
        // Before `vendor:publish` has been run. Appending a version to a 404
        // helps nobody, and the page still has to render.
        $this->assertStringEndsWith(
            '/vendor/isproject/never-published.js',
            app(Assets::class)->url('never-published.js'),
        );
    }

    // --------------------------------------------------------------- the page

    #[Test]
    public function the_layouts_ask_for_the_versioned_url(): void
    {
        $html = $this->actingAs($this->user())->get('/settings')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~isproject\.css\?v=[0-9a-f]{8}~', $html);
    }

    #[Test]
    public function the_sign_in_screen_does_too(): void
    {
        // The guest layout is a separate file, and has been forgotten before.
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~isproject\.css\?v=[0-9a-f]{8}~', $html);
    }

    // ------------------------------------------------------- service worker

    #[Test]
    public function the_service_worker_precaches_versioned_paths(): void
    {
        app(Settings::class)->set(['pwa_enabled' => true]);
        app()->forgetInstance(Settings::class);
        app()->forgetInstance(Pwa::class);

        $precache = app(Pwa::class)->precache();
        $stylesheet = collect($precache)->first(fn (string $path) => str_contains($path, 'isproject.css'));

        // Without a changing path the worker would serve the old stylesheet
        // from its own cache indefinitely — long after a browser gave up on it.
        $this->assertNotNull($stylesheet);
        $this->assertMatchesRegularExpression('~^/vendor/isproject/isproject\.css\?v=[0-9a-f]{8}$~', $stylesheet);
    }

    private function user(): AssetTestUser
    {
        return AssetTestUser::query()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'hashed',
        ]);
    }
}

/** A User model that exists only for these tests. */
class AssetTestUser extends Authenticatable
{
    protected $table = 'asset_users';

    protected $guarded = [];
}
