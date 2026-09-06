---
title: Commands and troubleshooting
icon: grid
summary: Every command in one table, and what to try when something will not behave.
---

# Commands and troubleshooting

## Every command

| Command | Does |
|---|---|
| `isproject:install` | Publishes the assets and config. `--views` and `--stubs` publish those too, `--all` publishes everything |
| `isproject:crud Book` | Generates a module from the `books` table |
| `isproject:crud-all` | Generates one for every table in the database |
| `isproject:crud-remove Book` | Deletes the files a module generated. Never the table |
| `isproject:archivable books` | Writes a migration adding `archived_at` |
| `isproject:permissions` | Scans the routes and syncs the permission list |
| `isproject:audit-prune` | Deletes audit entries past the retention period |

Common flags:

| Flag | On | Means |
|---|---|---|
| `--force` | crud, crud-remove, install | Overwrite, or skip the confirmation |
| `--only=` / `--except=` | crud | Generate a subset |
| `--table=` | crud | The table is not the plural of the model name |
| `--dry-run` | crud-remove | List what would go, delete nothing |
| `--pretend` | audit-prune | Count what would go, delete nothing |

## Troubleshooting

### "Table not found" when generating

The generator reads a table that already exists. Run `php artisan migrate` first,
and check the table name — `isproject:crud Book` looks for `books`. If yours is
called something else, say so with `--table=`.

### The module generated but the page is a 404

The routes are appended to `routes/web.php`. Check they arrived, and that you
have not cached routes from before:

```bash
php artisan route:list --name=books
php artisan route:clear
```

### The new module is not in the sidebar

Expected. Add it from [the menu screen](menu) — it is offered to you there.

### A 403 on a page I just made

Your role does not hold the new permission yet. Roles → **Rescan routes**, then
tick it. See [Users and roles](users-and-roles).

### My logo, favicon or profile photo does not appear

The public disk needs its symlink:

```bash
php artisan storage:link
```

### A setting I changed has no effect

Cached config. Clear it from the bottom of [Settings](settings), or:

```bash
php artisan optimize:clear
```

### The styling looks wrong after an update

The published assets are a copy, so they need refreshing:

```bash
php artisan vendor:publish --tag=isproject-assets --force
```

### Everything looks broken and I do not know why

In order, stopping when it works:

```bash
php artisan optimize:clear
php artisan vendor:publish --tag=isproject-assets --force
php artisan migrate
composer dump-autoload
```

> [!TIP]
> Read the error message before doing any of that. Laravel's error page in debug
> mode names the file and the line, and nine times out of ten it is a typo in a
> migration rather than anything to do with the framework.
