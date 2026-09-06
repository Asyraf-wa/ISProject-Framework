<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Concerns\HasRoles;
use IsProject\Framework\Http\Middleware\EnsurePermission;
use IsProject\Framework\Models\Permission;
use IsProject\Framework\Models\Role;
use IsProject\Framework\Support\Access;
use IsProject\Framework\Support\PermissionRegistry;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', AccessTestUser::class);
        $app['config']->set('isproject.access.middleware', ['web']);
    }

    /**
     * Routes the registry is meant to discover. Defined here rather than in a
     * fixture so the test states exactly what the matrix should end up holding.
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function ($router) {
            $router->get('products', fn () => 'list')->name('products.index');
            $router->get('products/create', fn () => 'form')->name('products.create');
            $router->post('products', fn () => 'created')->name('products.store');
            $router->get('tasks', fn () => 'tasks')->name('tasks.index');

            // Must never reach the matrix: unnamed, or on the ignore list.
            $router->get('anonymous', fn () => 'no name');
            $router->get('login', fn () => 'sign in')->name('login');
        });

        $router->middleware(['web', EnsurePermission::class])->group(function ($router) {
            $router->get('guarded/products', fn () => 'guarded list')->name('products.guarded');
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('access_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    // ------------------------------------------------------------- discovery

    #[Test]
    public function it_discovers_permissions_from_named_routes(): void
    {
        $names = $this->registry()->names();

        $this->assertContains('products.index', $names);
        $this->assertContains('products.create', $names);
        $this->assertContains('tasks.index', $names);
    }

    #[Test]
    public function it_skips_unnamed_and_ignored_routes(): void
    {
        $names = $this->registry()->names();

        // An unnamed route cannot be matched by the middleware, and signing in
        // must never be something a role can be denied.
        $this->assertNotContains('login', $names);
        $this->assertSame([], array_filter($names, fn (string $n) => $n === ''));
    }

    #[Test]
    public function it_groups_routes_into_modules_and_abilities(): void
    {
        $modules = collect($this->registry()->modules())->keyBy('key');

        $this->assertArrayHasKey('products', $modules);
        $this->assertSame(['index', 'create', 'store', 'guarded'], array_keys($modules['products']['abilities']));

        // Read-only verbs sort before the ones that change something.
        $this->assertSame('products.index', $modules['products']['abilities']['index']['name']);
    }

    #[Test]
    public function syncing_adds_new_routes_and_removes_departed_ones(): void
    {
        $registry = $this->registry();

        $first = $registry->sync();
        $this->assertContains('products.index', $first['added']);

        // A permission whose route no longer exists must not stay assignable.
        Permission::query()->create(['name' => 'ghosts.index']);

        $second = $this->registry()->sync();

        $this->assertSame([], $second['added']);
        $this->assertSame(['ghosts.index'], $second['removed']);
        $this->assertDatabaseMissing('isproject_permissions', ['name' => 'ghosts.index']);
    }

    #[Test]
    public function it_reports_routes_that_have_not_been_synced(): void
    {
        $this->assertNotEmpty($this->registry()->unsynced());

        $this->registry()->sync();

        $this->assertSame([], $this->registry()->unsynced());
    }

    // ----------------------------------------------------------- enforcement

    #[Test]
    public function a_user_may_reach_what_their_role_grants(): void
    {
        $this->registry()->sync();

        $user = $this->userWithPermissions(['products.guarded']);

        $this->actingAs($user)->get('guarded/products')->assertOk();
    }

    #[Test]
    public function a_user_is_refused_what_their_role_does_not_grant(): void
    {
        $this->registry()->sync();

        $user = $this->userWithPermissions(['products.index']);

        $this->actingAs($user)->get('guarded/products')->assertForbidden();
    }

    #[Test]
    public function a_super_admin_passes_without_holding_any_permission(): void
    {
        $this->registry()->sync();

        $user = $this->user();
        $user->roles()->attach(Role::query()->create(['name' => 'Owner', 'is_super_admin' => true]));
        Access::flush();

        $this->assertSame([], $user->permissionNames());
        $this->actingAs($user)->get('guarded/products')->assertOk();
    }

    #[Test]
    public function nothing_is_enforced_until_a_role_exists(): void
    {
        $this->registry()->sync();

        // Permissions are synced but nobody has defined a role. There is
        // nothing to enforce, and enforcing would deny every request.
        $this->assertSame(0, Role::query()->count());

        $this->actingAs($this->user())->get('guarded/products')->assertOk();
    }

    #[Test]
    public function an_unscanned_route_is_allowed_by_default_and_can_be_denied(): void
    {
        // Deliberately not synced: the route exists, the permission does not.
        Role::query()->create(['name' => 'Someone']);

        $user = $this->user();

        $this->actingAs($user)->get('guarded/products')->assertOk();

        config()->set('isproject.access.unknown_routes', 'deny');

        $this->actingAs($user)->get('guarded/products')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_left_to_the_auth_middleware(): void
    {
        $this->registry()->sync();
        Role::query()->create(['name' => 'Someone']);

        // Answering 403 here would strand a guest instead of sending them to
        // the sign-in screen.
        $this->get('guarded/products')->assertOk();
    }

    // ------------------------------------------------------------------ gate

    #[Test]
    public function permissions_work_through_laravels_own_gate(): void
    {
        $this->registry()->sync();

        $user = $this->userWithPermissions(['products.index']);

        $this->assertTrue(Gate::forUser($user)->allows('products.index'));
        $this->assertFalse(Gate::forUser($user)->allows('tasks.index'));
    }

    #[Test]
    public function the_gate_hook_does_not_shadow_other_abilities(): void
    {
        $this->registry()->sync();

        Gate::define('brew-coffee', fn () => true);

        $user = $this->userWithPermissions(['products.index']);

        // RBAC must answer only for abilities it owns, or every policy in the
        // application would start returning false.
        $this->assertTrue(Gate::forUser($user)->allows('brew-coffee'));
    }

    // ----------------------------------------------------------- the screens

    #[Test]
    public function the_matrix_lists_every_discovered_module(): void
    {
        $this->registry()->sync();

        $this->actingAs($this->superAdmin())
            ->get('/access/roles/create')
            ->assertOk()
            ->assertSee('products')
            ->assertSee('tasks');
    }

    #[Test]
    public function saving_a_role_stores_exactly_the_ticked_permissions(): void
    {
        $this->registry()->sync();

        $this->actingAs($this->superAdmin())->post('/access/roles', [
            'name' => 'Viewer',
            'permissions' => ['products.index', 'tasks.index'],
        ])->assertRedirect();

        $role = Role::query()->where('name', 'Viewer')->firstOrFail();

        $this->assertEqualsCanonicalizing(['products.index', 'tasks.index'], $role->permissionNames());
    }

    #[Test]
    public function it_refuses_a_permission_that_is_not_a_real_route(): void
    {
        $this->registry()->sync();

        $this->actingAs($this->superAdmin())->post('/access/roles', [
            'name' => 'Sneaky',
            'permissions' => ['products.index', 'wipe.database'],
        ])->assertSessionHasErrors('permissions.1');
    }

    #[Test]
    public function the_last_super_admin_role_cannot_be_demoted(): void
    {
        // It has to be the only super admin role for the guard to apply, so
        // demote the one the acting administrator actually holds.
        $admin = $this->superAdmin();
        $role = $admin->roles->first();

        $this->actingAs($admin)->put("/access/roles/{$role->id}", [
            'name' => $role->name,
            'is_super_admin' => '0',
        ])->assertSessionHas('error');

        $this->assertTrue($role->fresh()->is_super_admin);
    }

    #[Test]
    public function a_super_admin_role_can_be_demoted_once_another_exists(): void
    {
        $admin = $this->superAdmin();
        $role = $admin->roles->first();

        Role::query()->create(['name' => 'Second Owner', 'is_super_admin' => true]);

        $this->actingAs($admin)->put("/access/roles/{$role->id}", [
            'name' => $role->name,
            'is_super_admin' => '0',
        ])->assertSessionHas('success');

        $this->assertFalse($role->fresh()->is_super_admin);
    }

    #[Test]
    public function the_last_super_admin_role_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $role = $admin->roles->first();

        $this->actingAs($admin)->delete("/access/roles/{$role->id}")->assertSessionHas('error');

        $this->assertDatabaseHas('isproject_roles', ['id' => $role->id]);
    }

    #[Test]
    public function it_creates_a_user_with_roles(): void
    {
        $role = Role::query()->create(['name' => 'Viewer']);

        $this->actingAs($this->superAdmin())->post('/access/users', [
            'name' => 'Aisha',
            'email' => 'aisha@example.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'roles' => [$role->id],
        ])->assertRedirect();

        $user = AccessTestUser::query()->where('email', 'aisha@example.test')->firstOrFail();

        $this->assertTrue($user->hasRole('Viewer'));
    }

    #[Test]
    public function editing_a_user_without_a_password_keeps_the_old_one(): void
    {
        $admin = $this->superAdmin();
        $original = $admin->password;

        $this->actingAs($admin)->put("/access/users/{$admin->id}", [
            'name' => 'Renamed',
            'email' => $admin->email,
            'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $admin->fresh()->name);
        $this->assertSame($original, $admin->fresh()->password);
    }

    #[Test]
    public function you_cannot_delete_your_own_account(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->delete("/access/users/{$admin->id}")->assertSessionHas('error');

        $this->assertDatabaseHas('access_users', ['id' => $admin->id]);
    }

    #[Test]
    public function the_last_super_admin_cannot_lose_the_role(): void
    {
        $admin = $this->superAdmin();
        $other = Role::query()->create(['name' => 'Viewer']);

        $this->actingAs($admin)->put("/access/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [$other->id],
        ])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }

    // -------------------------------------------------------------- helpers

    private function registry(): PermissionRegistry
    {
        // A fresh instance each time: the registry memoises the route scan, and
        // these tests change what has been synced between calls.
        return new PermissionRegistry;
    }

    private function user(string $email = 'user@example.test'): AccessTestUser
    {
        return AccessTestUser::query()->create([
            'name' => 'Test User',
            'email' => $email,
            'password' => 'hashed',
        ]);
    }

    /** @param  array<int, string>  $permissions */
    private function userWithPermissions(array $permissions, string $email = 'user@example.test'): AccessTestUser
    {
        $role = Role::query()->create(['name' => 'Role '.$email]);
        $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id')->all());

        $user = $this->user($email);
        $user->roles()->attach($role);

        Access::flush();

        return $user;
    }

    private function superAdmin(): AccessTestUser
    {
        $user = $this->user('admin@example.test');
        $user->roles()->attach(Role::query()->create(['name' => 'Administrator', 'is_super_admin' => true]));

        Access::flush();

        return $user->fresh();
    }
}

/** A User model that exists only for these tests. */
class AccessTestUser extends Authenticatable
{
    use HasRoles;

    protected $table = 'access_users';

    protected $guarded = [];

    public $timestamps = true;
}
