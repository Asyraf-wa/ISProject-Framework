<?php

namespace IsProject\Framework\Console;

use Illuminate\Console\Command;
use IsProject\Framework\Models\Role;
use IsProject\Framework\Support\Access;
use IsProject\Framework\Support\PermissionRegistry;

/**
 * Rebuild the permission list from the routes the application registers.
 *
 * Worth running from a deploy script: a module generated on one machine only
 * becomes assignable once its routes have been scanned on the machine serving
 * the application.
 */
class PermissionSyncCommand extends Command
{
    protected $signature = 'isproject:permissions
        {--admin= : Create or update this super admin role and give it everything}
        {--user= : Email of a user to grant that role to}';

    protected $description = 'Scan the application routes and sync them into the RBAC permission list';

    public function handle(PermissionRegistry $registry): int
    {
        $result = $registry->sync();

        $this->components->info(sprintf(
            'Permissions synced: %d added, %d removed, %d total.',
            count($result['added']),
            count($result['removed']),
            count($registry->names()),
        ));

        foreach ($result['added'] as $name) {
            $this->components->twoColumnDetail("<fg=green>+</> {$name}", 'new');
        }

        foreach ($result['removed'] as $name) {
            $this->components->twoColumnDetail("<fg=red>-</> {$name}", 'route no longer exists');
        }

        if ($role = $this->option('admin')) {
            $this->makeAdmin((string) $role);
        }

        return self::SUCCESS;
    }

    /**
     * A super admin role passes every check on its own, so it is not given the
     * permission rows — that is the point of the flag. Granting them anyway
     * would only leave a matrix to drift out of date.
     */
    private function makeAdmin(string $name): void
    {
        $role = Role::query()->firstOrNew(['name' => $name]);
        $role->fill(['is_super_admin' => true, 'description' => $role->description ?: 'Full access to everything.'])->save();

        $this->components->info("Super admin role [{$role->name}] is ready.");

        if (! $email = $this->option('user')) {
            return;
        }

        $class = config('auth.providers.users.model');
        $user = $class ? $class::query()->where('email', $email)->first() : null;

        if (! $user) {
            $this->components->error("No user with email [{$email}] — role not assigned.");

            return;
        }

        $user->roles()->syncWithoutDetaching([$role->getKey()]);
        Access::flush();

        $this->components->info("Granted [{$role->name}] to {$email}.");
    }
}
