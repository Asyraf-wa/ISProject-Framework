<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Concerns\HasRoles;
use IsProject\Framework\Models\Role;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UserMakeCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', MakeUserTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('make_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    #[Test]
    public function it_creates_an_account(): void
    {
        $this->artisan('isproject:user', [
            'email' => 'new@example.test',
            '--name' => 'New Person',
            '--password' => 'a-long-enough-password',
        ])->assertSuccessful();

        $user = MakeUserTestUser::query()->where('email', 'new@example.test')->firstOrFail();

        $this->assertSame('New Person', $user->name);
        $this->assertTrue(Hash::check('a-long-enough-password', $user->password));
    }

    #[Test]
    public function admin_creates_the_role_and_attaches_it(): void
    {
        $this->artisan('isproject:user', [
            'email' => 'boss@example.test',
            '--name' => 'Boss',
            '--password' => 'a-long-enough-password',
            '--admin' => true,
        ])->assertSuccessful();

        $user = MakeUserTestUser::query()->where('email', 'boss@example.test')->firstOrFail();
        $role = Role::query()->where('name', 'Administrator')->firstOrFail();

        $this->assertTrue($role->is_super_admin);
        $this->assertTrue($user->roles->contains($role));
    }

    #[Test]
    public function running_it_twice_updates_rather_than_failing_on_the_unique_email(): void
    {
        $arguments = [
            'email' => 'again@example.test',
            '--name' => 'First Name',
            '--password' => 'a-long-enough-password',
        ];

        $this->artisan('isproject:user', $arguments)->assertSuccessful();
        $this->artisan('isproject:user', ['--name' => 'Second Name'] + $arguments)->assertSuccessful();

        $this->assertSame(1, MakeUserTestUser::query()->count());
        $this->assertSame('Second Name', MakeUserTestUser::query()->value('name'));
    }

    #[Test]
    public function a_short_password_is_refused(): void
    {
        $this->artisan('isproject:user', [
            'email' => 'weak@example.test',
            '--name' => 'Weak',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertSame(0, MakeUserTestUser::query()->count());
    }

    #[Test]
    public function a_nonsense_email_is_refused(): void
    {
        $this->artisan('isproject:user', [
            'email' => 'not-an-address',
            '--name' => 'Nope',
            '--password' => 'a-long-enough-password',
        ])->assertFailed();

        $this->assertSame(0, MakeUserTestUser::query()->count());
    }

    #[Test]
    public function it_says_so_when_the_users_table_is_not_there_yet(): void
    {
        Schema::drop('make_users');

        // The likeliest mistake on a first install is running this before
        // migrate, so it must say which command is missing rather than throw.
        $this->artisan('isproject:user', [
            'email' => 'early@example.test',
            '--name' => 'Early',
            '--password' => 'a-long-enough-password',
        ])
            ->expectsOutputToContain('php artisan migrate')
            ->assertFailed();
    }

    #[Test]
    public function it_says_so_when_the_user_model_cannot_hold_roles(): void
    {
        config(['auth.providers.users.model' => RolelessTestUser::class]);

        Schema::create('roleless_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        // The account is still saved — only the role could not be attached, and
        // the message names the trait to add.
        $this->artisan('isproject:user', [
            'email' => 'noroles@example.test',
            '--name' => 'No Roles',
            '--password' => 'a-long-enough-password',
            '--admin' => true,
        ])
            ->expectsOutputToContain('HasRoles')
            ->assertFailed();

        $this->assertSame(1, RolelessTestUser::query()->count());
    }
}

/** A User model that exists only for these tests. */
class MakeUserTestUser extends Authenticatable
{
    use HasRoles;

    protected $table = 'make_users';

    protected $guarded = [];
}

/** Deliberately without HasRoles, to exercise the warning. */
class RolelessTestUser extends Authenticatable
{
    protected $table = 'roleless_users';

    protected $guarded = [];
}
