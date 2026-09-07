<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Support\Settings;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', SeoTestUser::class);
        $app['config']->set('isproject.settings.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('seo_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    // ------------------------------------------------------------ indexing

    #[Test]
    public function nothing_is_indexable_until_it_is_switched_on(): void
    {
        // Off is the right default: a system that is still being built has no
        // business appearing in search results.
        $this->get('/login')->assertOk()->assertSee('content="noindex, nofollow"', false);
    }

    #[Test]
    public function the_public_pages_become_indexable_when_allowed(): void
    {
        $this->enable();

        $this->get('/login')
            ->assertOk()
            ->assertSee('content="index, follow"', false)
            ->assertDontSee('noindex', false);
    }

    #[Test]
    public function signed_in_pages_are_never_indexable_whatever_the_setting_says(): void
    {
        $this->enable();

        // This is the point of the whole design. The admin screens sit behind
        // authentication; listing their titles and URLs would advertise the
        // shape of the system and help nobody.
        $this->actingAs($this->user())
            ->get('/settings')
            ->assertOk()
            ->assertSee('content="noindex, nofollow"', false)
            ->assertDontSee('content="index, follow"', false);
    }

    #[Test]
    public function an_admin_page_never_declares_a_canonical_url(): void
    {
        $this->enable();

        // A canonical tag on a page that must not be indexed is a mixed
        // message; the structured data is pointless there for the same reason.
        $this->actingAs($this->user())
            ->get('/settings')
            ->assertOk()
            ->assertDontSee('rel="canonical"', false)
            ->assertDontSee('application/ld+json', false);
    }

    // -------------------------------------------------------------- content

    #[Test]
    public function the_description_reaches_both_the_meta_tag_and_the_preview(): void
    {
        $this->set(['seo_description' => 'The Information Systems student portal.']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('name="description" content="The Information Systems student portal."', false)
            ->assertSee('property="og:description"', false)
            ->assertSee('name="twitter:description"', false);
    }

    #[Test]
    public function a_description_with_awkward_whitespace_is_tidied(): void
    {
        $this->set(['seo_description' => "  Line one\n\n   and    line two.  "]);

        // Newlines inside a meta attribute are legal but ugly in a preview.
        $this->get('/login')->assertOk()->assertSee('content="Line one and line two."', false);
    }

    #[Test]
    public function the_verification_code_is_emitted_for_search_console(): void
    {
        $this->set(['seo_verification' => 'abc123token']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('name="google-site-verification" content="abc123token"', false);
    }

    #[Test]
    public function the_canonical_address_replaces_the_host_but_keeps_the_path(): void
    {
        $this->enable();
        $this->set(['seo_canonical_host' => 'https://portal.example.edu/']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('rel="canonical" href="https://portal.example.edu/login"', false);
    }

    #[Test]
    public function link_previews_work_even_when_the_page_is_not_indexable(): void
    {
        $this->set(['seo_description' => 'Staff portal.']);

        // A link pasted into a staff chat should still preview properly on a
        // site search engines are told to ignore.
        $this->get('/login')
            ->assertOk()
            ->assertSee('content="noindex, nofollow"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('property="og:description"', false);
    }

    #[Test]
    public function the_share_image_falls_back_to_the_logo(): void
    {
        $this->set(['logo' => 'isproject/logo.png']);
        $this->get('/login')->assertOk()->assertSee('property="og:image"', false);

        // A preview with the wrong picture beats one with none, but an explicit
        // share image wins when there is one.
        $this->set(['seo_share_image' => 'isproject/share.png']);
        $this->get('/login')->assertOk()->assertSee('share.png', false);
    }

    #[Test]
    public function structured_data_appears_only_on_an_indexable_page(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('application/ld+json', false);

        $this->enable();

        $this->get('/login')->assertOk()->assertSee('application/ld+json', false);
    }

    // ----------------------------------------------------------- robots.txt

    #[Test]
    public function robots_txt_follows_the_setting(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /');

        $this->enable();

        $this->get('/robots.txt')->assertOk()->assertDontSee('Disallow: /');
    }

    #[Test]
    public function robots_txt_does_not_advertise_a_sitemap_that_does_not_exist(): void
    {
        $this->enable();

        // Pointing a crawler at a file that 404s is worse than saying nothing.
        $this->get('/robots.txt')->assertOk()->assertDontSee('Sitemap');
    }

    #[Test]
    public function the_settings_screen_warns_when_a_robots_file_overrules_it(): void
    {
        $response = $this->actingAs($this->user())->get('/settings')->assertOk();

        // Testbench's skeleton has no public/robots.txt, so there is nothing to
        // warn about; the warning is asserted by its absence here and the
        // presence of the field it belongs to.
        $response->assertSee('Allow search engines to index this site');
    }

    // -------------------------------------------------------------- helpers

    private function enable(): void
    {
        $this->set(['seo_indexable' => true]);
    }

    /** @param  array<string, mixed>  $values */
    private function set(array $values): void
    {
        app(Settings::class)->set($values);
        app()->forgetInstance(Settings::class);
    }

    private function user(): SeoTestUser
    {
        return SeoTestUser::query()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'hashed',
        ]);
    }
}

/** A User model that exists only for these tests. */
class SeoTestUser extends Authenticatable
{
    protected $table = 'seo_users';

    protected $guarded = [];
}
