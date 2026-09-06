<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Concerns\Auditable;
use IsProject\Framework\Models\Audit;
use IsProject\Framework\Support\AuditRecorder;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', AuditTestUser::class);
        $app['config']->set('isproject.audit.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('audit_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('audit_widgets', function ($table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2)->default(0);
            $table->string('api_token')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    // -------------------------------------------------------------- events

    #[Test]
    public function it_records_a_creation_with_every_attribute(): void
    {
        $widget = AuditWidget::query()->create(['name' => 'Blue Mug', 'price' => '19.90']);

        $audit = Audit::query()->sole();

        $this->assertSame(Audit::CREATED, $audit->event);
        $this->assertSame((string) $widget->id, $audit->auditable_id);
        $this->assertSame([], $audit->old_values);
        $this->assertSame('Blue Mug', $audit->new_values['name']);
    }

    #[Test]
    public function it_records_only_what_changed_on_an_update(): void
    {
        $widget = AuditWidget::query()->create(['name' => 'Blue Mug', 'price' => '19.90']);
        Audit::query()->delete();

        $widget->update(['price' => '24.50']);

        $audit = Audit::query()->sole();

        // "price changed" is far less use than "19.90 became 24.50", and the
        // untouched name has no business being in the record either.
        $this->assertSame(['price' => '19.90'], $audit->old_values);
        $this->assertSame(['price' => '24.50'], $audit->new_values);
        $this->assertSame(['price'], $audit->changedKeys());
    }

    #[Test]
    public function saving_without_changing_anything_records_nothing(): void
    {
        $widget = AuditWidget::query()->create(['name' => 'Blue Mug']);
        Audit::query()->delete();

        $widget->name = 'Blue Mug';
        $widget->save();

        $this->assertSame(0, Audit::query()->count());
    }

    #[Test]
    public function it_records_deletes_and_restores(): void
    {
        $widget = AuditWidget::query()->create(['name' => 'Blue Mug']);
        Audit::query()->delete();

        $widget->delete();
        $widget->restore();

        $this->assertSame(
            [Audit::DELETED, Audit::RESTORED],
            Audit::query()->orderBy('id')->pluck('event')->all()
        );
    }

    // ------------------------------------------------------------ redaction

    #[Test]
    public function it_never_writes_down_a_secret(): void
    {
        $widget = AuditWidget::query()->create(['name' => 'Blue Mug', 'api_token' => 'sk-live-123456']);

        $audit = Audit::query()->sole();

        // The change is recorded; the value is not. Both halves matter.
        $this->assertArrayHasKey('api_token', $audit->new_values);
        $this->assertSame('••••••••', $audit->new_values['api_token']);
        $this->assertStringNotContainsString('sk-live-123456', json_encode($audit->getAttributes()));

        $widget->update(['api_token' => 'sk-live-abcdef']);

        $this->assertStringNotContainsString(
            'sk-live-abcdef',
            json_encode(Audit::query()->latest('id')->firstOrFail()->getAttributes())
        );
    }

    #[Test]
    public function a_password_is_recorded_as_changed_but_never_quoted(): void
    {
        $user = AuditTestUser::query()->create([
            'name' => 'Aisha', 'email' => 'aisha@example.test', 'password' => 'hash-one',
        ]);
        Audit::query()->delete();

        $user->update(['password' => 'hash-two']);

        $audit = Audit::query()->sole();

        $this->assertSame('••••••••', $audit->new_values['password']);
        $this->assertStringNotContainsString('hash-two', json_encode($audit->getAttributes()));
    }

    #[Test]
    public function timestamps_are_left_out_as_noise(): void
    {
        $widget = AuditWidget::query()->create(['name' => 'Blue Mug']);
        Audit::query()->delete();

        $widget->update(['name' => 'Red Mug']);

        $audit = Audit::query()->sole();

        $this->assertArrayNotHasKey('updated_at', $audit->new_values);
        $this->assertArrayNotHasKey('created_at', $audit->new_values);
    }

    // ----------------------------------------------------------- attribution

    #[Test]
    public function it_records_who_did_it_by_name(): void
    {
        $user = AuditTestUser::query()->create([
            'name' => 'Aisha', 'email' => 'aisha@example.test', 'password' => 'x',
        ]);
        Audit::query()->delete();

        $this->actingAs($user);
        AuditWidget::query()->create(['name' => 'Blue Mug']);

        $audit = Audit::query()->sole();

        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('Aisha', $audit->user_label);
    }

    #[Test]
    public function the_actor_name_survives_the_account_being_deleted(): void
    {
        $user = AuditTestUser::query()->create([
            'name' => 'Aisha', 'email' => 'aisha@example.test', 'password' => 'x',
        ]);

        $this->actingAs($user);
        AuditWidget::query()->create(['name' => 'Blue Mug']);

        $audit = Audit::query()->where('auditable_type', AuditWidget::class)->sole();

        $user->forceDelete();

        // "user #7 deleted the invoice" after user 7 is gone has lost the thing
        // the trail was kept for.
        $this->assertSame('Aisha', $audit->fresh()->user_label);
    }

    #[Test]
    public function it_labels_the_record_so_a_listing_reads(): void
    {
        AuditWidget::query()->create(['name' => 'Blue Mug']);

        $this->assertSame('Blue Mug', Audit::query()->sole()->auditable_label);
    }

    // -------------------------------------------------------------- control

    #[Test]
    public function auditing_can_be_suspended_for_a_callback(): void
    {
        app(AuditRecorder::class)->withoutAuditing(function () {
            AuditWidget::query()->create(['name' => 'Seeded']);
        });

        $this->assertSame(0, Audit::query()->count());

        // ...and comes back afterwards.
        AuditWidget::query()->create(['name' => 'Real']);

        $this->assertSame(1, Audit::query()->count());
    }

    #[Test]
    public function config_can_switch_it_off_entirely(): void
    {
        config()->set('isproject.audit.enabled', false);

        AuditWidget::query()->create(['name' => 'Blue Mug']);

        $this->assertSame(0, Audit::query()->count());
    }

    #[Test]
    public function a_failure_to_write_the_log_does_not_break_the_save(): void
    {
        Schema::drop('isproject_audits');

        // The trail is a record of the application's work, not part of it.
        $widget = AuditWidget::query()->create(['name' => 'Blue Mug']);

        $this->assertTrue($widget->exists);
        $this->assertDatabaseHas('audit_widgets', ['name' => 'Blue Mug']);
    }

    #[Test]
    public function a_mass_update_is_not_recorded(): void
    {
        AuditWidget::query()->create(['name' => 'Blue Mug']);
        Audit::query()->delete();

        AuditWidget::query()->update(['price' => '99.00']);

        // Documented, not accidental: query builder updates fire no model
        // events, so nothing sees them. The README says so too.
        $this->assertSame(0, Audit::query()->count());
    }

    // --------------------------------------------------------------- screen

    #[Test]
    public function the_trail_screen_lists_entries(): void
    {
        AuditWidget::query()->create(['name' => 'Blue Mug']);

        $this->get('/audit')
            ->assertOk()
            ->assertSee('Blue Mug')
            ->assertSee('Created');
    }

    #[Test]
    public function the_trail_screen_filters(): void
    {
        $widget = AuditWidget::query()->create(['name' => 'Blue Mug']);
        $widget->update(['name' => 'Red Mug']);

        // Asserting on the record label, not on the word "Created" — that
        // appears in the event filter's own dropdown on every render.
        $this->get('/audit?event=updated')->assertOk()->assertSee('Red Mug')->assertDontSee('Blue Mug');
        $this->get('/audit?q=Nothing+Matches')->assertOk()->assertSee('Nothing recorded yet');
    }

    #[Test]
    public function an_entry_shows_both_sides_of_the_change(): void
    {
        $widget = AuditWidget::query()->create(['name' => 'Blue Mug', 'price' => '19.90']);
        $widget->update(['price' => '24.50']);

        $audit = Audit::query()->where('event', Audit::UPDATED)->sole();

        $this->get("/audit/{$audit->id}")
            ->assertOk()
            ->assertSee('19.90')
            ->assertSee('24.50');
    }

    #[Test]
    public function the_trail_is_read_only(): void
    {
        $widget = AuditWidget::query()->create(['name' => 'Blue Mug']);
        $audit = Audit::query()->sole();

        // Evidence you can edit is not evidence. There is no route for it.
        $this->put("/audit/{$audit->id}")->assertMethodNotAllowed();
        $this->delete("/audit/{$audit->id}")->assertMethodNotAllowed();
    }

    // --------------------------------------------------------------- prune

    #[Test]
    public function pruning_removes_only_what_is_past_retention(): void
    {
        AuditWidget::query()->create(['name' => 'Old']);
        Audit::query()->update(['created_at' => now()->subDays(400)]);

        AuditWidget::query()->create(['name' => 'Recent']);

        $this->artisan('isproject:audit-prune', ['--days' => 365])->assertSuccessful();

        $this->assertSame(1, Audit::query()->count());
        $this->assertSame('Recent', Audit::query()->sole()->auditable_label);
    }

    #[Test]
    public function pretending_deletes_nothing(): void
    {
        AuditWidget::query()->create(['name' => 'Old']);
        Audit::query()->update(['created_at' => now()->subDays(400)]);

        $this->artisan('isproject:audit-prune', ['--days' => 10, '--pretend' => true])->assertSuccessful();

        $this->assertSame(1, Audit::query()->count());
    }

    #[Test]
    public function it_refuses_a_retention_of_zero_days(): void
    {
        AuditWidget::query()->create(['name' => 'Something']);

        // --days=0 would mean "delete the entire trail", which is never what
        // somebody typing a number meant.
        $this->artisan('isproject:audit-prune', ['--days' => 0])->assertFailed();

        $this->assertSame(1, Audit::query()->count());
    }
}

/** Models that exist only for these tests. */
class AuditWidget extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'audit_widgets';

    protected $guarded = [];
}

class AuditTestUser extends Authenticatable
{
    use Auditable;

    protected $table = 'audit_users';

    protected $guarded = [];
}
