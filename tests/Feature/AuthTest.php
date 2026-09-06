<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use IsProject\Framework\Models\SocialAccount;
use IsProject\Framework\Support\AuthOptions;
use IsProject\Framework\Support\GoogleProvider;
use IsProject\Framework\Support\Settings;
use IsProject\Framework\Support\SettingsSchema;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', AuthTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('auth_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    // ------------------------------------------------------ email + password

    #[Test]
    public function the_sign_in_screen_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    #[Test]
    public function a_user_can_sign_in(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function a_wrong_password_and_an_unknown_address_look_identical(): void
    {
        $this->user();

        $wrong = $this->post('/login', ['email' => 'someone@example.test', 'password' => 'nope']);
        $unknown = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'nope']);

        // Different messages here would turn the form into a way of testing
        // which addresses have accounts.
        $this->assertSame(
            $wrong->getSession()->get('errors')->first('email'),
            $unknown->getSession()->get('errors')->first('email'),
        );

        $this->assertGuest();
    }

    #[Test]
    public function repeated_failures_are_throttled(): void
    {
        $user = $this->user();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $this->assertStringContainsString(
            'Too many sign-in attempts',
            (string) $response->getSession()->get('errors')->first('email')
        );
    }

    #[Test]
    public function signing_out_ends_the_session(): void
    {
        $this->actingAs($this->user())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    // ------------------------------------------------------------- profile

    #[Test]
    public function a_user_can_change_their_own_details(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/profile', ['name' => 'Renamed', 'email' => $user->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $user->fresh()->name);
    }

    #[Test]
    public function changing_a_password_requires_the_current_one(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'not-the-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'secret-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));
    }

    // -------------------------------------------------------- registration

    #[Test]
    public function registration_is_closed_unless_switched_on(): void
    {
        $this->get('/register')->assertNotFound();

        // Not just the form: a bookmarked POST must not create an account
        // after registration has been turned off.
        $this->post('/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertNotFound();

        $this->assertDatabaseMissing('auth_users', ['email' => 'sneaky@example.test']);
    }

    #[Test]
    public function registration_works_once_switched_on(): void
    {
        $this->enable(['auth_registration_enabled' => true]);

        $this->get('/register')->assertOk();

        $this->post('/register', [
            'name' => 'Aisha',
            'email' => 'aisha@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('auth_users', ['email' => 'aisha@example.test']);
    }

    // -------------------------------------------------- the Google toggle

    #[Test]
    public function google_is_not_configured_without_credentials(): void
    {
        $this->assertFalse(AuthOptions::googleConfigured());
        $this->assertFalse(AuthOptions::googleEnabled());
    }

    #[Test]
    public function the_settings_screen_reports_what_is_missing(): void
    {
        $missing = app(SettingsSchema::class)->unmetRequirements('auth_google_enabled');

        $this->assertSame(['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'], array_values($missing));

        $this->configureGoogle();
        app()->forgetInstance(SettingsSchema::class);

        $this->assertSame([], app(SettingsSchema::class)->unmetRequirements('auth_google_enabled'));
    }

    #[Test]
    public function it_refuses_to_enable_google_without_credentials(): void
    {
        // The disabled checkbox in the form is a courtesy; this is the control.
        $this->actingAs($this->user())
            ->put('/settings', ['app_name' => 'Portal', 'auth_google_enabled' => '1'])
            ->assertSessionHasErrors('auth_google_enabled');

        $this->assertFalse((bool) app(Settings::class)->get('auth_google_enabled'));
    }

    #[Test]
    public function it_allows_enabling_google_once_credentials_exist(): void
    {
        $this->configureGoogle();

        $this->actingAs($this->user())
            ->put('/settings', ['app_name' => 'Portal', 'auth_google_enabled' => '1'])
            ->assertSessionHasNoErrors();

        app()->forgetInstance(Settings::class);

        $this->assertTrue((bool) app(Settings::class)->get('auth_google_enabled'));
    }

    #[Test]
    public function the_sign_in_screen_only_offers_google_when_it_is_usable(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Continue with Google');

        $this->configureGoogle();
        $this->enable(['auth_google_enabled' => true]);

        $this->get('/login')->assertOk()->assertSee('Continue with Google');
    }

    #[Test]
    public function the_google_endpoints_refuse_while_the_feature_is_off(): void
    {
        $this->get('/auth/google/redirect')->assertRedirect('/login');
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/login');
    }

    // ---------------------------------------------------- the Google flow

    #[Test]
    public function the_redirect_carries_a_state_that_the_callback_demands_back(): void
    {
        $this->configureGoogle();
        $this->enable(['auth_google_enabled' => true]);

        $response = $this->get('/auth/google/redirect');
        $response->assertRedirectContains('accounts.google.com');

        $url = $response->headers->get('Location');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertNotEmpty($query['state']);

        // A callback that does not carry that exact value is not the
        // continuation of a flow this session started.
        $this->get('/auth/google/callback?code=abc&state=something-else')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function it_refuses_a_google_account_whose_email_is_unverified(): void
    {
        $this->fakeGoogle(['email_verified' => false]);

        $this->assertGuest();
        $this->callbackWithValidState()->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function it_signs_in_and_links_an_existing_account(): void
    {
        $user = $this->user('known@example.test');
        $this->fakeGoogle(['email' => 'known@example.test']);

        $this->callbackWithValidState()->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('isproject_social_accounts', [
            'provider' => 'google',
            'provider_id' => 'google-sub-1',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_refuses_an_unknown_google_account_unless_creation_is_allowed(): void
    {
        $this->fakeGoogle(['email' => 'stranger@example.test']);

        $this->callbackWithValidState()->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('auth_users', ['email' => 'stranger@example.test']);
    }

    #[Test]
    public function it_creates_an_account_when_that_is_switched_on(): void
    {
        $this->fakeGoogle(['email' => 'stranger@example.test', 'name' => 'A Stranger']);
        $this->enable(['auth_google_register' => true]);

        $this->callbackWithValidState()->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('auth_users', ['email' => 'stranger@example.test', 'name' => 'A Stranger']);
    }

    #[Test]
    public function a_second_sign_in_reuses_the_link_rather_than_making_another(): void
    {
        $this->user('known@example.test');
        $this->fakeGoogle(['email' => 'known@example.test']);

        $this->callbackWithValidState();
        $this->post('/logout');
        $this->callbackWithValidState();

        $this->assertSame(1, SocialAccount::query()->count());
    }

    // ------------------------------------------------------------ helpers

    private function user(string $email = 'someone@example.test'): AuthTestUser
    {
        return AuthTestUser::query()->create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make('secret-password'),
        ]);
    }

    private function configureGoogle(): void
    {
        config([
            'isproject.auth.google.client_id' => 'test-client-id',
            'isproject.auth.google.client_secret' => 'test-client-secret',
        ]);
    }

    /** @param  array<string, mixed>  $values */
    private function enable(array $values): void
    {
        app(Settings::class)->set($values);
        app()->forgetInstance(Settings::class);
    }

    /**
     * Stand in for Google: a token endpoint that hands back an access token and
     * a userinfo endpoint returning the profile under test.
     *
     * @param  array<string, mixed>  $profile
     */
    private function fakeGoogle(array $profile = []): void
    {
        $this->configureGoogle();
        $this->enable(['auth_google_enabled' => true]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response(array_merge([
                'sub' => 'google-sub-1',
                'email' => 'known@example.test',
                'email_verified' => true,
                'name' => 'Known Person',
            ], $profile)),
        ]);
    }

    /** Walk the real redirect first, so the session holds a state to match. */
    private function callbackWithValidState(): TestResponse
    {
        $this->get('/auth/google/redirect');

        $state = session(GoogleProvider::STATE_KEY);

        return $this->get("/auth/google/callback?code=test-code&state={$state}");
    }
}

/** A User model that exists only for these tests. */
class AuthTestUser extends Authenticatable
{
    protected $table = 'auth_users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}
