<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Models\Activity;
use IsProject\Framework\Support\ActivityLogger;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', ActivityTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('activity_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    // ------------------------------------------------------- what gets logged

    #[Test]
    public function signing_in_is_recorded_against_the_person(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect();

        $activity = Activity::query()->where('event', Activity::LOGIN)->firstOrFail();

        $this->assertSame($user->id, (int) $activity->user_id);
        $this->assertSame('Administrator', $activity->user_label);
        $this->assertNotNull($activity->ip_address);
    }

    #[Test]
    public function signing_out_is_recorded(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/logout');

        // The user comes off the event, not off auth(): the session has already
        // gone by the time Logout fires.
        $this->assertSame(
            $user->id,
            (int) Activity::query()->where('event', Activity::LOGOUT)->value('user_id'),
        );
    }

    #[Test]
    public function a_failed_sign_in_is_recorded_with_the_address_that_was_tried(): void
    {
        $this->user();

        $this->post('/login', ['email' => 'intruder@example.test', 'password' => 'guessing']);

        $activity = Activity::query()->where('event', Activity::LOGIN_FAILED)->firstOrFail();

        $this->assertSame('intruder@example.test', $activity->properties['email']);
        $this->assertFalse($activity->properties['account_exists']);
    }

    #[Test]
    public function a_failed_sign_in_is_not_attributed_to_the_account_it_targeted(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $activity = Activity::query()->where('event', Activity::LOGIN_FAILED)->firstOrFail();

        // "What has this person done" must not start listing things done *to*
        // them by somebody else. The account is named in properties instead.
        $this->assertNull($activity->user_id);
        $this->assertTrue($activity->properties['account_exists']);
    }

    #[Test]
    public function the_password_never_reaches_the_table(): void
    {
        $this->post('/login', ['email' => 'someone@example.test', 'password' => 'hunter2-in-the-clear']);

        $rows = Activity::query()->get()->map(fn (Activity $row) => json_encode($row->toArray()))->implode(' ');

        $this->assertStringNotContainsString('hunter2-in-the-clear', $rows);
    }

    #[Test]
    public function a_lockout_is_recorded(): void
    {
        event(new Lockout(Request::create('/login', 'POST', ['email' => 'target@example.test'])));

        $this->assertSame(
            'target@example.test',
            Activity::query()->where('event', Activity::LOCKOUT)->value('properties')['email'] ?? null,
        );
    }

    #[Test]
    public function changing_a_password_is_recorded(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'secret-password',
            'password' => 'a-much-longer-new-password',
            'password_confirmation' => 'a-much-longer-new-password',
        ])->assertSessionHasNoErrors();

        // It is redacted in the audit trail, so without this there would be no
        // record anywhere that somebody's password changed.
        $this->assertTrue(Activity::query()->where('event', Activity::PASSWORD_CHANGED)->exists());
    }

    // ------------------------------------------------------------- the logger

    #[Test]
    public function an_application_can_log_its_own_events(): void
    {
        $user = $this->user();

        $this->actingAs($user);
        isproject_activity('invoice.exported', 'Exported the March invoices', ['count' => 42]);

        $activity = Activity::query()->where('event', 'invoice.exported')->firstOrFail();

        $this->assertSame('Exported the March invoices', $activity->description);
        $this->assertSame(42, $activity->properties['count']);
        $this->assertSame('Invoice.exported', $activity->label());
    }

    #[Test]
    public function secrets_are_dropped_from_properties_whoever_passes_them(): void
    {
        isproject_activity('thing.done', 'Did a thing', [
            'password' => 'no',
            'api_key' => 'no',
            'reset_token' => 'no',
            'keep' => 'yes',
        ]);

        $properties = Activity::query()->where('event', 'thing.done')->value('properties');

        $this->assertSame(['keep' => 'yes'], $properties);
    }

    #[Test]
    public function logging_can_be_switched_off_entirely(): void
    {
        config(['isproject.activity.enabled' => false]);

        isproject_activity('thing.done', 'Did a thing');

        $this->assertSame(0, Activity::query()->count());
    }

    #[Test]
    public function a_noisy_event_can_be_silenced_by_config(): void
    {
        config(['isproject.activity.ignored_events' => ['logout']]);

        isproject_activity(Activity::LOGOUT, 'Signed out');
        isproject_activity(Activity::LOGIN, 'Signed in');

        $this->assertSame([Activity::LOGIN], Activity::query()->pluck('event')->all());
    }

    #[Test]
    public function a_seeder_can_suspend_logging(): void
    {
        app(ActivityLogger::class)->withoutLogging(function () {
            isproject_activity('bulk.import', 'Imported a row');
            isproject_activity('bulk.import', 'Imported a row');
        });

        isproject_activity('thing.done', 'Did a thing');

        $this->assertSame(1, Activity::query()->count());
    }

    #[Test]
    public function a_broken_log_never_breaks_the_thing_it_watches(): void
    {
        Schema::drop('isproject_activities');

        // The whole point of the try/catch in the logger: signing in must work
        // even when the log cannot be written.
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    // -------------------------------------------------------------- the screen

    #[Test]
    public function the_screen_lists_what_happened(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        isproject_activity(Activity::LOGIN, 'Signed in');

        $this->actingAs($user)
            ->get('/activity')
            ->assertOk()
            ->assertSee('Signed in')
            ->assertSee('Administrator');
    }

    #[Test]
    public function the_security_filter_shows_only_the_events_worth_reviewing(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        isproject_activity(Activity::LOGIN, 'Signed in');
        isproject_activity(Activity::LOGIN_FAILED, 'Sign-in failed');

        $response = $this->actingAs($user)->get('/activity?security=1')->assertOk();

        $response->assertSee('Sign-in failed');
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'badge-soft-warning'),
        );
    }

    #[Test]
    public function the_log_can_be_filtered_by_event_and_person(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        isproject_activity(Activity::LOGIN, 'Signed in');
        isproject_activity('invoice.exported', 'Exported');

        $this->actingAs($user)
            ->get('/activity?event=invoice.exported')
            ->assertOk()
            ->assertSee('Exported')
            ->assertDontSee('badge-soft-success', false);
    }

    #[Test]
    public function an_entry_can_be_opened(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $activity = isproject_activity('invoice.exported', 'Exported the March invoices', ['count' => 42]);

        $this->actingAs($user)
            ->get("/activity/{$activity->id}")
            ->assertOk()
            ->assertSee('Exported the March invoices')
            ->assertSee('42');
    }

    #[Test]
    public function the_screen_needs_a_signed_in_reader(): void
    {
        $this->get('/activity')->assertRedirect('/login');
    }

    // --------------------------------------------------------------- pruning

    #[Test]
    public function pruning_removes_what_is_older_than_the_retention(): void
    {
        isproject_activity('old.thing', 'Long ago');
        Activity::query()->update(['created_at' => now()->subDays(400)]);
        isproject_activity('new.thing', 'Just now');

        $this->artisan('isproject:activity-prune', ['--days' => 30])->assertSuccessful();

        $this->assertSame(['new.thing'], Activity::query()->pluck('event')->all());
    }

    #[Test]
    public function pretending_deletes_nothing(): void
    {
        isproject_activity('old.thing', 'Long ago');
        Activity::query()->update(['created_at' => now()->subDays(400)]);

        $this->artisan('isproject:activity-prune', ['--days' => 30, '--pretend' => true])->assertSuccessful();

        $this->assertSame(1, Activity::query()->count());
    }

    #[Test]
    public function a_retention_of_zero_is_refused_rather_than_deleting_everything(): void
    {
        isproject_activity('thing.done', 'Did a thing');

        $this->artisan('isproject:activity-prune', ['--days' => 0])->assertFailed();

        $this->assertSame(1, Activity::query()->count());
    }

    // --------------------------------------------------------------- helpers

    private function user(): ActivityTestUser
    {
        return ActivityTestUser::query()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => Hash::make('secret-password'),
        ]);
    }
}

/** A User model that exists only for these tests. */
class ActivityTestUser extends Authenticatable
{
    protected $table = 'activity_users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}
