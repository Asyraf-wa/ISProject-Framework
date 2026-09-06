<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use IsProject\Framework\Support\Settings;
use IsProject\Framework\Support\SettingsSchema;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The package's own auth stack is out of scope here; the gate is what
        // this module actually controls, and it gets its own test below.
        $app['config']->set('isproject.settings.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // ------------------------------------------------------------------ store

    #[Test]
    public function the_migration_creates_the_settings_table(): void
    {
        $this->assertTrue(Schema::hasTable(Settings::TABLE));
    }

    #[Test]
    public function it_falls_back_to_defaults_before_anything_is_saved(): void
    {
        config()->set('app.name', 'Lab Portal');

        // "@config:app.name" means the screen opens showing the value the
        // application is really using, not a blank box.
        $this->assertSame('Lab Portal', $this->settings()->get('app_name'));
    }

    #[Test]
    public function it_survives_the_table_not_existing(): void
    {
        Schema::drop(Settings::TABLE);
        Cache::forget(Settings::CACHE_KEY);
        config()->set('app.name', 'Before Migration');

        // The shell renders on a fresh install too, so a missing table must not
        // be an exception.
        $this->assertSame('Before Migration', $this->settings()->get('app_name'));
    }

    #[Test]
    public function saved_values_win_over_defaults(): void
    {
        $this->settings()->set(['app_name' => 'Faculty Portal']);

        $this->assertSame('Faculty Portal', $this->freshSettings()->get('app_name'));
    }

    #[Test]
    public function a_blank_value_falls_back_rather_than_rendering_empty(): void
    {
        $this->settings()->set(['support_email' => '']);

        $this->assertSame('none@example.test', $this->freshSettings()->get('support_email', 'none@example.test'));
    }

    #[Test]
    public function writing_clears_the_cache(): void
    {
        $this->settings()->all();
        $this->assertTrue(Cache::has(Settings::CACHE_KEY));

        $this->settings()->set(['app_name' => 'Changed']);

        $this->assertFalse(Cache::has(Settings::CACHE_KEY));
        $this->assertSame('Changed', $this->freshSettings()->get('app_name'));
    }

    // ------------------------------------------------------------------ screen

    #[Test]
    public function it_shows_every_configured_group_and_field(): void
    {
        $this->get('/settings')
            ->assertOk()
            ->assertSee('General')
            ->assertSee('Appearance')
            ->assertSee('System name')
            ->assertSee('Favicon')
            ->assertSee('Timezone');
    }

    #[Test]
    public function it_saves_the_form(): void
    {
        $this->put('/settings', [
            'app_name' => 'Faculty Portal',
            'app_tagline' => 'Information Systems',
            'support_email' => 'help@faculty.test',
            'timezone' => 'Asia/Kuala_Lumpur',
        ])->assertRedirect()->assertSessionHas('success');

        $settings = $this->freshSettings();

        $this->assertSame('Faculty Portal', $settings->get('app_name'));
        $this->assertSame('Asia/Kuala_Lumpur', $settings->get('timezone'));
    }

    #[Test]
    public function the_system_name_is_required(): void
    {
        $this->put('/settings', ['app_name' => ''])->assertSessionHasErrors('app_name');
    }

    #[Test]
    public function it_validates_by_field_type(): void
    {
        $this->put('/settings', [
            'app_name' => 'Fine',
            'support_email' => 'not-an-email',
        ])->assertSessionHasErrors('support_email');
    }

    // ------------------------------------------------------------------ files

    #[Test]
    public function it_stores_an_uploaded_logo(): void
    {
        $this->put('/settings', [
            'app_name' => 'Faculty Portal',
            'logo' => UploadedFile::fake()->image('brand.png', 240, 64),
        ])->assertSessionHasNoErrors();

        $stored = $this->freshSettings()->get('logo');

        $this->assertNotNull($stored);
        Storage::disk('public')->assertExists($stored);
    }

    #[Test]
    public function replacing_an_upload_deletes_the_file_it_replaced(): void
    {
        $this->put('/settings', [
            'app_name' => 'A',
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);
        $first = $this->freshSettings()->get('logo');

        // The stored name carries a timestamp, so the second upload must not
        // land on the same path within the same second.
        $this->travel(1)->second();

        $this->put('/settings', [
            'app_name' => 'A',
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);
        $second = $this->freshSettings()->get('logo');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    #[Test]
    public function the_remove_checkbox_clears_an_upload(): void
    {
        $this->put('/settings', [
            'app_name' => 'A',
            'logo' => UploadedFile::fake()->image('brand.png'),
        ]);
        $path = $this->freshSettings()->get('logo');

        $this->put('/settings', ['app_name' => 'A', 'remove' => ['logo']])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->freshSettings()->get('logo'));
        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function a_new_upload_beats_a_left_over_remove_tick(): void
    {
        $this->put('/settings', ['app_name' => 'A', 'logo' => UploadedFile::fake()->image('one.png')]);

        $this->put('/settings', [
            'app_name' => 'A',
            'remove' => ['logo'],
            'logo' => UploadedFile::fake()->image('two.png'),
        ]);

        $this->assertNotNull($this->freshSettings()->get('logo'));
    }

    #[Test]
    public function it_refuses_an_svg_upload(): void
    {
        // An SVG can carry script and is served from the application's own
        // origin, so it must not be accepted however it is labelled.
        $this->put('/settings', [
            'app_name' => 'A',
            'logo' => UploadedFile::fake()->create('payload.svg', 4, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');

        $this->assertNull($this->freshSettings()->get('logo'));
    }

    #[Test]
    public function it_refuses_a_remove_for_something_that_is_not_a_file_setting(): void
    {
        $this->put('/settings', ['app_name' => 'A', 'remove' => ['app_name']])
            ->assertSessionHasErrors('remove.0');
    }

    // ------------------------------------------------------------------ caches

    #[Test]
    public function it_clears_a_cache_and_flushes_its_own_entry(): void
    {
        $this->settings()->all();
        $this->assertTrue(Cache::has(Settings::CACHE_KEY));

        $this->post('/settings/cache', ['action' => 'view'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse(Cache::has(Settings::CACHE_KEY));
    }

    #[Test]
    public function it_only_runs_commands_from_its_own_list(): void
    {
        // Whatever the browser sends only ever selects a key from a fixed map,
        // so an artisan command name posted directly is simply not a valid
        // choice. The rows below prove nothing ran: a rejected action never
        // reaches Artisan, and the settings cache is left untouched.
        $this->settings()->all();

        // "cache" is deliberately absent: it is a legitimate action. These are
        // the shapes that must never be accepted — arbitrary artisan commands,
        // a different case, and a traversal-flavoured value.
        foreach (['migrate:fresh', 'db:wipe', 'optimize', 'CACHE', '../cache'] as $action) {
            $this->post('/settings/cache', ['action' => $action])
                ->assertSessionHasErrors('action', "[{$action}] should not be an accepted cache action");
        }

        $this->assertTrue(Cache::has(Settings::CACHE_KEY));
    }

    #[Test]
    public function it_only_offers_the_configured_cache_buttons(): void
    {
        config()->set('isproject.settings.cache_actions', ['view']);

        $this->get('/settings')->assertOk()->assertSee('Compiled views')->assertDontSee('Route cache');

        $this->post('/settings/cache', ['action' => 'route'])->assertSessionHasErrors('action');
    }

    // --------------------------------------------------------- authorisation

    #[Test]
    public function the_gate_can_lock_the_screen_down(): void
    {
        config()->set('isproject.settings.gate', 'manage-settings');
        Gate::define('manage-settings', fn () => false);

        $this->get('/settings')->assertForbidden();
        $this->put('/settings', ['app_name' => 'Nope'])->assertForbidden();
        $this->post('/settings/cache', ['action' => 'view'])->assertForbidden();
    }

    #[Test]
    public function no_gate_means_the_route_middleware_is_the_only_guard(): void
    {
        config()->set('isproject.settings.gate', null);

        $this->get('/settings')->assertOk();
    }

    // --------------------------------------------------------------- schema

    #[Test]
    public function unknown_field_types_and_option_lists_degrade_quietly(): void
    {
        config()->set('isproject.settings.groups', [
            'odd' => ['label' => 'Odd', 'fields' => [
                'weird' => ['type' => 'quantum'],
                'picker' => ['type' => 'select', 'options' => '@nothing-like-this'],
            ]],
        ]);

        $fields = (new SettingsSchema)->fields();

        $this->assertSame('text', $fields['weird']['type']);
        $this->assertSame([], $fields['picker']['options']);
    }

    #[Test]
    public function built_in_option_lists_are_resolved(): void
    {
        $fields = (new SettingsSchema)->fields();

        $this->assertArrayHasKey('grid', $fields['brand_icon']['options']);
        $this->assertArrayHasKey('Asia/Kuala_Lumpur', $fields['timezone']['options']);
        $this->assertSame(['system', 'light', 'dark'], array_keys($fields['default_theme']['options']));
    }

    #[Test]
    public function the_config_contains_no_closures_so_config_cache_still_works(): void
    {
        // config:cache serialises with var_export(), which cannot represent a
        // closure. One closure anywhere in this file breaks that command for
        // the whole application, so assert the config round-trips.
        $exported = var_export(config('isproject'), true);

        $this->assertStringNotContainsString('Closure', $exported);
        $this->assertIsArray(eval("return {$exported};"));
    }

    // -------------------------------------------------------------- helpers

    #[Test]
    public function the_helper_reads_the_same_values(): void
    {
        $this->settings()->set(['app_name' => 'Via Helper']);
        $this->freshSettings();

        $this->assertSame('Via Helper', isproject_setting('app_name'));
        $this->assertSame('fallback', isproject_setting('nothing_here', 'fallback'));
    }

    private function settings(): Settings
    {
        return app(Settings::class);
    }

    /**
     * A new instance, so assertions read the database rather than the copy the
     * request-local cache is holding.
     */
    private function freshSettings(): Settings
    {
        app()->forgetInstance(Settings::class);
        Cache::forget(Settings::CACHE_KEY);

        return app(Settings::class);
    }
}
