<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Models\Activity;
use IsProject\Framework\Support\Settings;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', DashboardTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('dashboard_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    // ------------------------------------------------------------- the screen

    #[Test]
    public function it_renders_with_no_data_at_all(): void
    {
        // The first screen somebody opens on a fresh install. Empty charts, not
        // a stack trace.
        $this->actingAs($this->user())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Sign-ins, last 14 days');
    }

    #[Test]
    public function it_survives_the_activity_table_being_missing(): void
    {
        Schema::drop('isproject_activities');

        $this->actingAs($this->user())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('activity log table is not there yet');
    }

    #[Test]
    public function it_needs_a_signed_in_reader(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    #[Test]
    public function the_stat_tiles_count_today(): void
    {
        $this->seedActivity(Activity::LOGIN, 3);
        $this->seedActivity(Activity::LOGIN_FAILED, 2);

        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/is-stat-value">\s*3\s*</', $html);
        $this->assertMatchesRegularExpression('/is-stat-value">\s*2\s*</', $html);
    }

    // -------------------------------------------------------------- the charts

    #[Test]
    public function the_trend_chart_has_a_point_for_every_day_including_empty_ones(): void
    {
        $this->seedActivity(Activity::LOGIN, 1);

        $option = $this->chartOption(0);

        // A line chart that skips quiet days draws a straight line through the
        // gap and makes a quiet week look busy.
        $this->assertCount(14, $option['xAxis']['data']);
        $this->assertCount(14, $option['series'][0]['data']);
        $this->assertSame(1, $option['series'][0]['data'][13]);
        $this->assertSame(0, $option['series'][0]['data'][0]);
    }

    #[Test]
    public function the_charts_carry_no_colours_of_their_own(): void
    {
        $this->seedActivity(Activity::LOGIN, 1);

        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        // Colours are applied in the browser from the stylesheet, so the charts
        // follow the light and dark themes. A colour baked in here would not.
        $this->assertStringNotContainsString('&quot;color&quot;:', $html);
    }

    #[Test]
    public function the_event_mix_uses_readable_names(): void
    {
        $this->seedActivity(Activity::LOGIN_FAILED, 2);

        $option = $this->chartOption(1);

        $this->assertSame('Sign-in failed', $option['series'][0]['data'][0]['name']);
        $this->assertSame(2, $option['series'][0]['data'][0]['value']);
    }

    // ------------------------------------------------------------ the library

    #[Test]
    public function echarts_is_shipped_with_the_other_assets(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2).'/resources/dist/echarts.min.js');

        // Apache-2.0 asks that recipients get a copy of the licence.
        $this->assertFileExists(dirname(__DIR__, 2).'/resources/dist/echarts.LICENSE.txt');
    }

    #[Test]
    public function echarts_loads_only_where_there_is_a_chart(): void
    {
        $user = $this->user();

        // 664 KB. A page with no chart on it should not pay for that.
        $this->actingAs($user)->get('/settings')->assertOk()->assertDontSee('echarts.min.js');
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('echarts.min.js');
    }

    // ------------------------------------------------------- the sign-in page

    #[Test]
    public function the_signed_out_screens_show_the_showcase(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('is-showcase', false)
            ->assertSee('Everything your team needs, in one place.');
    }

    #[Test]
    public function the_headline_prefers_the_tagline_from_settings(): void
    {
        app(Settings::class)->set(['app_tagline' => 'The registry, online.']);
        app()->forgetInstance(Settings::class);

        $this->get('/login')
            ->assertOk()
            ->assertSee('The registry, online.')
            ->assertDontSee('Everything your team needs, in one place.');
    }

    #[Test]
    public function the_showcase_can_be_switched_off_for_a_plain_card(): void
    {
        config(['isproject.landing.enabled' => false]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('is-showcase', false)
            // The form is the point; it must survive the panel going away.
            ->assertSee('Sign in');
    }

    // --------------------------------------------------------------- helpers

    /**
     * The option array the view handed to the chart component, decoded back out
     * of the page. Asserting on the rendered JSON is what proves the browser
     * receives what the controller built.
     *
     * @return array<string, mixed>
     */
    private function chartOption(int $index): array
    {
        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        preg_match_all("/data-is-chart='(.*?)'/s", $html, $matches);

        $this->assertArrayHasKey($index, $matches[1], 'That chart was not rendered.');

        return json_decode(html_entity_decode($matches[1][$index], ENT_QUOTES), true);
    }

    private function seedActivity(string $event, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            Activity::query()->create([
                'event' => $event,
                'description' => 'Seeded',
                'created_at' => now(),
            ]);
        }
    }

    private function user(): DashboardTestUser
    {
        return DashboardTestUser::query()->firstOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Administrator', 'password' => 'hashed'],
        );
    }
}

/** A User model that exists only for these tests. */
class DashboardTestUser extends Authenticatable
{
    protected $table = 'dashboard_users';

    protected $guarded = [];
}
