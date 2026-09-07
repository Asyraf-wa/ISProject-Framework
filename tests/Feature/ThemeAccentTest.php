<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Support\Settings;
use IsProject\Framework\Support\Theme;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ThemeAccentTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', AccentTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('accent_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    // ------------------------------------------------------------ the presets

    #[Test]
    public function every_preset_is_legible_in_both_themes(): void
    {
        // The whole reason this is a fixed set and not a colour picker. Each
        // accent is used as text on a light card, as a white-labelled button,
        // and as its dark tint on the dark panel.
        foreach (Theme::PRESETS as $key => [$hex, $label, $light, $dark]) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex, "{$key} is not a hex value");

            $this->assertGreaterThanOrEqual(4.5, $light, "{$key} fails as text on a light card");
            $this->assertGreaterThanOrEqual(4.5, $dark, "{$key} fails as text on the dark panel");

            // The stated figure has to be the real one, or the screen is lying.
            $this->assertEqualsWithDelta($light, $this->ratio($hex, '#ffffff'), 0.05, "{$key}: light figure is wrong");
            $this->assertEqualsWithDelta($dark, $this->ratio($this->tint($hex, 0.45), '#0f172a'), 0.05, "{$key}: dark figure is wrong");
        }
    }

    #[Test]
    public function a_white_label_reads_on_every_preset(): void
    {
        foreach (Theme::PRESETS as $key => [$hex]) {
            // Primary buttons paint white on the accent. Cyan is the colour
            // this rules out — it looks fine and fails here at 2.1:1.
            $this->assertGreaterThanOrEqual(
                4.5,
                $this->ratio('#ffffff', $hex),
                "A white button label on {$key} is unreadable",
            );
        }
    }

    #[Test]
    public function the_default_is_the_colour_the_stylesheet_was_compiled_with(): void
    {
        $this->assertSame('indigo', Theme::DEFAULT);
        $this->assertSame('#4338ca', Theme::PRESETS[Theme::DEFAULT][0]);
    }

    // ------------------------------------------------------------- the output

    #[Test]
    public function the_default_emits_nothing_at_all(): void
    {
        // The compiled stylesheet is already indigo, so choosing it must cost
        // an installation nothing.
        $this->assertSame('', app(Theme::class)->css());
    }

    #[Test]
    public function choosing_an_accent_redefines_every_token_that_carries_it(): void
    {
        $css = $this->choose('emerald')->css();

        foreach ([
            '--bs-primary', '--bs-primary-rgb', '--bs-primary-text-emphasis',
            '--bs-link-color', '--bs-focus-ring-color',
            '--is-accent-text', '--is-control-checked', '--is-nav-active-color', '--is-chart-1',
        ] as $token) {
            $this->assertStringContainsString($token.':', $css, "{$token} was left behind");
        }

        // A half-applied accent — buttons change, checkboxes do not — would be
        // worse than no accent at all.
        $this->assertStringContainsString('.btn-primary', $css);
        $this->assertStringContainsString('.btn-outline-primary', $css);
        $this->assertStringContainsString('.badge-soft-primary', $css);
    }

    #[Test]
    public function the_dark_block_uses_tints_rather_than_the_flat_accent(): void
    {
        $css = $this->choose('emerald')->css();

        [$dark] = explode('.btn-primary', explode('[data-bs-theme="dark"]', $css)[1]);

        // Flat brand colour on a dark panel is the failure that has bitten this
        // project four times; the dark half must not simply repeat the base.
        $this->assertStringNotContainsString('#047857', $dark);
        $this->assertStringContainsString('--is-accent-text: #75b5a3', $dark);
    }

    #[Test]
    public function an_unknown_accent_falls_back_rather_than_breaking_the_page(): void
    {
        $theme = $this->choose('chartreuse');

        $this->assertSame('indigo', $theme->current());
        $this->assertSame('', $theme->css());
    }

    // ------------------------------------------------------------- the screen

    #[Test]
    public function the_settings_screen_offers_every_preset_as_a_swatch(): void
    {
        $html = $this->actingAs($this->user())->get('/settings')->assertOk()->getContent();

        foreach (Theme::PRESETS as $key => [$hex, $label]) {
            $this->assertStringContainsString('value="'.$key.'"', $html);
            $this->assertStringContainsString($hex, $html);
        }
    }

    #[Test]
    public function the_chosen_accent_reaches_the_page(): void
    {
        $this->choose('rose');

        $this->actingAs($this->user())
            ->get('/settings')
            ->assertOk()
            ->assertSee('--is-accent-text: #be123c', false);
    }

    #[Test]
    public function nothing_is_emitted_on_the_page_for_the_default(): void
    {
        $this->actingAs($this->user())
            ->get('/settings')
            ->assertOk()
            ->assertDontSee('--is-accent-text:', false);
    }

    #[Test]
    public function the_sign_in_screen_is_themed_too(): void
    {
        $this->choose('amber');

        // The guest layout is a separate file and has been forgotten before.
        $this->get('/login')->assertOk()->assertSee('--is-accent-text: #b45309', false);
    }

    #[Test]
    public function an_accent_outside_the_set_is_refused_by_the_form(): void
    {
        $this->actingAs($this->user())
            ->put('/settings', ['app_name' => 'Test Site', 'accent' => 'chartreuse'])
            ->assertSessionHasErrors('accent');
    }

    // --------------------------------------------------------------- helpers

    private function choose(string $accent): Theme
    {
        app(Settings::class)->set(['accent' => $accent]);
        app()->forgetInstance(Settings::class);

        return app(Theme::class);
    }

    /** @return array<int, float> */
    private function channels(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function tint(string $hex, float $amount): string
    {
        $mixed = array_map(fn ($c) => (int) round($c + (255 - $c) * $amount), $this->channels($hex));

        return sprintf('#%02x%02x%02x', ...$mixed);
    }

    private function luminance(string $hex): float
    {
        $parts = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $this->channels($hex));

        return 0.2126 * $parts[0] + 0.7152 * $parts[1] + 0.0722 * $parts[2];
    }

    private function ratio(string $a, string $b): float
    {
        $x = $this->luminance($a);
        $y = $this->luminance($b);

        return round((max($x, $y) + 0.05) / (min($x, $y) + 0.05), 2);
    }

    private function user(): AccentTestUser
    {
        return AccentTestUser::query()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'hashed',
        ]);
    }
}

/** A User model that exists only for these tests. */
class AccentTestUser extends Authenticatable
{
    protected $table = 'accent_users';

    protected $guarded = [];
}
