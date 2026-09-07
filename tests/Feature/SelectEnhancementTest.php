<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SelectEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', SelectTestUser::class);
    }

    protected function defineRoutes($router): void
    {
        $router->get('books', fn () => 'books')->name('books.index');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('select_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    // ------------------------------------------------------------ the script

    #[Test]
    public function the_script_is_shipped_with_the_other_assets(): void
    {
        // Committed to resources/dist, like Bootstrap: the framework runs
        // without anybody installing npm.
        $this->assertFileExists(dirname(__DIR__, 2).'/resources/dist/tom-select.min.js');
    }

    #[Test]
    public function the_layout_loads_it_before_our_own_script(): void
    {
        $html = $this->actingAs($this->user())->get('/settings')->assertOk()->getContent();

        $tomSelect = strpos($html, 'tom-select.min.js');
        $ours = strpos($html, 'isproject.js');

        $this->assertNotFalse($tomSelect);
        $this->assertLessThan($ours, $tomSelect, 'Tom Select must be defined before our script looks for it.');
    }

    #[Test]
    public function it_can_be_switched_off_entirely(): void
    {
        config(['isproject.select.enabled' => false]);

        $this->actingAs($this->user())
            ->get('/settings')
            ->assertOk()
            ->assertDontSee('tom-select.min.js')
            // Ours still loads: it checks for TomSelect and leaves plain
            // selects alone when it is absent.
            ->assertSee('isproject.js');
    }

    #[Test]
    public function the_threshold_reaches_the_page(): void
    {
        config(['isproject.select.threshold' => 25]);

        $this->actingAs($this->user())
            ->get('/settings')
            ->assertOk()
            ->assertSee('data-is-select-threshold="25"', false);
    }

    #[Test]
    public function the_sign_in_screen_gets_it_too(): void
    {
        // The guest layout is a separate file; it has been forgotten before.
        $this->get('/login')->assertOk()->assertSee('data-is-select-threshold', false);
    }

    // ------------------------------------------------------- what it applies to

    #[Test]
    public function the_menu_form_picks_routes_from_a_select_rather_than_a_text_box(): void
    {
        $html = $this->actingAs($this->user())->get('/menu/create')->assertOk()->getContent();

        // It used to be an <input list="..."> against a datalist, which let
        // somebody type a route that does not exist and only find out on save.
        $this->assertStringNotContainsString('<datalist', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*name="route_name"/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*name="permission"/', $html);
    }

    #[Test]
    public function those_selects_are_marked_for_enhancement_whatever_their_length(): void
    {
        $html = $this->actingAs($this->user())->get('/menu/create')->assertOk()->getContent();

        // A fresh application may have fewer routes than the threshold, and a
        // route picker is the one place a search box always earns its keep.
        $this->assertMatchesRegularExpression('/<select[^>]*name="route_name"[^>]*data-is-select/s', $html);
    }

    #[Test]
    public function the_route_select_offers_the_routes_that_validation_accepts(): void
    {
        $html = $this->actingAs($this->user())->get('/menu/create')->assertOk()->getContent();

        // The same list Rule::in checks against, so the form cannot offer
        // something the controller would then refuse.
        $this->assertStringContainsString('books.index', $html);
    }

    #[Test]
    public function a_route_chosen_from_the_select_still_saves(): void
    {
        $this->actingAs($this->user())
            ->post('/menu', [
                'type' => 'route',
                'label' => 'Books',
                'route_name' => 'books.index',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('isproject_menu_items', ['route_name' => 'books.index']);
    }

    #[Test]
    public function the_timezone_setting_is_long_enough_to_be_enhanced_on_its_own(): void
    {
        $html = $this->actingAs($this->user())->get('/settings')->assertOk()->getContent();

        preg_match('/<select[^>]*id="timezone".*?<\/select>/s', $html, $matches);

        $this->assertNotEmpty($matches, 'The timezone select was not rendered.');

        // Nothing marks it: it qualifies by being enormous, which is the rule
        // that has to work for generated modules nobody annotated.
        $this->assertGreaterThan(
            (int) config('isproject.select.threshold', 8),
            substr_count($matches[0], '<option'),
        );
        $this->assertStringNotContainsString('data-is-select', $matches[0]);
    }

    #[Test]
    public function a_short_select_is_left_alone(): void
    {
        $html = $this->actingAs($this->user())->get('/menu/create')->assertOk()->getContent();

        preg_match('/<select[^>]*id="type".*?<\/select>/s', $html, $matches);

        // Four options. A search box over four things is noise.
        $this->assertLessThanOrEqual(
            (int) config('isproject.select.threshold', 8),
            substr_count($matches[0], '<option'),
        );
        $this->assertStringNotContainsString('data-is-select', $matches[0]);
    }

    // --------------------------------------------------------------- helpers

    private function user(): SelectTestUser
    {
        return SelectTestUser::query()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'hashed',
        ]);
    }
}

/** A User model that exists only for these tests. */
class SelectTestUser extends Authenticatable
{
    protected $table = 'select_users';

    protected $guarded = [];
}
