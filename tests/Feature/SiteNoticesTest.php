<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use IsProject\Framework\Support\Settings;
use IsProject\Framework\Support\SiteNotices;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SiteNoticesTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Without this the settings PUT below redirects to the sign-in screen
        // and never reaches validation, so the assertions would pass for the
        // wrong reason. Authorisation has its own tests.
        $app['config']->set('isproject.settings.middleware', ['web']);
    }

    // ------------------------------------------------------------- ribbon

    #[Test]
    public function the_ribbon_is_off_until_it_is_switched_on(): void
    {
        $this->assertNull($this->notices()->ribbon());

        $this->set(['ribbon_enabled' => true, 'ribbon_text' => 'Beta']);

        $this->assertSame('Beta', $this->notices()->ribbon()['text']);
    }

    #[Test]
    public function a_ribbon_with_no_text_is_not_shown(): void
    {
        // Switched on but blank is a half-finished edit, not a notice.
        $this->set(['ribbon_enabled' => true, 'ribbon_text' => '   ']);

        $this->assertNull($this->notices()->ribbon());
    }

    #[Test]
    public function an_external_ribbon_link_opens_in_a_new_tab_and_a_relative_one_does_not(): void
    {
        $this->set(['ribbon_enabled' => true, 'ribbon_text' => 'Docs', 'ribbon_url' => 'https://example.edu']);
        $this->assertTrue($this->notices()->ribbon()['external']);

        $this->set(['ribbon_url' => '/faq']);
        $this->assertFalse($this->notices()->ribbon()['external']);
    }

    // ------------------------------------------------------- announcement

    #[Test]
    public function an_announcement_shows_when_enabled_and_written(): void
    {
        $this->assertNull($this->notices()->announcement());

        $this->set(['announcement_enabled' => true, 'announcement_text' => 'Registration closes Friday.']);

        $this->assertSame('Registration closes Friday.', $this->notices()->announcement()['text']);
    }

    #[Test]
    public function an_end_date_silences_it_without_anyone_switching_it_off(): void
    {
        $this->set([
            'announcement_enabled' => true,
            'announcement_text' => 'Open day on Saturday.',
            'announcement_until' => now()->addDay()->toDateString(),
        ]);
        $this->assertNotNull($this->notices()->announcement());

        // Inclusive of the day itself: "until the 3rd" means through the 3rd.
        $this->set(['announcement_until' => now()->toDateString()]);
        $this->assertNotNull($this->notices()->announcement());

        $this->set(['announcement_until' => now()->subDay()->toDateString()]);
        $this->assertNull($this->notices()->announcement());
    }

    #[Test]
    public function an_unreadable_end_date_does_not_silence_it(): void
    {
        $this->set([
            'announcement_enabled' => true,
            'announcement_text' => 'Important.',
            'announcement_until' => 'not a date',
        ]);

        // Failing open here: a typo in a date must not quietly hide a notice
        // somebody believed they had published.
        $this->assertNotNull($this->notices()->announcement());
    }

    #[Test]
    public function the_dismissal_id_changes_with_the_message(): void
    {
        $this->set(['announcement_enabled' => true, 'announcement_text' => 'First message.']);
        $first = $this->notices()->announcement()['id'];

        $this->set(['announcement_text' => 'Second message.']);
        $second = $this->notices()->announcement()['id'];

        // This is what makes a new announcement appear at once for someone who
        // dismissed the previous one a minute ago.
        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function the_dismissal_window_is_clamped_to_something_sensible(): void
    {
        $this->set(['announcement_enabled' => true, 'announcement_text' => 'Hello.']);

        $this->assertSame(1, $this->notices()->announcement()['hours']);

        // Zero would make the close button do nothing at all.
        $this->set(['announcement_dismiss_hours' => 0]);
        $this->assertSame(1, $this->notices()->announcement()['hours']);

        // And a decade would make it permanent.
        $this->set(['announcement_dismiss_hours' => 99999]);
        $this->assertSame(720, $this->notices()->announcement()['hours']);
    }

    // ---------------------------------------------------------------- urls

    #[Test]
    public function a_script_url_never_reaches_an_href(): void
    {
        $this->set([
            'ribbon_enabled' => true,
            'ribbon_text' => 'Click me',
            'ribbon_url' => 'javascript:alert(document.cookie)',
            'announcement_enabled' => true,
            'announcement_text' => 'Click me too',
            'announcement_url' => 'JavaScript:alert(1)',
        ]);

        // With the default open settings gate any signed-in user can set these,
        // so a link that runs script for every visitor would be stored XSS.
        // The notice still shows; it simply is not a link.
        $this->assertNull($this->notices()->ribbon()['url']);
        $this->assertNull($this->notices()->announcement()['url']);
    }

    #[Test]
    public function other_schemes_are_refused_too(): void
    {
        foreach (['data:text/html;base64,PHNjcmlwdD4=', 'vbscript:msgbox', 'file:///etc/passwd', 'mailto:a@b.c'] as $url) {
            $this->set(['ribbon_enabled' => true, 'ribbon_text' => 'x', 'ribbon_url' => $url]);

            $this->assertNull($this->notices()->ribbon()['url'], "[{$url}] should not become an href");
        }
    }

    #[Test]
    public function the_settings_screen_rejects_a_script_url_as_well(): void
    {
        // Belt and braces: the model layer refuses to render it, and the form
        // refuses to store it in the first place.
        $this->put('/settings', [
            'app_name' => 'Portal',
            'ribbon_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('ribbon_url');

        $this->put('/settings', [
            'app_name' => 'Portal',
            'announcement_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('announcement_url');

        $this->put('/settings', [
            'app_name' => 'Portal',
            'ribbon_url' => 'https://example.edu/ok',
        ])->assertSessionHasNoErrors();
    }

    // -------------------------------------------------------------- render

    #[Test]
    public function the_layout_renders_both_notices(): void
    {
        $this->set([
            'ribbon_enabled' => true,
            'ribbon_text' => 'Beta',
            'announcement_enabled' => true,
            'announcement_text' => 'Registration closes Friday.',
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Beta')
            ->assertSee('Registration closes Friday.')
            ->assertSee('data-is-announcement-id', false);
    }

    #[Test]
    public function announcement_text_is_escaped(): void
    {
        $this->set([
            'announcement_enabled' => true,
            'announcement_text' => '<script>alert(1)</script>',
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    private function notices(): SiteNotices
    {
        return new SiteNotices;
    }

    /** @param  array<string, mixed>  $values */
    private function set(array $values): void
    {
        app(Settings::class)->set($values);
        app()->forgetInstance(Settings::class);
    }
}
