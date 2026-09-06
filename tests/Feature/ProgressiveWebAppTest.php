<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use IsProject\Framework\Support\PermissionRegistry;
use IsProject\Framework\Support\Pwa;
use IsProject\Framework\Support\Settings;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ProgressiveWebAppTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', PwaTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('pwa_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Storage::fake('public');
    }

    // ------------------------------------------------------------- the switch

    #[Test]
    public function it_is_off_until_somebody_turns_it_on(): void
    {
        $this->assertFalse(app(Pwa::class)->enabled());

        $this->get('/manifest.webmanifest')->assertNotFound();
    }

    #[Test]
    public function the_manifest_appears_once_it_is_switched_on(): void
    {
        $this->enable();

        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json');
    }

    #[Test]
    public function the_manifest_is_never_held_in_a_browser_cache(): void
    {
        $this->enable();

        // Cached for an hour, turning the feature off would do nothing for an
        // hour: the browser would go on reading a withdrawn manifest.
        $header = $this->get('/manifest.webmanifest')->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('no-cache', $header);
        $this->assertStringNotContainsString('max-age=3600', $header);
    }

    // ---------------------------------------------------------- the worker

    #[Test]
    public function the_worker_route_answers_whether_it_is_on_or_off(): void
    {
        $off = $this->get('/isproject-sw.js');

        $off->assertOk();
        $this->assertStringContainsString('application/javascript', $off->headers->get('Content-Type'));

        $this->enable();

        $this->get('/isproject-sw.js')->assertOk();
    }

    #[Test]
    public function switching_it_off_serves_a_worker_that_removes_itself(): void
    {
        // The whole point. A registered worker outlives the page that asked for
        // it, so "off" has to be an instruction, not silence.
        $body = $this->get('/isproject-sw.js')->getContent();

        $this->assertStringContainsString('registration.unregister', $body);
        $this->assertStringContainsString('caches.delete', $body);
        $this->assertStringNotContainsString('caches.open', $body);
    }

    #[Test]
    public function switching_it_on_serves_a_worker_that_caches(): void
    {
        $this->enable();

        $body = $this->get('/isproject-sw.js')->getContent();

        $this->assertStringContainsString('caches.open', $body);
        $this->assertStringNotContainsString('registration.unregister', $body);
    }

    #[Test]
    public function the_worker_script_is_never_cached_by_the_browser(): void
    {
        $this->enable();

        // A stale worker script is how a site pins itself to a version it can
        // no longer replace — including the kill switch.
        $header = $this->get('/isproject-sw.js')->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $header);
    }

    #[Test]
    public function the_worker_never_caches_a_page(): void
    {
        $this->enable();

        $body = $this->get('/isproject-sw.js')->getContent();

        // Every screen is behind sign-in and filtered by role, and these are
        // shared machines: a cached page shows the next person the last one's
        // work. Navigations go to the network or to the offline page.
        $this->assertStringContainsString("request.mode === 'navigate'", $body);
        $this->assertMatchesRegularExpression('/navigate.*?\n.*?fetch\(request\)/s', $body);
    }

    // ------------------------------------------------------------ precache

    #[Test]
    public function the_precache_list_holds_paths_not_absolute_urls(): void
    {
        $this->enable();

        foreach (app(Pwa::class)->precache() as $entry) {
            // A worker resolves a path against the host the visitor is on.
            // An absolute URL built from APP_URL is cross-origin the moment
            // those differ, and caching one silently stores nothing.
            $this->assertStringStartsWith('/', $entry);
            $this->assertStringNotContainsString('://', $entry);
        }
    }

    #[Test]
    public function the_offline_page_is_precached_and_reachable_without_signing_in(): void
    {
        $this->enable();

        $this->assertContains('/offline', app(Pwa::class)->precache());

        $this->get('/offline')->assertOk()->assertSee('You are offline');
    }

    // ------------------------------------------------------------- manifest

    #[Test]
    public function the_manifest_describes_the_configured_app(): void
    {
        $this->enable([
            'pwa_name' => 'Faculty Portal',
            'pwa_short_name' => 'Faculty',
            'pwa_theme_color' => '#123456',
            'pwa_display' => 'minimal-ui',
        ]);

        $manifest = app(Pwa::class)->manifest();

        $this->assertSame('Faculty Portal', $manifest['name']);
        $this->assertSame('Faculty', $manifest['short_name']);
        $this->assertSame('#123456', $manifest['theme_color']);
        $this->assertSame('minimal-ui', $manifest['display']);
    }

    #[Test]
    public function the_scope_and_start_url_keep_their_trailing_slash(): void
    {
        $this->enable();

        $manifest = app(Pwa::class)->manifest();

        // "https://example.test" as a scope resolves to the parent of the root.
        $this->assertStringEndsWith('/', $manifest['scope']);
        $this->assertStringEndsWith('/', $manifest['start_url']);
    }

    #[Test]
    public function a_missing_short_name_is_cut_from_the_long_one(): void
    {
        $this->enable(['pwa_name' => 'Faculty of Information Systems Portal']);

        // Home screens truncate at about a dozen characters anyway; better to
        // decide where the cut falls than to let the launcher do it.
        $this->assertSame(12, mb_strlen(app(Pwa::class)->shortName()));
    }

    #[Test]
    public function a_nonsense_colour_falls_back_rather_than_reaching_the_page(): void
    {
        $this->enable(['pwa_theme_color' => 'red; }</style><script>alert(1)</script>']);

        $this->assertSame('#4338ca', app(Pwa::class)->themeColor());
    }

    #[Test]
    public function an_unknown_display_mode_falls_back_to_standalone(): void
    {
        $this->enable(['pwa_display' => 'hologram']);

        $this->assertSame('standalone', app(Pwa::class)->display());
    }

    // ----------------------------------------------------------------- icons

    #[Test]
    public function there_are_no_icons_until_one_is_uploaded(): void
    {
        $this->enable();

        $this->assertSame([], app(Pwa::class)->icons());
        $this->assertNull(app(Pwa::class)->icon());
    }

    #[Test]
    public function an_icon_is_declared_at_the_size_it_actually_is(): void
    {
        $this->enable(['pwa_icon' => $this->storeIcon(512)]);

        $icons = app(Pwa::class)->icons();

        // Claiming 512 for a 64px file makes a browser fetch it, measure it and
        // refuse the install without explaining why.
        $this->assertCount(1, $icons);
        $this->assertSame('512x512', $icons[0]['sizes']);
        $this->assertSame('any', $icons[0]['purpose']);
    }

    #[Test]
    public function a_maskable_icon_is_only_declared_when_it_was_said_to_have_padding(): void
    {
        $this->enable(['pwa_icon' => $this->storeIcon(512)]);
        $this->assertCount(1, app(Pwa::class)->icons());

        $this->enable(['pwa_icon_maskable' => true]);

        // An unpadded icon declared maskable gets its edges cropped on Android.
        $purposes = array_column(app(Pwa::class)->icons(), 'purpose');
        $this->assertSame(['any', 'maskable'], $purposes);
    }

    #[Test]
    public function a_small_icon_is_reported_as_not_good_enough(): void
    {
        $this->enable(['pwa_icon' => $this->storeIcon(256)]);

        $icon = collect(app(Pwa::class)->checks())->firstWhere('label', 'An app icon of at least 512px square');

        $this->assertFalse($icon['ok']);
        $this->assertStringContainsString('256×256', $icon['detail']);
    }

    #[Test]
    public function the_screen_refuses_an_icon_that_is_too_small_or_not_square(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put('/settings', ['app_name' => 'Test Site', 'pwa_icon' => UploadedFile::fake()->image('icon.png', 64, 64)])
            ->assertSessionHasErrors('pwa_icon');

        $this->actingAs($user)
            ->put('/settings', ['app_name' => 'Test Site', 'pwa_icon' => UploadedFile::fake()->image('icon.png', 512, 300)])
            ->assertSessionHasErrors('pwa_icon');
    }

    #[Test]
    public function the_screen_refuses_an_svg_icon(): void
    {
        // An SVG can carry script and is served from this application's own
        // origin, so it is never an accepted upload anywhere in the framework.
        $this->actingAs($this->user())
            ->put('/settings', ['app_name' => 'Test Site', 'pwa_icon' => UploadedFile::fake()->create('icon.svg', 8, 'image/svg+xml')])
            ->assertSessionHasErrors('pwa_icon');
    }

    #[Test]
    public function a_square_icon_of_a_good_size_is_accepted(): void
    {
        $this->actingAs($this->user())
            ->put('/settings', ['app_name' => 'Test Site', 'pwa_icon' => UploadedFile::fake()->image('icon.png', 512, 512)])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(app(Settings::class)->get('pwa_icon'));
    }

    // -------------------------------------------------------------- the head

    #[Test]
    public function the_layout_links_the_manifest_and_registers_the_worker(): void
    {
        $this->enable();

        $this->actingAs($this->user())
            ->get('/settings')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('serviceWorker.register', false);
    }

    #[Test]
    public function the_layout_cleans_up_after_itself_when_it_is_switched_off(): void
    {
        $response = $this->actingAs($this->user())->get('/settings');

        // Not merely the absence of a registration: a browser that installed
        // the worker while it was on needs to be told to let go of it.
        $response->assertOk()
            ->assertDontSee('rel="manifest"', false)
            ->assertSee('getRegistrations', false)
            ->assertSee('registration.unregister', false);
    }

    // --------------------------------------------------------------- context

    #[Test]
    public function a_plain_http_host_is_reported_as_unusable(): void
    {
        $this->enable();

        // Testbench serves from http://localhost, which browsers treat as
        // secure; a deployed http:// host is the case that bites.
        $this->assertTrue(app(Pwa::class)->isSecureContext());

        $checks = collect(app(Pwa::class)->checks())->firstWhere('label', 'Served over HTTPS');
        $this->assertTrue($checks['ok']);
    }

    #[Test]
    public function readiness_fails_while_anything_is_missing(): void
    {
        $this->enable();

        $this->assertFalse(app(Pwa::class)->ready());

        $this->enable(['pwa_icon' => $this->storeIcon(512)]);

        $this->assertTrue(app(Pwa::class)->ready());
    }

    #[Test]
    public function the_pwa_routes_are_not_permissions(): void
    {
        $names = collect(app(PermissionRegistry::class)->modules())
            ->flatMap(fn (array $module) => array_column($module['abilities'], 'name'));

        // The browser fetches all three before anybody signs in, so they can no
        // more be permission-controlled than the sign-in screen itself.
        $this->assertNotContains('isproject.pwa.manifest', $names);
        $this->assertNotContains('isproject.pwa.serviceworker', $names);
        $this->assertNotContains('isproject.pwa.offline', $names);
    }

    // --------------------------------------------------------------- helpers

    /** @param  array<string, mixed>  $extra */
    private function enable(array $extra = []): void
    {
        app(Settings::class)->set(['pwa_enabled' => true] + $extra);

        app()->forgetInstance(Settings::class);
        app()->forgetInstance(Pwa::class);
    }

    /** A real square PNG on the fake disk, so its size can be measured. */
    private function storeIcon(int $size): string
    {
        $path = 'isproject/pwa-icon.png';

        Storage::disk('public')->put(
            $path,
            UploadedFile::fake()->image('icon.png', $size, $size)->get(),
        );

        return $path;
    }

    private function user(): PwaTestUser
    {
        return PwaTestUser::query()->create([
            'name' => 'Lecturer',
            'email' => 'lecturer@example.test',
            'password' => 'hashed',
        ]);
    }
}

/** A User model that exists only for these tests. */
class PwaTestUser extends Authenticatable
{
    protected $table = 'pwa_users';

    protected $guarded = [];
}
