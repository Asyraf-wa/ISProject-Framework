<?php

namespace IsProject\Framework\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use IsProject\Framework\Concerns\HasRoles;
use IsProject\Framework\Models\Role;
use IsProject\Framework\Support\Access;
use Throwable;

/**
 * Create the first account.
 *
 * Without this a fresh install has nobody who can sign in, and no way to make
 * anybody: the settings screen that switches on self-registration is itself
 * behind authentication. The only alternative was a tinker one-liner, which is
 * a poor first instruction — especially on Windows, where quoting it correctly
 * is its own small ordeal.
 */
class UserMakeCommand extends Command
{
    protected $signature = 'isproject:user
        {email? : Email address}
        {--name= : Display name}
        {--password= : Password (prompted for if omitted)}
        {--admin : Give the account a super admin role}
        {--role= : Name of the super admin role to use}';

    protected $description = 'Create or update an account, optionally as a super admin';

    public function handle(): int
    {
        $model = config('auth.providers.users.model');

        if (! $model || ! class_exists($model)) {
            $this->components->error('No user model is configured for the default auth provider.');

            return self::FAILURE;
        }

        if (! $this->tableReady($model)) {
            $this->components->error('The users table does not exist yet. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $email = $this->argument('email') ?: $this->ask('Email address');
        $name = $this->option('name') ?: $this->ask('Display name', 'Administrator');

        // secret() so it is not echoed, and not left in the shell history the
        // way a --password on the command line would be.
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $existing = $model::query()->where('email', $email)->first();

        $user = $existing ?: new $model;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ])->save();

        $this->components->info(
            $existing
                ? "Updated [{$email}]."
                : "Created [{$email}]."
        );

        if ($this->option('admin') && ! $this->makeAdmin($user)) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Sign in at /login with that address.');

        return self::SUCCESS;
    }

    /** Attach a super admin role, creating it if it is not there. */
    private function makeAdmin(mixed $user): bool
    {
        if (! in_array(HasRoles::class, class_uses_recursive($user), true)) {
            $this->components->error(
                'The account was saved, but '.$user::class.' does not use the HasRoles trait, '
                .'so no role could be attached. Add it and re-run with --admin.'
            );

            return false;
        }

        try {
            $role = Role::query()->firstOrNew(['name' => (string) ($this->option('role') ?: 'Administrator')]);
            $role->fill([
                'is_super_admin' => true,
                'description' => $role->description ?: 'Full access to everything.',
            ])->save();

            $user->roles()->syncWithoutDetaching([$role->id]);
            Access::flush();
        } catch (Throwable $e) {
            $this->components->error('Could not attach the role: '.$e->getMessage());

            return false;
        }

        $this->components->info("Granted the [{$role->name}] role — full access to everything.");

        return true;
    }

    private function tableReady(string $model): bool
    {
        try {
            return Schema::hasTable((new $model)->getTable());
        } catch (Throwable) {
            return false;
        }
    }
}
