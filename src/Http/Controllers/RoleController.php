<?php

namespace IsProject\Framework\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use IsProject\Framework\Models\Permission;
use IsProject\Framework\Models\Role;
use IsProject\Framework\Support\Access;
use IsProject\Framework\Support\PermissionRegistry;

/**
 * Roles and the permission matrix.
 *
 * The matrix is drawn from the live route collection, not from the permissions
 * table, so it always shows the application as it is now. The table only exists
 * to hang the role assignments off.
 */
class RoleController extends Controller
{
    public function index(PermissionRegistry $registry): View
    {
        return view('isproject::access.roles.index', [
            'roles' => Role::query()->withCount(['permissions', 'users'])->orderBy('name')->get(),
            'unsynced' => $registry->unsynced(),
        ]);
    }

    public function create(PermissionRegistry $registry): View
    {
        return $this->form(new Role, $registry);
    }

    public function edit(Role $role, PermissionRegistry $registry): View
    {
        return $this->form($role, $registry);
    }

    public function store(Request $request, PermissionRegistry $registry): RedirectResponse
    {
        $role = new Role;

        $this->save($role, $request, $registry);

        return redirect()
            ->route('isproject.roles.edit', $role)
            ->with('success', "Role [{$role->name}] created.");
    }

    public function update(Request $request, Role $role, PermissionRegistry $registry): RedirectResponse
    {
        $wasSuper = $role->is_super_admin;

        $this->save($role, $request, $registry);

        // Standing on the branch you are sawing: if this was the only super
        // admin role and it just stopped being one, nobody can reach this
        // screen again. Put it back and say so.
        if ($wasSuper && ! $role->is_super_admin && ! Role::query()->where('is_super_admin', true)->exists()) {
            $role->update(['is_super_admin' => true]);

            return back()->with('error', 'This is the only super admin role, so it has been left as one. Create another first.');
        }

        return back()->with('success', 'Role saved.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_super_admin && Role::query()->where('is_super_admin', true)->count() === 1) {
            return back()->with('error', 'That is the only super admin role. Deleting it would lock everyone out.');
        }

        // Deleting a role you hold could remove your own access mid-request.
        if ($request->user()?->roles()->whereKey($role->getKey())->exists()) {
            return back()->with('error', 'You hold this role yourself. Remove it from your account first.');
        }

        $name = $role->name;
        $role->delete();

        return redirect()->route('isproject.roles.index')->with('success', "Role [{$name}] deleted.");
    }

    /** Rebuild the permission list from the routes registered right now. */
    public function sync(PermissionRegistry $registry): RedirectResponse
    {
        $result = $registry->sync();

        $added = count($result['added']);
        $removed = count($result['removed']);

        if ($added === 0 && $removed === 0) {
            return back()->with('success', 'Permissions are already up to date.');
        }

        return back()->with('success', "Permissions rescanned: {$added} added, {$removed} removed.");
    }

    private function form(Role $role, PermissionRegistry $registry): View
    {
        return view('isproject::access.roles.form', [
            'role' => $role,
            'modules' => $registry->modules(),
            'columns' => $registry->abilityColumns(),
            'granted' => $role->exists ? $role->permissionNames() : [],
            'unsynced' => $registry->unsynced(),
        ]);
    }

    private function save(Role $role, Request $request, PermissionRegistry $registry): void
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('isproject_roles', 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_super_admin' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            // Only routes that actually exist. Anything else is a stale form or
            // a hand-crafted request, and neither should create a permission.
            'permissions.*' => ['string', Rule::in($registry->names())],
        ]);

        $role->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_super_admin' => (bool) ($validated['is_super_admin'] ?? false),
        ])->save();

        $role->permissions()->sync(
            Permission::query()->whereIn('name', $validated['permissions'] ?? [])->pluck('id')->all()
        );

        // The pivot is written directly, so the model's saved() hook has not
        // fired for it — retire the cached permission sets explicitly.
        $role->refresh();
        Access::flush();
    }
}
