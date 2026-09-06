<?php

namespace IsProject\Framework\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use IsProject\Framework\Models\Role;
use IsProject\Framework\Support\Access;
use IsProject\Framework\Support\Avatars;

/**
 * User management.
 *
 * Deliberately model-agnostic: the class comes from
 * config('auth.providers.users.model'), so this works against whatever User an
 * application already has rather than one this package imposes.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $users = $this->query()
            ->with('roles')
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate((int) config('isproject.per_page', 15))
            ->withQueryString();

        return view('isproject::access.users.index', [
            'users' => $users,
            'search' => $search,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('isproject::access.users.form', [
            'user' => $this->model()->newInstance(),
            'roles' => Role::query()->orderBy('name')->get(),
            'assigned' => [],
        ]);
    }

    public function edit(int|string $user): View
    {
        $model = $this->query()->findOrFail($user);

        return view('isproject::access.users.form', [
            'user' => $model,
            'roles' => Role::query()->orderBy('name')->get(),
            'assigned' => $model->roles->pluck('id')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, null);

        $user = $this->model()->newInstance();
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ])->save();

        $this->assignRoles($request, $user, $validated['roles'] ?? []);

        return redirect()
            ->route('isproject.users.index')
            ->with('success', "User [{$user->email}] created.");
    }

    public function update(Request $request, int|string $user): RedirectResponse
    {
        $model = $this->query()->findOrFail($user);
        $validated = $this->validated($request, $model);

        $model->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        // Blank means "leave it alone" — an edit form should not require
        // retyping a password to change somebody's name.
        if (! empty($validated['password'])) {
            $model->forceFill(['password' => Hash::make($validated['password'])]);
        }

        $model->save();

        $error = $this->assignRoles($request, $model, $validated['roles'] ?? []);

        return $error
            ? back()->with('error', $error)
            : back()->with('success', 'User saved.');
    }

    public function destroy(Request $request, int|string $user): RedirectResponse
    {
        $model = $this->query()->findOrFail($user);

        if ((string) $model->getKey() === (string) $request->user()?->getAuthIdentifier()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($this->isLastSuperAdmin($model)) {
            return back()->with('error', 'That is the last super admin. Promote someone else first.');
        }

        $email = $model->email;

        // Before the row goes: otherwise the file outlives the person it
        // belonged to, with nothing left pointing at it to find it by.
        app(Avatars::class)->forget($model);

        $model->delete();

        Access::flush();

        return back()->with('success', "User [{$email}] deleted.");
    }

    /**
     * Apply the submitted roles, refusing the one change nobody can undo:
     * removing the last super admin.
     *
     * Returns an error message when the change was refused, null otherwise.
     */
    private function assignRoles(Request $request, Model $user, array $roleIds): ?string
    {
        $roleIds = array_map('intval', $roleIds);

        $wouldLoseSuper = $this->isLastSuperAdmin($user)
            && ! Role::query()->whereKey($roleIds)->where('is_super_admin', true)->exists();

        if ($wouldLoseSuper) {
            return 'Roles were not changed: this is the last super admin account.';
        }

        $user->roles()->sync($roleIds);
        Access::flush();

        return null;
    }

    /** Whether this user is the only one holding a super admin role. */
    private function isLastSuperAdmin(Model $user): bool
    {
        $superRoleIds = Role::query()->where('is_super_admin', true)->pluck('id');

        if ($superRoleIds->isEmpty() || ! $user->roles()->whereKey($superRoleIds)->exists()) {
            return false;
        }

        return $this->query()
            ->whereHas('roles', fn (Builder $query) => $query->whereKey($superRoleIds))
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Model $user): array
    {
        $table = $this->model()->getTable();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique($table, 'email')->ignore($user?->getKey()),
            ],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::exists('isproject_roles', 'id')],
        ]);
    }

    /** @return Builder<Model> */
    private function query(): Builder
    {
        return $this->model()->newQuery();
    }

    private function model(): Model
    {
        $class = config('auth.providers.users.model');

        abort_if(! $class || ! class_exists($class), 500, 'No user model is configured for the default auth provider.');

        return new $class;
    }
}
