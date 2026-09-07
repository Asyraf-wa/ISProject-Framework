# isproject/framework

[![tests](https://github.com/Asyraf-wa/ISProject-Framework/actions/workflows/tests.yml/badge.svg)](https://github.com/Asyraf-wa/ISProject-Framework/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/isproject/framework.svg)](https://packagist.org/packages/isproject/framework)
[![Licence](https://img.shields.io/packagist/l/isproject/framework.svg)](LICENSE)

A Laravel teaching framework for Information Systems web application
development: CRUD scaffolding generated from your database schema, on top of a
first-party design system — responsive sidebar, dark mode, no paid theme and no
CDN.

Developers write a migration, run it, and get a complete working module — model,
controller, form requests, Blade views, factory, policy and routes.

```bash
php artisan make:migration create_products_table
php artisan migrate
php artisan isproject:crud Product
```

---

## Installing

Requires **PHP 8.3+** and **Laravel 12 or 13**.

```bash
composer require isproject/framework
php artisan isproject:install
php artisan migrate
php artisan storage:link
```

`isproject:install` publishes the config and the compiled assets. Add `--views`
to publish the layout and partials for editing, `--stubs` to reshape what the
generator writes, or `--all` for both.

`storage:link` is what makes uploaded logos, favicons and profile photos
visible; without it they upload successfully and never appear.

That is the whole installation. There is no npm step — `resources/dist` ships
compiled, so the framework runs on a machine that has never seen Node.

> **Version policy.** This is 0.x: the config file, the stub tokens and the
> shape of the generated code may still change. Pin with `^0.1` and read
> [CHANGELOG.md](CHANGELOG.md) before upgrading.

---

## Why two repositories

| Repository | Composer type | What it is | How developers get it |
|---|---|---|---|
| `isproject/framework` | `library` | This repo — generators, stubs, shared views, config | pulled in as a dependency |
| `isproject/skeleton` | `project` | A pre-wired Laravel app (layout, auth, dashboard) | `laravel new myapp --using=isproject/skeleton` |

This repo is the first half. The skeleton is step 2 of the roadmap below.

---

## Development environment

Requires Docker. Nothing else — not even PHP on the host.

```bash
cp .env.example .env          # set UID/GID to match `id -u` / `id -g`
docker compose up -d --build
docker compose exec app bash /var/www/docker/bin/init-sandbox.sh --demo
```

That last command creates `./sandbox` — a real Laravel application that
consumes this package through a Composer **path repository with symlinks**. An
edit to `src/` or `stubs/` is live in the app immediately; there is nothing to
republish and no cache to clear.

`--demo` also creates `categories` and `products` tables and generates CRUD for
both, which is the quickest way to confirm everything works.

| Service | URL | Notes |
|---|---|---|
| Application | http://localhost:8080 | login `admin@example.test` / `password` |
| phpMyAdmin | http://localhost:8081 | signed in automatically — no password to type |
| Mailpit | http://localhost:8025 | catches all outgoing mail |
| MySQL | `localhost:3307` | user `isproject`, password `secret` |
| Redis | `localhost:6380` | |

phpMyAdmin signs in as the application user, which sees both `isproject` and the
`isproject_testing` schema. To sign in as someone else — root, to manage users
or create databases — comment out `PMA_USER` and `PMA_PASSWORD` in
`docker-compose.yml` and you get the normal login form; the root password is
`DB_ROOT_PASSWORD` from your `.env`.

Every port is configurable in `.env` if one clashes with something you already
run: `APP_PORT`, `PHPMYADMIN_PORT`, `DB_PORT`, `REDIS_PORT`, `MAILPIT_PORT`.

Run artisan through the wrapper so you do not have to remember the working
directory:

```bash
./dev migrate                     # ./dev.ps1 on Windows PowerShell
./dev isproject:crud Product
./dev isproject:crud-all --force
```

Front-end assets are opt-in, once the sandbox has a `package.json`:

```bash
docker compose --profile assets up -d
```

---

## Commands

### `isproject:crud`

Generates one module from one existing table.

```bash
php artisan isproject:crud Product
php artisan isproject:crud Product --table=tbl_products
php artisan isproject:crud Product --only=views,controller --force
php artisan isproject:crud Product --except=policy,routes
```

| Option | Effect |
|---|---|
| `--table=` | Table to introspect. Defaults to the plural snake case of the model. |
| `--only=` | Subset of `model,controller,requests,views,factory,policy,routes`. |
| `--except=` | Targets to skip, applied after `--only` / config. |
| `--connection=` | Database connection to read. |
| `--force` | Overwrite files that already exist. |

### `isproject:crud-all`

Every table in the connection, in one command.

```bash
php artisan isproject:crud-all
php artisan isproject:crud-all --tables=products,categories
php artisan isproject:crud-all --skip=users --force
```

### The generator page

For developers who would rather not use the terminal, the same generator has a web
UI at **`/isproject/generator`** (also linked in the sidebar). It lists every
table on the connection with its shape — columns, row count, relations, whether
it has soft deletes, which parts are already generated — and a **Generate CRUD**
button, with checkboxes for what to emit.

**It writes PHP files into your application, so it is gated hard:**

| Guard | Behaviour |
|---|---|
| Environment | Local only by default. **Never** registered in production, whatever the config says |
| Routes | Not registered at all when disabled — the URL simply does not exist |
| Middleware | `EnsureGeneratorIsEnabled` re-checks and 404s, in case routes were cached before the config changed |
| Auth | `web` + `auth` by default (`isproject.generator.middleware`) |
| Gate | Optional extra ability via `isproject.generator.gate`, to limit it to lecturers |
| Input | The table must be one the connection actually has and config does not ignore; the model name must be a bare StudlyCase word; targets are checked against an allow-list |

```dotenv
ISPROJECT_GENERATOR=true    # switch it on outside local — never on a shared server
```

Existing files are skipped unless "Overwrite my edits" is ticked, so a developer
cannot lose an afternoon's work by double-clicking the button.

Each generated module also has a **Remove this module** panel, and the same thing
is available from the terminal:

```bash
php artisan isproject:crud-remove Product --dry-run   # list what would go
php artisan isproject:crud-remove Product
```

> **It deletes code, never data.** The table, its rows and its migration are left
> exactly as they are — dropping a table is a decision taken in a migration, not
> a side effect of tidying up some PHP files. There is no route here that would
> drop one.

It removes the model, controller, both form requests, the policy, the factory,
the whole view folder, and any route line pointing at the controller. Because it
can delete files somebody has edited, it is more careful than generating:

| Guard | Why |
|---|---|
| The panel lists the exact paths first | Derived from the same config paths the command deletes, so what you read is what goes |
| You type the model name to confirm | A checkbox is muscle memory; a name is a decision |
| The model must belong to a real table on the connection | The name reaches file paths, so it is never free text |
| The name is validated **before** `Str::studly()` | Studly quietly strips slashes and dots, so `../../etc/passwd` would arrive as a harmless `EtcPasswd` and report success having done nothing — checking first means a bad name is refused and *said so* |
| Multi-line route definitions are left alone | Deleting one line of a statement that spans several would leave the file unparseable, so those are reported for a human to check |
| Other modules are untouched | Only paths built from this model's own name |

A sidebar entry you pasted into `config/isproject.php` can stay: the menu hides
entries whose route no longer exists, so nothing breaks either way.

### Sorting and page size

Generated index screens (and archive screens) sort by clicking a column heading,
and offer a page-size selector. Both survive each other and a search — changing
one carries the rest along in the query string, so refining a search never
silently resets your sort.

Foreign keys sort by the value the column **shows**, not by the id: "Category"
joins the related table and orders by its name, because ordering by
`category_id` looks arbitrary to anyone reading the page.

> **The sort column is a whitelist, not a parameter.** An `order by` clause is
> concatenated into SQL rather than bound, so `?sort=` may only *choose* from the
> map in the generated controller's `sortable()` — anything else is ignored and
> the default ordering stands. The direction is `asc` or `desc` and nothing else.
> There are tests firing `name; drop table products--` and friends at it.

Sorting joins a table, which is why generated search clauses are qualified
(`products.name`, not `name`) — unqualified, a search while sorted by Category
would be an ambiguous-column error, since `categories` has a `name` too.

```php
'per_page' => 15,
'per_page_options' => [15, 25, 50, 100],
'max_per_page' => 200,
```

Three clear choices rather than 20/30/40/50, which is four near-identical numbers
to read through. **"All" is capped** at `max_per_page`: unbounded, it is a hung
browser and an exhausted memory limit on any real table, so it means "as many as
we will render at once" — beyond that the pagination links reappear and say how
much more there is. A size that was not offered falls back to the default rather
than being honoured.

Both live in `IsProject\Framework\Concerns\ListsRecords`, so a fix reaches
modules generated months ago without regenerating them.

### Archiving

Putting a record away without deleting it. **Opt-in per table**, by one nullable
timestamp column — the same mechanic as Laravel's `deleted_at`, and detected the
same way:

```bash
php artisan isproject:archivable books   # writes the migration
php artisan migrate                      # you run it
php artisan isproject:crud Book --force  # now the module has an archive
```

The column is `archived_at`: `NULL` means active, a timestamp means archived and
records *when*. No second table — the row never moves, so ids stay stable and
foreign keys keep working. Add it to a new table directly with the Blueprint
macro:

```php
$table->archivable();   // timestamp('archived_at')->nullable()->index()
```

**The generator writes the migration; it never alters your schema.** If a column
appeared without a migration, a colleague cloning the repo and running
`migrate:fresh` would get a different table, and the archive screen would work on
one machine and not the other. The generator page offers the same thing as an
**Add archiving** button that writes the file and tells you the command to run.

#### What you get

A `books.archived` screen with a Restore action, an **Archive** item in the row
menu, and — because the same screen serves both — a **Deleted** tab for tables
that also soft delete, which until now had no way back through the UI at all.

| Route | Purpose |
|---|---|
| `GET books/archived` | The screen, with `?state=archived` / `?state=deleted` |
| `POST books/{book}/archive` | Archive one |
| `POST books/{book}/restore` | Bring one back, from either state |
| `DELETE books/{book}/purge` | Delete permanently (trashed records only) |

Four routes rather than one, because RBAC derives permissions from route names:
this way "may see the archive" is a separate tick from "may destroy records
permanently". The screen route is registered **above** `Route::resource`, or
`books/{book}` would capture "archived" as an id.

#### Worth knowing

- **Archive and delete are independent.** A record can be archived, trashed, or
  both. Trash wins: a deleted record is out of every list, the archive included.
- **Route binding sees archived records.** The trait overrides
  `resolveRouteBinding()`, without which every link out of the archive screen —
  including the Restore button meant to fix it — would 404.
- **The audit trail says `archived` / `unarchived`**, not a bare "updated
  archived_at". `archived_at` is in `audit.ignored_attributes` so the change is
  reported once, in words.
- **No cascade.** Archiving a Category does not archive its Books. Silent bulk
  state changes are hard to reverse and harder to explain.

```php
Book::query()          // active only
Book::withArchived()   // active + archived
Book::onlyArchived()   // the archive screen's query
```

### Authentication

Sign in, sign out, forgotten passwords, an optional self-registration form and a
profile screen — on Laravel's conventional route names (`login`, `logout`,
`register`, `password.*`, `profile.*`), so anything expecting them keeps working.

Set `isproject.auth.enabled` to `false` if you use Breeze, Jetstream or Fortify;
the framework's routes are then never registered and yours are left alone.

Two things are switched on from **`/settings` → Sign in**, not from code:

| Setting | Default | Effect |
|---|---|---|
| Allow self-registration | off | Adds a "Create an account" link. Off means accounts are made on the Users screen only — and the `register` route returns 404, so a bookmarked POST cannot get round it |
| Allow Google sign-in | off | Adds a "Continue with Google" button |
| Google may create accounts | off | Off means Google can only sign in someone who **already** has an account — usually what a course roster wants |

#### Google sign-in

Two values in your **application's** `.env` — `sandbox/.env` in the dev
environment above, not the `.env` at the root of this repo, which configures
Docker. Nothing else; no `composer require`:

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=      # only when a proxy or tunnel makes the default wrong
```

**Until both are present the toggle stays disabled** and names the variables you
are missing. A switch that turns on a flow which can only end at a Google error
page is worse than no switch, so the check is enforced on the server too — a
hand-made request to enable it is refused with the same message.

The redirect URI defaults to `/auth/google/callback` on your host, and must
match what you register in the Google console; a mismatch there is the single
most common reason this fails, so the error from Google is passed straight
through rather than replaced with "something went wrong".

This is the OAuth authorization code flow written against Laravel's HTTP client
rather than Socialite, precisely so that "add two values, tick a box" is the
whole story. If you already use Socialite, set `isproject.auth.enabled` to false
and keep your own routes.

| Guard | Why |
|---|---|
| A `state` value is generated per attempt, stored in the session and required back | A callback that does not carry it is not the continuation of a flow this session started |
| The state is **single-use** | A replayed callback finds nothing to match |
| The code is exchanged **server-side** over TLS | The browser never sees the client secret |
| `email_verified` must be true | Linking on an unverified address would be account takeover |
| A first-time Google user is refused unless account creation is switched on | A Google account is not by itself a reason to be in your system |
| Created accounts get a random unusable password | They sign in through Google; there is nothing to guess |

Linked accounts are listed on the profile screen and can be disconnected there.

#### Profile photos

Anyone signed in can upload a photo of themselves from **`/profile`**. It then
stands in for their initials everywhere the framework shows a person — the
topbar, the users list, the profile screen — with no change to those screens:

```blade
<x-isproject::avatar :user="$user" />
<x-isproject::avatar :user="$user" size="lg" />
```

With no photo the component falls back to initials, first and last, so
"Aisha Rahman" reads as **AR** rather than as the first two letters of a word.

The photo is stored in a package-owned `isproject_profiles` table keyed by user
id, **not** as a column on your users table — that table is whatever
`auth.providers.users.model` points at, and may not be called `users` at all.
Nothing about your own schema has to change.

Files go to the `isproject.settings.disk` disk (`public` by default) under
`isproject/avatars`, so `php artisan storage:link` is what makes them visible;
until it is run the photo simply does not appear, and no page breaks.

| Rule | Why |
|---|---|
| PNG, JPEG or WebP, up to 2 MB | Formats a browser will render as an image and nothing else |
| **SVG is never accepted** | An SVG can carry script, and these files are served from your own origin — accepting one would be stored XSS that any signed-in user could upload |
| The stored name is 40 random characters | The disk is public. A name derived from the user id would let anyone walk the ids and collect every photograph on the site |
| Replacing or removing deletes the file that was there | Otherwise every upload leaves a copy behind, still reachable by URL |
| Deleting an account deletes its photo first | Otherwise the file outlives the person, with nothing left pointing at it to find it by |
| A refused upload leaves the existing photo alone | A failed validation should not cost someone the picture they already had |

Uploading and ticking *Remove my photo on save* in the same submission is read
as a replacement: someone who chose a file and left the tick behind meant to
swap the photo, not to delete it.

The image is cropped to a circle with `object-fit: cover`, so a wide or tall
upload is cropped rather than squashed.

### Audit trail

**`/audit`**, linked in the sidebar under *System*. Every create, update, delete
and restore, with who did it, when, from where, and both sides of every value
that moved.

Add the trait to a model — **generated models already have it**:

```php
use IsProject\Framework\Concerns\Auditable;

class Product extends Model
{
    use Auditable;
}
```

An update records only what actually changed, and both of its values, because
"price changed" is far less use than `19.90 → 24.50`. The listing filters by
record type, event, person and date; the detail screen shows a before/after
table. To put a record's own history on its show screen:

```blade
<x-isproject::audit-trail :model="$product" />
```

It renders nothing when the model is not audited, so it is safe to add before
every model has the trait.

> **Secrets are never written down.** Without this, a `User` model with the trait
> would copy its password hash into a table built for people to browse. Anything
> matching `isproject.audit.redacted_attributes` — `password`, `*_token`,
> `*_secret` and friends — is stored as `••••••••`. The *fact* of the change is
> kept, because "the password was changed, by this person, at this time" is
> exactly what an audit trail is for; the value is not.

| Behaviour | Why |
|---|---|
| Timestamps and `deleted_at` are ignored | They move on every write and bury the change somebody actually made. `restore()` also saves the model, so without this one restore would log twice |
| An update that changed nothing is not recorded | Saving an unmodified model is not an event |
| A failure to write the log never breaks the save | The trail is a record of the application's work, not part of it — the error is reported, the user's save still succeeds |
| The actor's name is stored alongside their id | "User #7 deleted the invoice" after user 7 is gone has lost the thing the trail was kept for |
| There is no route that edits or deletes an entry | Evidence you can edit is not evidence |

**What it does not see.** This listens to Eloquent model events, so it records
what goes through Eloquent. `Product::query()->update([...])` fires no model
events and is not recorded; nor is raw SQL. That is Eloquent's behaviour rather
than a gap here, and it is why this is a record of what the application did, not
a guarantee about what the database contains. There is a test asserting exactly
that, so the limit stays visible.

Suspend it when back-filling, so an import does not write thousands of entries
describing changes nobody made:

```php
app(AuditRecorder::class)->withoutAuditing(fn () => $seeder->run());
```

The table only grows. Schedule the pruner:

```php
Schedule::command('isproject:audit-prune')->daily();   // keeps audit.retention_days
```

```bash
php artisan isproject:audit-prune --days=90 --pretend
```

### Searchable dropdowns

Any `<select>` with more than **8 options** becomes one you can type into. The
timezone list is 420 entries; a route picker is a few hundred. Those are not
usable as plain selects, and nobody should have to remember to mark them.

```blade
<select data-is-select>...</select>        {{-- always enhanced, however short --}}
<select data-is-select="off">...</select>  {{-- never enhanced --}}
```

The threshold and the whole feature are config:

```php
'select' => ['enabled' => true, 'threshold' => 8],
```

#### Why Tom Select

| | |
|---|---|
| **Select2** | Needs jQuery. This project has none, and adding it to carry one widget is a poor trade |
| **Tom Select** ✓ | No dependencies, Apache-2.0, 52 KB minified — and it ships a Bootstrap 5 **SCSS** theme |

That SCSS theme is what settled it. It compiles against Bootstrap's own
variables, which in this project are ours — so `--is-control-border`, the dark
mode tokens and the contrast fixes all flow into it rather than being
reapplied by hand. Tom Select also copies the original select's classes onto its
wrapper, so the closed control *is* a `.form-select` and matches the fields
beside it exactly. Measured: its border sits at **4.37:1 in light and 4.24:1 in
dark — identical to a plain `<select>`**.

Two things the theme needed correcting on:

- Its Bootstrap 5 theme does colour arithmetic on `$input-border-color`, which
  in this project holds `var(--is-control-border)`. Sass cannot mix a custom
  property, so the four variables it does maths with are pinned to concrete
  values before the import.
- The highlighted option was `--bs-primary` flat, which measures **2.41:1 on a
  dark ground** — the same failure the checkboxes had. It now uses
  `--bs-primary-text-emphasis`: **6.04:1 dark, 12.04:1 light**.

#### What does not change

The original `<select>` stays in the page and keeps its `name`, so forms submit
exactly as before and no controller, request or validation rule is aware of any
of this. With JavaScript off you get ordinary selects and everything still
works.

The menu is parented to `<body>` so a scrolling table or a clipped card cannot
cut it off, and it sits at the same `z-index` as the Bootstrap dropdowns, above
the topbar — the two clipping problems the row menus hit earlier.

Accessibility comes from the library and was checked rather than assumed: the
control is a `role="combobox"` with `aria-expanded`, `aria-controls` and
`aria-labelledby`, and Tom Select rewrites the `<label for>` to point at it.
Typing filters, and arrow keys plus Enter select.

### Install as an app (PWA)

**Settings → Install as an app.** One switch adds a web app manifest and a
service worker, so the site can be added to a phone home screen or a desktop and
keeps a usable page when the connection drops.

| Setting | Effect |
|---|---|
| Offer to install this as an app | The switch. Everything below is inert while it is off |
| App name / Short name | The install prompt, and the label under the icon. A missing short name is cut from the long one at 12 characters |
| App icon | Square PNG or WebP. Refused below 192px or if it is not square; the screen asks for 512px |
| The icon has padding | Adds a `maskable` entry. Off by default, because an unpadded icon declared maskable gets its edges cropped on Android |
| Theme / Splash colour | The browser toolbar tint and the launch screen |
| How it opens | `standalone`, `minimal-ui`, `fullscreen` or `browser` |

#### Turning it off is the hard part

A registered service worker **outlives the page that registered it**. It stays
installed, keeps its caches and goes on answering requests for as long as that
browser profile exists. A toggle that merely stopped emitting the registration
script would leave every device that ever loaded the site running the old worker
indefinitely — and no amount of deploying would dislodge it.

So "off" is an instruction, not silence. Three things happen:

1. `/manifest.webmanifest` returns **404**, so nothing goes on advertising an
   installable app.
2. `/isproject-sw.js` serves a **self-destructing worker**: it clears every
   `isproject-` cache, calls `registration.unregister()`, and reloads open tabs.
   Browsers re-fetch this script on navigation and at least daily, so an old
   installation finds it and takes itself apart unattended.
3. Every page emits a small cleanup script that unregisters our worker and
   deletes its caches, for the browsers that get there first.

The manifest and the worker script are both served `no-cache`/`no-store` for the
same reason: an hour-long cache on either is an hour in which the off switch
does nothing.

#### What it caches, and what it refuses to

**Assets only. Never a page.**

Every screen here sits behind sign-in and is filtered by role, and these run on
shared lab machines. A cached page would show the next person at that computer
whatever the last one was looking at. So:

| Request | Treatment |
|---|---|
| A navigation | Network only. On failure, the offline page |
| `/vendor/isproject/*`, `/storage/*` | Cache first, then network |
| Anything else, or any non-GET | Not intercepted at all |
| The worker script and the manifest | Never intercepted — that is how a site pins itself to a version it can no longer replace |

The precache list holds **paths, not absolute URLs**. A worker resolves a path
against the host the visitor is actually on, whereas `asset()` builds on
`APP_URL`; where those differ — a tunnel, a staging alias, a site reached by IP
— every absolute entry is cross-origin and silently caches nothing.

#### The readiness panel

The toggle is never blocked, because the usual missing piece is HTTPS and nobody
can fix that from a form. Instead, once it is switched on, the settings screen
lists what a browser still wants:

| Check | Why it matters |
|---|---|
| Served over HTTPS | Browsers refuse a service worker on plain `http://`. `localhost` is the exception, which is why this works in development and stops on deployment |
| A square icon of at least 512px | Chrome will not offer to install without one. Smaller is still used, just never prompted |

Icons are declared in the manifest **at the size they actually are**, read off
the file: claiming 512×512 for a 64px image makes a browser fetch it, measure it
and refuse the install without saying why.

### The manual

**`/manual`**, linked in the sidebar under *Help*. A ten-chapter guide written
for the developer who has just been handed the scaffold — signing in and finding
their way around, generating their first module, then roles, settings, the menu,
the audit trail, archiving, going live, and a troubleshooting table.

It ships **with the package**, so it is versioned alongside the code it
describes rather than drifting on a wiki somewhere.

#### Chapters are Markdown files

`resources/manual/020-first-module.md` is the second chapter, served at
`/manual/first-module`. The number orders it, the rest is the slug, the first
`# heading` is dropped on render because the page header already shows the
title. Front matter carries the rest:

```markdown
---
title: Your first module
icon: box
summary: From an empty database table to a working screen, in three steps.
---
```

Nothing is registered anywhere. Adding a chapter means adding a file; inserting
one between two others means numbering it between them.

#### Writing your own

Point `isproject.manual.paths` at a directory of your own:

```php
'manual' => [
    'paths' => [resource_path('manual')],
],
```

Later directories win on a slug clash, so naming a file `020-first-module.md`
there **replaces** the bundled chapter — a lecturer can rewrite one for their
own course without forking the package.

| Feature | Notes |
|---|---|
| GitHub-flavoured Markdown | Tables above all; plain CommonMark renders a pipe table as a paragraph of pipes |
| `> [!NOTE]`, `[!TIP]`, `[!WARNING]`, `[!IMPORTANT]` | Become coloured callout boxes |
| `%%menuPath%%`, `%%settingsPath%%`, `%%app%%`, … | Replaced with this installation's real values, so the manual describes the system in front of the reader |
| On-page contents | Built from the `##` headings, which get ids automatically |
| Search | Server-side over the Markdown source, ranked by number of mentions. Works with JavaScript off |
| Caching | Rendered HTML is cached against the file's modification time, so editing a chapter shows up at once |

Raw HTML inside a chapter is **escaped**, not rendered. These files are as
trusted as a Blade view, but escaping costs nothing and means a manual directory
somebody made world-writable cannot become a script tag.

The manual requires a signed-in reader by default. Drop `auth` from
`isproject.manual.middleware` to open it up — reasonable for a public teaching
site, unwise for one holding real coursework.

> A test walks every cross-reference in every chapter and fails if one points at
> a chapter that no longer exists, so renaming a chapter cannot quietly leave
> dead links behind.

### Activity log

**`/activity`**, under *System*. Who signed in, who failed to, who was locked
out, who changed their password — and from which address.

This is **not** the audit trail. The audit trail answers "who edited this
invoice"; the activity log answers "who has been trying to get in". Keeping them
apart means neither list buries the other.

It is fed by **Laravel's own authentication events**, not by this package's
controllers, so it keeps working for an application using Breeze, Fortify or its
own login screen — even with `isproject.auth.enabled` set to false.

Log your own alongside them:

```php
isproject_activity('invoice.exported', 'Exported the March invoices', ['count' => 42]);
isproject_activity('order.shipped', 'Marked it shipped', [], $order);
```

| Guard | Why |
|---|---|
| A failed sign-in is **not** attributed to the account it targeted | "What has this person done" must not start listing things done *to* them by somebody else. The address tried is kept in properties |
| Passwords are dropped, not redacted | Unlike a changed field in an audit row, there is no version of a password worth keeping |
| It never throws | A log that can take down the thing it watches is worse than no log. A missing table must not turn somebody's sign-in into a 500 |

Prune it, or it grows for as long as people keep signing in:

```bash
php artisan isproject:activity-prune --days=90 --pretend
Schedule::command('isproject:activity-prune')->daily();
```

### Dashboard and charts

**`/dashboard`** — sign-ins over the last fortnight, what has been happening,
which modules changed, and the latest entries. Built **only from tables this
package owns**, because a starting dashboard that assumes you have an `orders`
table is one that breaks on every project except the one it was written for.

Charts are [ECharts](https://echarts.apache.org) (Apache-2.0), self-hosted with
the other assets. Use one anywhere:

```blade
<x-isproject::chart :option="$option" height="300" />
```

`$option` is a plain ECharts option array from PHP. Two things the component
does that matter:

- **The library loads only on pages that have a chart.** It is 664 KB; a page
  without one should not pay for that. The component pushes the script tag, the
  layout does not carry it.
- **Colours are not in your option.** They are read from the stylesheet in the
  browser at draw time and reapplied when the theme changes, so a chart follows
  light and dark mode. A palette baked into the PHP would not — it would be dark
  axis labels on a dark panel, the failure this design avoids.

### The sign-in screen

Sign in, register and password reset share a split layout: a showcase panel
beside the form, so the first page anybody sees reads as the front of a product
rather than a bare login box. The headline falls back to the tagline on the
settings screen, so most projects never touch it.

```php
'landing' => ['enabled' => false],   // a plain centred card instead
```

Below the large breakpoint the showcase is not rendered small — it is not
rendered at all. A marketing panel stacked above a sign-in form on a phone is
something to scroll past to reach the thing you came for.

### Menu management

**`/menu`**, linked in the sidebar under *System*. Reorder the sidebar by
dragging, nest one level, pick icons, add links to other sites, and hide a row
without deleting it — no file to edit and no build step.

#### Two sources, one rule

The sidebar reads **`config('isproject.menu')` while the menu items table is
empty, and the table as soon as it holds a single row.** Nothing in between.

That bluntness is the point. A menu half in config and half in the database
would quietly reintroduce a config entry among rows somebody had curated, and
there would be no way to remove it from the screen. So:

- Installing the migration changes nothing — the table starts empty.
- The screen offers **Manage the menu here**, which copies config in verbatim.
  The sidebar is byte-identical afterwards; it is a copy, not an edit.
- **Back to config** empties the table and hands control back.

The import also adds an entry for the menu screen itself if your config has
none — otherwise importing makes the sidebar database-driven with no link left
to the screen that edits it.

#### "I generated a module and it is not in the sidebar"

The screen lists every module that has a listing route nothing points at, and
adds one on a click with its permission already filled in. This is why the
generator does not need to write to your menu: the route it created is enough to
discover the module afterwards.

#### The four kinds of entry

| Kind | Points at | Notes |
|---|---|---|
| Page in this system | A named route | Survives URL changes. What a generated module wants |
| Path | `/reports` | Must start with `/` |
| Another website | `https://…` | Opens in a new tab with `rel="noopener noreferrer"`, and is never marked active |
| Heading | Nothing | Labels the group. One with nothing under it is dropped rather than left floating |

Each entry takes an icon from the bundled set, an optional permission (hidden
from anyone without it), an optional badge, and a parent.

#### Reordering

Drag a row by its handle, or use the up and down arrows. They do the same thing:
the arrows are ordinary form posts, so reordering works from a keyboard, on a
touch screen, and with JavaScript switched off — WCAG 2.5.7 asks that a dragging
movement never be the only way to reach an outcome.

Drops are validated on the server, not just in the browser. Positions are
assigned from the order given rather than trusted from the payload, so it cannot
invent gaps or duplicates, and these are refused outright:

| Refused | Why |
|---|---|
| A third level of nesting | Unusable in a 264px rail |
| A row nested under itself | It would become its own parent |
| Items under a heading | A heading labels a group; it does not contain one |
| An id that is not in the menu | The payload does not describe this menu |

A refusal leaves the database untouched and the screen reloads, so what you see
is the order that actually holds rather than a rejected one that looks saved.

| Guard | Why |
|---|---|
| Only `http://`, `https://` or a leading `/` | A menu is a list of links people click without reading them; `javascript:` is refused |
| Route names checked against the live route table | A name that does not resolve would render as a row that silently vanishes, which reads as a bug rather than a typo |
| Icons checked against the bundled set | Anything else renders as a blank square |
| Deleting a parent deletes its children | Orphans silently promoted to the top level is the worse surprise |

### Users and roles (RBAC)

**`/access/users`** and **`/access/roles`**, linked in the sidebar under *System*.

Permissions are **not written by hand**. The application's named routes are
scanned, and each becomes one permission — so a module you generate with
`isproject:crud` brings its own permissions with it. That is the whole reason
RBAC belongs in a framework that also generates the routes.

```bash
php artisan isproject:permissions                       # rescan
php artisan isproject:permissions --admin=Administrator --user=you@example.com
```

The roles screen is a matrix: modules down, actions across, both read from the
live route table. Row and column headings toggle whole rows and columns, and a
partly-ticked row shows as indeterminate.

**Add the trait to your User model** — this is the one manual step:

```php
use IsProject\Framework\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

**Enforcement is opt-in**, because an application has routes that must stay open:

```php
Route::resource('products', ProductController::class)
    ->middleware(['auth', 'isproject.permission']);
```

The middleware matches the current route's **name** against the matrix — which
is also why only named routes can be permitted. Everything else goes through
Laravel's own Gate, so nothing here replaces the API developers should be learning:

```blade
@can('products.create') <a href="...">New product</a> @endcan
```

```php
['label' => 'Products', 'route' => 'products.index', 'can' => 'products.index'],
```

| Behaviour | Why |
|---|---|
| A **super admin** role passes every check without holding a permission | Without it, saving the matrix with the wrong boxes ticked would lock you out of the screen that fixes it |
| Nothing is enforced until **at least one role exists** | Lets you add the middleware before setting RBAC up — otherwise turning it on early would deny every request |
| A route that has never been scanned is **allowed** by default | A forgotten rescan should not become a 403 nobody can diagnose. The roles screen shows a standing warning listing exactly these; set `access.unknown_routes` to `deny` to close it |
| The last super admin **role** cannot be demoted or deleted | It is the only way back in |
| The last super admin **user** cannot lose that role, and nobody can delete their own account | Same reason |
| The users, roles and settings screens are **not** guarded by the matrix | They use the settings gate instead, so a wrong tick can never make them unreachable |

> **Page level, not row level.** This grants "may edit products", not "may edit
> *their own* products". That distinction belongs in a policy — and the generator
> already writes one per module.

### The settings page

Site configuration lives at **`/settings`** (linked in the sidebar under
*System*), backed by the `isproject_settings` table — so the system name, logo
and favicon change without editing `.env` or redeploying.

Out of the box it covers the system name, tagline, support email, footer text,
brand icon, default theme, logo, favicon and timezone. Everything on the screen
takes effect somewhere: the name reaches the browser tab, sidebar, sign-in
screen and footer; the timezone is applied to PHP itself at boot.

**Adding a setting takes no code** — add an entry to
`config('isproject.settings.groups')` and it appears, validated and cast:

```php
'general' => ['label' => 'General', 'fields' => [
    'campus' => ['label' => 'Campus', 'help' => 'Shown on printed reports.'],
    'results_public' => ['type' => 'boolean', 'label' => 'Publish results'],
]],
```

Field types: `text`, `textarea`, `email`, `url`, `number`, `select`, `boolean`,
`image`. Rules are inferred from the type unless you give your own. Option lists
are either a plain array or one of `'@icons'`, `'@timezones'`, `'@themes'` —
**not a closure**, because `php artisan config:cache` cannot serialise one, and a
single closure anywhere in config breaks that command for the whole application.

#### Search engines

Meta description, share image, canonical address and a Google verification code,
all from **`/settings` → Search engines**.

> **Read this before expecting search traffic.** Almost every screen in this
> system sits behind `auth`, and a crawler cannot get past the sign-in page. So
> the indexable surface is the **signed-out** pages — the sign-in screen, and any
> public page you add. Indexing the admin screens is not merely useless: listing
> their titles and URLs advertises the shape of your system to anyone reading
> search results.

That decision is made per layout, not per site:

| Layout | Robots tag | Why |
|---|---|---|
| `layouts.app` | **always** `noindex, nofollow` | Signed-in screens, whatever the setting says |
| `layouts.guest` | follows the setting | The only public face the system has |

**Indexing is off by default**, which is right until the system is live. Turning
it on emits `index, follow`, a canonical link and `Organization` structured data
on the public pages — and nothing changes on the admin ones.

Open Graph and Twitter Card tags are emitted **even when the page is not
indexable**, because a link pasted into a staff group chat should still preview
properly on a site search engines are told to ignore. The share image falls back
to your logo.

##### The robots.txt trap

A stock Laravel application ships `public/robots.txt`, and the web server hands
that file over before PHP is ever reached — so the file silently overrules the
setting. The settings screen detects this and says so; delete
`public/robots.txt` to let the setting take effect.

It matters less than it sounds, because **the meta tag is the right tool anyway**:
`robots.txt` asks a crawler not to *fetch* a page, which means a disallowed
page's `noindex` never gets read. Blocking in `robots.txt` is a poor way to stay
out of an index; `noindex` is how you actually stay out.

No `sitemap.xml` is generated, and `robots.txt` does not advertise one — an admin
panel has a single public page, and pointing a crawler at a file that 404s is
worse than saying nothing.

#### Site notices

Two things you can put in front of every visitor without deploying, both from
**`/settings`**:

**Announcement bar** — a strip across the top of every page, signed in or not
(a maintenance notice matters most on the sign-in screen). Optional link, five
tones, and an optional **Show until** date so it stops appearing on its own
rather than relying on somebody remembering to switch it off.

Closing it hides it for **one hour** by default, configurable per site. Two
details make that behave the way people expect:

- The dismissal is remembered against a hash of **that** announcement, not a
  general "seen the bar" flag. Edit the wording and it reappears at once for
  everyone — including people who closed the previous one a minute ago, which is
  precisely when a new notice matters.
- The check runs in the pre-paint boot script, the same place the theme is
  restored, so a bar you already closed never flashes up before JavaScript
  removes it.

**Corner ribbon** — a small diagonal banner in the top-right, with optional link.
Hidden below the `md` breakpoint, where it would sit on top of the account menu;
above it, the topbar and the announcement's close button are given clearance so
nothing ends up under the band.

> **Links are restricted to `http(s)://` and site-relative paths.** With the
> default open settings gate, any signed-in user can set these — and a
> `javascript:` href would run for every visitor on every page. The settings form
> rejects it and `SiteNotices` refuses to render it, so neither alone is the only
> thing standing in the way. External links get `target="_blank"` and
> `rel="noopener noreferrer"`; relative ones stay in the tab.

The side panel clears Laravel's caches (`cache:clear`, `view:clear`,
`config:clear`, `route:clear`) — choose which appear with
`isproject.settings.cache_actions`. The action posted from the browser only ever
selects a key from a fixed map, so it can never name an arbitrary artisan
command.

Uploads need `php artisan storage:link`; the page says so if the link is
missing, rather than saving a logo that renders as a broken image.

> **Two things to know before putting this on a shared server.**
> Unlike the generator, this page is meant to work in production, so it is only
> as protected as you make it. With `'gate' => null` **any signed-in user** can
> rename the site and clear caches — define an ability and name it in
> `isproject.settings.gate`.
> SVG is deliberately not an accepted upload format: an SVG can carry script and
> these files are served from your own origin, which with an open gate is stored
> XSS. PNG, JPEG, WebP and `.ico` are accepted.

### `isproject:install`

Publishes what a developer is meant to edit.

```bash
php artisan isproject:install          # config + views
php artisan isproject:install --stubs  # ...and the generator templates
```

---

## What gets generated

From a table like this:

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained();
    $table->string('sku', 32)->unique();
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
    $table->boolean('is_featured')->default(false);
    $table->timestamps();
    $table->softDeletes();
});
```

you get:

```
app/Models/Product.php                     $fillable, casts(), belongsTo(Category)
app/Http/Controllers/ProductController.php index/create/store/show/edit/update/destroy
app/Http/Requests/StoreProductRequest.php  rules derived from the schema
app/Http/Requests/UpdateProductRequest.php same, with unique()->ignore()
app/Policies/ProductPolicy.php             permissive — you are expected to tighten it
database/factories/ProductFactory.php      type-aware faker values
resources/views/products/index.blade.php   searchable, paginated table
resources/views/products/_form.blade.php   shared create/edit fields
resources/views/products/_guide.blade.php  guidance panel beside the form
resources/views/products/archived.blade.php archive + trash, when the columns exist
resources/views/products/report.blade.php  filterable, printable report
resources/views/products/create|edit|show.blade.php
routes/web.php                             report + Route::resource appended
```

### The report screen

Every module gets `/products/report`, built from the same schema reading:

- **keyword search** over the text columns
- a **date range** on `created_at`, or the first date column if the table has no
  timestamps
- a **dropdown filter** for every enum, boolean and foreign key
- **summary tiles**: record count, plus a total for each numeric column
- **Print**, with a `@media print` block that strips the sidebar and topbar,
  repeats table headers across pages and avoids splitting rows
- **Export CSV**, streamed in chunks so a large export never has to fit in
  memory, with a UTF-8 BOM so Excel opens accented characters correctly

The export reuses the screen's query, so the download can never disagree with
what was on screen — change a filter and the CSV changes with it.

The schema drives every decision:

| Schema fact | Consequence |
|---|---|
| `NOT NULL` | `required` instead of `nullable` |
| `varchar(32)` | `max:32` and `maxlength="32"` |
| unique index | `unique:products,sku`, and `Rule::unique()->ignore()` when editing |
| foreign key | `belongsTo`, eager loading, `exists:` rule, a populated `<select>` |
| `enum(...)` | `in:` rule and a `<select>` of the allowed values |
| `tinyint(1)` | `boolean` cast, a switch input, a Yes/No badge |
| `text` | textarea, and kept off the index table for readability |
| `deleted_at` | `SoftDeletes` trait |
| no `created_at` | `$timestamps = false` and ordering falls back to the primary key |

Introspection uses `Schema::getColumns()`, `getIndexes()` and `getForeignKeys()`,
native since Laravel 11 — no Doctrine DBAL.

---

## Customising the output

Publish the stubs and edit them. The generator prefers your copies over the
packaged ones, so nobody has to fork the package:

```bash
php artisan vendor:publish --tag=isproject-stubs
```

They land in `stubs/isproject/` (configurable via `isproject.stub_path`).

Stubs use `%%placeholder%%` rather than Laravel's `{{ placeholder }}`, because
the view stubs are Blade templates — `{{ }}` inside them belongs to the
generated application, not to the generator.

Useful placeholders: `%%model%%`, `%%modelVariable%%`, `%%modelVariablePlural%%`,
`%%table%%`, `%%routeName%%`, `%%viewFolder%%`, `%%title%%`, `%%titleSingular%%`,
`%%layout%%`, `%%fillable%%`, `%%casts%%`, `%%relations%%`, `%%rulesStore%%`,
`%%rulesUpdate%%`, `%%indexHeaders%%`, `%%indexCells%%`, `%%formFields%%`,
`%%showRows%%`, `%%factoryDefinition%%`.

---

## The design system

No paid theme, no CDN, no Google Fonts — everything is self-hosted and MIT, so
it runs unchanged on an offline lab machine and can be redistributed freely.

| Layer | What it is |
|---|---|
| **Bootstrap 5.3** (MIT) | grid, components, dark-mode plumbing — compiled **from SCSS source**, so our tokens are baked in rather than patched over |
| **`resources/scss/_tokens.scss`** | the design tokens: palette, type scale, radii, shadows, sidebar metrics. Change a value here and the whole framework follows |
| **`resources/scss/_layout.scss`** | the app shell — sidebar, topbar, content area, guest screen |
| **`resources/scss/_components.scss`** | page headers, soft badges, tables, empty states, detail lists |
| **`resources/scss/_dark.scss`** | retunes our own surfaces for dark mode |
| **`resources/js/isproject.js`** | ~150 lines of plain ES: sidebar state, drawer, theme toggle. No jQuery, no bundler |
| **`x-isproject::icon`** | inline SVG icons (Bootstrap Icons paths, MIT). No icon font to publish, inherits `currentColor` |

### The sidebar

- **≥ 992px** — fixed 264px panel, collapsible to a 76px icon rail. Hovering a
  collapsed item reveals its label as a flyout, in pure CSS.
- **< 992px** — off-canvas drawer with a backdrop, opened from the topbar, closed
  by the backdrop, Escape, or following any link inside it.
- State persists in `localStorage` and is restored by an inline script in
  `<head>` **before first paint**, so there is no flash of the wrong layout.

### Dark mode

Three states — light, dark, and system (the default, which follows the OS and
keeps following it live). Toggled from the topbar, persisted, and applied before
paint by the same boot script. Built on Bootstrap 5.3's native
`data-bs-theme` attribute.

### Form controls and contrast

Bootstrap derives a checkbox's border from `--bs-border-color` and its fill from
`--bs-body-bg`. Since this design system also uses `--bs-body-bg` for cards, that
default produced an invisible control — a `#f4f5fb` square with an `#e2e8f0`
outline on an `#f4f5fb` card, measuring **1.13:1**. WCAG 1.4.11 asks for 3:1 on
the boundary of a control.

Three custom properties now own that, defined once per theme in `_layout.scss`:

| Token | Light | Dark | What it drives |
|---|---|---|---|
| `--is-control-border` | slate-500 | slate-500 | Text input and checkbox borders |
| `--is-control-bg` | white | slate-950 | The interior of an unchecked box |
| `--is-control-checked` | `$primary` | 25% tint of `$primary` | Checked and indeterminate fill |

Measured against the card in both themes:

| | Light | Dark |
|---|---|---|
| Checkbox border | 4.37:1 | 3.75:1 |
| Checked fill | 7.26:1 | 4.05:1 |
| Tick on its own fill | 7.90:1 | 4.41:1 |
| Text input border | 4.37:1 | 3.75:1 |

The dark checked fill is a *tint* of the brand indigo rather than the indigo
itself, for the same reason the sidebar's active state is: `$primary` is one
value for both themes, and `#4338ca` on the dark panel measures 2.26:1 — a
ticked box you cannot tell from an empty one. Lighten it too far and the white
tick inside stops reading, so 25% is the point that satisfies both.

To soften the borders, change `--is-control-border` in one place.

### Rebuilding the CSS

`resources/dist/` is **committed**, so the framework runs with no npm step. You
only need this to change the design:

```bash
docker compose --profile assets run --rm node sh -c "cd /var/www && npm install && npm run build"
```

or, with node on the host: `npm install && npm run build` (`npm run watch` while
working on the SCSS).

### Using your own layout

```dotenv
ISPROJECT_LAYOUT=layouts.app
ISPROJECT_GUEST_LAYOUT=layouts.guest
```

Your layout needs a `content` section and a `title` section.

### The sidebar menu

Driven by `config('isproject.menu')`:

```php
['heading' => 'Manage'],
['label' => 'Products', 'icon' => 'box', 'route' => 'products.index'],
['label' => 'Reports', 'icon' => 'list', 'can' => 'viewAny', 'children' => [
    ['label' => 'Monthly', 'route' => 'reports.monthly'],
]],
```

Entries naming a route that does not exist yet are skipped rather than throwing,
so a menu can list a module before it is generated. `isproject:crud` prints the
line to paste after it runs. Permission filtering (`can`), active-state
detection and nesting are all handled by the renderer.

This array is the *default*. Once anybody curates the menu from **`/menu`** the
table takes over — see below.

---

## Configuration

```bash
php artisan vendor:publish --tag=isproject-config
```

Notable keys in `config/isproject.php`:

| Key | Purpose |
|---|---|
| `layout` | Blade layout every generated view extends |
| `generate` | Default target list for `isproject:crud` |
| `middleware` | Extra middleware on generated routes (`web` is already applied) |
| `ignored_columns` | Never surfaced in forms, tables or rules |
| `ignored_tables` | Never touched by `isproject:crud-all` |
| `searchable_types` | Column types included in the keyword search |
| `max_index_columns` | Keeps wide tables readable on the list screen |
| `paths` / `namespaces` | Where generated classes are written |

---

## Roadmap

A teaching framework needs rather more than CRUD. Planned order, reusing
maintained packages rather than rebuilding them:

| Feature | Status | Approach |
|---|---|---|
| Design system, responsive sidebar | **done** | own SCSS over Bootstrap 5, self-hosted |
| Light / dark mode toggle | **done** | `data-bs-theme`, three states, applied before paint |
| Site configuration | **done** | own `isproject_settings` table, schema declared in config, cached |
| User management + RBAC | **done** | permissions discovered from the route table, enforced through Laravel's Gate |
| Auth + profile | **done** | first-party login, password reset, optional Google sign-in toggled from settings |
| Profile photos | **done** | package-owned table, random filenames on the public disk, initials as the fallback |
| Audit trail | **done** | own Auditable trait on Eloquent events, redaction by config, prunable |
| Announcement bar, promo ribbon | **done** | driven from settings; dismissal remembered per message, restored before paint |
| Archived | **done** | opt-in `archived_at` column, detected by the generator; shares a screen with the trash |
| Sortable columns, page size | **done** | whitelisted `order by`, relations sort by their label, capped "All" |
| SEO / indexing | **done** | meta, Open Graph and canonical from settings; admin screens always `noindex` |
| Menu management | **done** | `isproject_menu_items` feeding the existing renderer; config is the fallback, drag or arrows to sort |
| In-app manual | **done** | Markdown chapters shipped with the package, rendered at `/manual`, searchable, overridable per course |
| Install as an app (PWA) | **done** | Manifest and service worker from settings; disabling serves a self-unregistering worker, and no page is ever cached |
| Activity log | **done** | own table fed by Laravel auth events; works with Breeze and Fortify too |
| Dashboard and charts | **done** | ECharts self-hosted, loaded only where a chart exists, themed from the stylesheet |
| Searchable dropdowns | **done** | Tom Select, self-hosted, themed from our own SCSS tokens; applied by option count so nothing needs marking |
| Skeleton starter kit | next | `isproject/skeleton`, installed with `laravel new --using=` |
| Advanced search / filter | | `spatie/laravel-query-builder` + a Livewire index table |
| QR sharing | | `endroid/qr-code`, exposed as a Blade component |
| FAQ, to-do, contact us | | Generated with `isproject:crud` — the package dogfooding itself |

---

## Testing and style

```bash
docker compose exec -w /var/www app composer install
docker compose exec -w /var/www app vendor/bin/pint
```

A `isproject_testing` database is created alongside the main one, so the test
suite never wipes data you have been clicking through in the browser.

---

## Licence

MIT.

Every third-party asset shipped or generated by this package is permissively
licensed, so the whole thing can be handed to developers, forked and redistributed
without negotiating anyone's terms:

| | Licence |
|---|---|
| Bootstrap 5 (compiled into `resources/dist/isproject.css`) | MIT — © The Bootstrap Authors |
| Bootstrap Icons (SVG paths in `x-isproject::icon`) | MIT — © The Bootstrap Authors |
| Tom Select (`resources/dist/tom-select.min.js`) | Apache-2.0 — © Tom Select authors |
| Apache ECharts (`resources/dist/echarts.min.js`) | Apache-2.0 — © The Apache Software Foundation |
| Laravel | MIT |

Tom Select and ECharts are the exceptions to MIT. Both are redistributed
unmodified, both keep their licence headers in the minified files, and the full
Apache-2.0 text ships beside each as `tom-select.LICENSE.txt` and
`echarts.LICENSE.txt` — which is what that licence asks for. Nothing in either
restricts forking or redistribution.

No commercial theme, no CDN, no webfont service. Nothing phones home, and the
whole UI works on a machine with no internet connection.
