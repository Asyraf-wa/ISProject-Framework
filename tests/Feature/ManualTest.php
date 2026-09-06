<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Support\Manual;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ManualTest extends TestCase
{
    use RefreshDatabase;

    private string $extra = 'build/manual';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', ManualTestUser::class);
        $app['config']->set('isproject.manual.paths', [base_path($this->extra)]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('manual_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        File::ensureDirectoryExists(base_path($this->extra));
    }

    // ------------------------------------------------------- what ships

    #[Test]
    public function the_bundled_chapters_are_all_discovered(): void
    {
        $chapters = app(Manual::class)->chapters();

        $this->assertGreaterThanOrEqual(8, $chapters->count());

        // The one the whole manual points at.
        $this->assertTrue($chapters->contains('slug', 'first-module'));
    }

    #[Test]
    public function every_bundled_chapter_renders_without_leaving_a_placeholder(): void
    {
        $manual = app(Manual::class);

        foreach ($manual->chapters() as $chapter) {
            $rendered = $manual->find($chapter['slug']);

            $this->assertNotNull($rendered, "{$chapter['slug']} did not render");
            $this->assertNotSame('', trim($rendered['html']));

            // An unresolved %%token%% on screen is a typo nobody would spot
            // until a student read it.
            $this->assertDoesNotMatchRegularExpression('/%%[a-zA-Z]+%%/', $rendered['html']);
        }
    }

    #[Test]
    public function every_chapter_declares_a_title_and_a_summary(): void
    {
        foreach (app(Manual::class)->chapters() as $chapter) {
            $this->assertNotSame('', $chapter['title'], "{$chapter['slug']} has no title");
            $this->assertNotSame('', $chapter['summary'], "{$chapter['slug']} has no summary");
        }
    }

    #[Test]
    public function every_internal_link_in_the_manual_points_at_a_real_chapter(): void
    {
        $manual = app(Manual::class);
        $slugs = $manual->chapters()->pluck('slug')->all();

        foreach ($manual->chapters() as $chapter) {
            preg_match_all('/href="([^"#][^"]*)"/', $manual->find($chapter['slug'])['html'], $matches);

            foreach ($matches[1] as $href) {
                if (str_starts_with($href, 'http') || str_starts_with($href, '/')) {
                    continue;
                }

                // A cross-reference to a chapter that was renamed would be a
                // 404 nobody notices until a reader follows it.
                $this->assertContains($href, $slugs, "{$chapter['slug']} links to missing chapter [{$href}]");
            }
        }
    }

    // ------------------------------------------------------------ rendering

    #[Test]
    public function the_chapters_own_h1_is_dropped_because_the_page_shows_it(): void
    {
        $this->write('500-sample.md', "---\ntitle: Sample\n---\n\n# Sample\n\nBody text.\n");

        $html = app(Manual::class)->find('sample')['html'];

        $this->assertStringNotContainsString('<h1>', $html);
        $this->assertStringContainsString('Body text.', $html);
    }

    #[Test]
    public function tables_are_rendered_as_tables(): void
    {
        $this->write('500-sample.md', "---\ntitle: Sample\n---\n\n| A | B |\n|---|---|\n| 1 | 2 |\n");

        // Plain CommonMark renders a pipe table as a paragraph of pipes, which
        // would quietly wreck most of the manual.
        $this->assertStringContainsString('<table>', app(Manual::class)->find('sample')['html']);
    }

    #[Test]
    public function h2s_get_ids_and_appear_in_the_contents(): void
    {
        $this->write('500-sample.md', "---\ntitle: Sample\n---\n\n## First part\n\nText.\n\n## Second part\n");

        $chapter = app(Manual::class)->find('sample');

        $this->assertStringContainsString('<h2 id="first-part">', $chapter['html']);
        $this->assertSame(
            [['id' => 'first-part', 'title' => 'First part'], ['id' => 'second-part', 'title' => 'Second part']],
            $chapter['contents'],
        );
    }

    #[Test]
    public function a_github_style_alert_becomes_a_callout(): void
    {
        $this->write('500-sample.md', "---\ntitle: Sample\n---\n\n> [!WARNING]\n> Mind the gap.\n");

        $html = app(Manual::class)->find('sample')['html'];

        $this->assertStringContainsString('is-callout is-callout-warning', $html);
        $this->assertStringContainsString('Mind the gap.', $html);
        $this->assertStringNotContainsString('[!WARNING]', $html);
    }

    #[Test]
    public function an_ordinary_quotation_stays_a_quotation(): void
    {
        $this->write('500-sample.md', "---\ntitle: Sample\n---\n\n> Just a quote.\n");

        $html = app(Manual::class)->find('sample')['html'];

        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringNotContainsString('is-callout', $html);
    }

    #[Test]
    public function raw_html_in_a_chapter_is_escaped(): void
    {
        $this->write('500-sample.md', "---\ntitle: Sample\n---\n\n<script>alert(1)</script>\n");

        $html = app(Manual::class)->find('sample')['html'];

        // These files are as trusted as a Blade view, but a manual directory
        // somebody made writable must not become a script tag.
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function placeholders_are_replaced_with_the_configured_paths(): void
    {
        config(['isproject.menu_admin.path' => 'navigation']);

        $this->write('500-sample.md', "---\ntitle: Sample\n---\n\nGo to %%menuPath%% now.\n");

        // The manual has to describe the system in front of the reader, not the
        // defaults it was written against.
        $this->assertStringContainsString('/navigation', app(Manual::class)->find('sample')['html']);
    }

    // ------------------------------------------------------------ discovery

    #[Test]
    public function file_names_give_the_order_and_the_slug(): void
    {
        $this->write('501-zebra.md', "---\ntitle: Zebra\n---\n\nZ\n");
        $this->write('500-antelope.md', "---\ntitle: Antelope\n---\n\nA\n");

        $slugs = app(Manual::class)->chapters()->pluck('slug');

        $this->assertLessThan($slugs->search('zebra'), $slugs->search('antelope'));
    }

    #[Test]
    public function a_file_without_a_number_is_ignored(): void
    {
        $this->write('notes.md', "---\ntitle: Notes\n---\n\nScratch.\n");

        $this->assertNull(app(Manual::class)->find('notes'));
    }

    #[Test]
    public function an_application_chapter_can_replace_a_bundled_one(): void
    {
        $this->write('020-first-module.md', "---\ntitle: Our own version\n---\n\nLocal.\n");

        // Later directories win, so a lecturer can rewrite a chapter for their
        // own course without forking the package.
        $this->assertSame('Our own version', app(Manual::class)->find('first-module')['title']);
    }

    #[Test]
    public function neighbours_walk_the_manual_in_order(): void
    {
        $manual = app(Manual::class);
        $slugs = $manual->chapters()->pluck('slug')->all();

        $this->assertNull($manual->neighbours($slugs[0])['previous']);
        $this->assertSame($slugs[1], $manual->neighbours($slugs[0])['next']['slug']);
        $this->assertNull($manual->neighbours(end($slugs))['next']);
    }

    // --------------------------------------------------------------- search

    #[Test]
    public function search_finds_a_chapter_and_shows_where(): void
    {
        $results = app(Manual::class)->search('archiving');

        $this->assertNotEmpty($results);
        $this->assertSame('archiving', $results[0]['slug']);
        $this->assertStringContainsStringIgnoringCase('archiv', $results[0]['snippet']);
    }

    #[Test]
    public function search_puts_the_chapter_that_says_it_most_first(): void
    {
        $this->write('500-once.md', "---\ntitle: Once\n---\n\nBadger.\n");
        $this->write('501-often.md', "---\ntitle: Often\n---\n\nBadger badger badger.\n");

        $results = app(Manual::class)->search('badger');

        $this->assertSame('often', $results[0]['slug']);
        $this->assertSame(3, $results[0]['hits']);
    }

    #[Test]
    public function search_ignores_case_and_an_empty_query(): void
    {
        $this->write('500-sample.md', "---\ntitle: Sample\n---\n\nCapybara.\n");

        $this->assertNotEmpty(app(Manual::class)->search('CAPYBARA'));
        $this->assertSame([], app(Manual::class)->search('   '));
    }

    #[Test]
    public function a_snippet_is_cut_around_the_match_not_from_the_top(): void
    {
        $filler = str_repeat('padding words here. ', 40);

        $this->write('500-sample.md', "---\ntitle: Sample\n---\n\n{$filler}\n\nThe wombat appears late.\n");

        // Collapsing whitespace before cutting would shift the window off the
        // match and show the opening of the file instead.
        $this->assertStringContainsString('wombat', app(Manual::class)->search('wombat')[0]['snippet']);
    }

    // -------------------------------------------------------------- screens

    #[Test]
    public function the_contents_page_lists_every_chapter(): void
    {
        $this->actingAs($this->user())
            ->get('/manual')
            ->assertOk()
            ->assertSee('Your first module')
            ->assertSee('Search the manual');
    }

    #[Test]
    public function a_chapter_renders(): void
    {
        $this->actingAs($this->user())
            ->get('/manual/first-module')
            ->assertOk()
            ->assertSee('isproject:crud Book');
    }

    #[Test]
    public function searching_from_the_screen_shows_matches(): void
    {
        $this->actingAs($this->user())
            ->get('/manual?q=permission')
            ->assertOk()
            ->assertSee('Users and roles');
    }

    #[Test]
    public function a_chapter_that_does_not_exist_is_a_404(): void
    {
        $this->actingAs($this->user())->get('/manual/no-such-chapter')->assertNotFound();
    }

    #[Test]
    public function a_slug_that_is_not_slug_shaped_never_reaches_the_lookup(): void
    {
        // The route constraint refuses it, so a traversal attempt is a 404 from
        // the router rather than something the chapter lookup has to defend.
        $this->actingAs($this->user())->get('/manual/..%2F..%2Fconfig')->assertNotFound();
    }

    #[Test]
    public function the_manual_needs_a_signed_in_reader_by_default(): void
    {
        $this->get('/manual')->assertRedirect('/login');
    }

    // --------------------------------------------------------------- helpers

    private function write(string $name, string $contents): void
    {
        File::put(base_path($this->extra.'/'.$name), $contents);

        // The chapter list is memoised for the request.
        app()->forgetInstance(Manual::class);
    }

    private function user(): ManualTestUser
    {
        return ManualTestUser::query()->create([
            'name' => 'Student',
            'email' => 'student@example.test',
            'password' => 'hashed',
        ]);
    }
}

/** A User model that exists only for these tests. */
class ManualTestUser extends Authenticatable
{
    protected $table = 'manual_users';

    protected $guarded = [];
}
