# Changelog

All notable changes to `isproject/framework` are recorded here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Packagist reads versions from git tags, so every release below has a matching
`vX.Y.Z` tag.

## [Unreleased]

## [0.2.0] — 2026-09-07

**Upgrading from 0.1.0 is `composer update` plus `migrate`.** The renames under
*Changed* are all in this repository's own development environment — the
sandbox directory, the artisan wrapper, the seeded demo account. None of them
exist in an installed package, so nothing in your application has to move.

Two steps are worth taking after updating:

```bash
php artisan migrate                                        # the activity log table
php artisan vendor:publish --tag=isproject-assets --force  # the new CSS and JS
```

### Added

- **Activity log** at `/activity` — sign-ins, failed sign-ins, lockouts and
  password changes, fed by Laravel's own authentication events so it also works
  for applications using Breeze, Fortify or their own login screen. Log your own
  with `isproject_activity()`. Prune with `isproject:activity-prune`.
- **Dashboard** at `/dashboard`, charted with self-hosted Apache ECharts, built
  only from tables this package owns.
- **`<x-isproject::chart>`** component. The library loads only on pages that use
  it, and colours come from the stylesheet at draw time so charts follow the
  light and dark themes.
- **Split sign-in screen** — a showcase panel beside the form, shared by sign in,
  register and password reset. Turn it off with `isproject.landing.enabled`.
- **`isproject:user`** creates the first account, optionally as a super admin.
  Without it a fresh install had nobody who could sign in and no supported way
  to make anybody: the setting that enables self-registration is itself behind
  the sign-in, so the only route was a `tinker` one-liner.
- **Installation instructions** in the README for two routes — Docker via Sail,
  and XAMPP / WAMP / Laragon — with a table of the things that actually go
  wrong on each.
- **Accent colour** in Settings → Appearance: seven presets shown as swatches
  with their measured contrast, changing buttons, links, the current menu item,
  checkboxes, focus rings and the first chart series. A fixed set rather than a
  colour picker because every option is verified to clear 4.5:1 as text, as a
  white-labelled button, and as its dark tint — which a picker cannot promise.
  Choosing the default emits no CSS at all.

### Changed

- The development application directory is now `sandbox`, not `playground`, and
  the wording throughout addresses **developers** rather than students.
- The seeded administrator is `admin@example.test` (was `lecturer@example.test`).
  The password is unchanged.
- The artisan wrapper is now `./dev` (`.\dev.ps1` on Windows), renamed from
  `art`. It is also executable, which it was not before — so the command the
  README documented failed on a fresh clone.

### Fixed

- **Published assets are versioned.** The stylesheet and scripts sit at a fixed
  path, so re-publishing after an upgrade changed the bytes without changing the
  URL — and because static files carried no `Cache-Control`, browsers applied
  *heuristic* freshness and served the old copy without even revalidating. The
  symptom was styling that only appeared after a hard reload. Every published
  asset now carries a `?v=` derived from the file, `isproject_asset()` builds
  those URLs, and the dev nginx config sets an explicit immutable year, which
  the versioning makes safe.
- The service worker had the same bug one layer down: it precached those paths
  cache-first, so an installed app would have served the old stylesheet
  indefinitely. Its precache list is versioned too.

- Brand indigo used as text measured 1.8–2.4:1 on dark panels — below the 4.5:1
  WCAG minimum — on the manual cards, avatar initials, menu icons and the
  searchable-dropdown highlight. All now use a theme-aware accent token and
  measure 5.0–6.4:1. Light mode is unchanged.

## [0.1.0] — 2026-09-06

First public release.

Versioned 0.x deliberately: the config file, the stub tokens and the generated
code are still open to change, and 0.x says so. Pin to `^0.1` and read this file
before upgrading.

### The generator

- `isproject:crud` — reads an existing table and writes the model, controller,
  form requests, Blade views, factory, policy and routes. Field types,
  validation rules and relation dropdowns are inferred from the columns.
- `isproject:crud-all` — the same across every table in the connection.
- `isproject:crud-remove` — deletes the files a module generated. Never the
  database table.
- `isproject:archivable` — writes a migration adding an `archived_at` column.
- A generator screen at `/isproject/generator`, available only while the
  application is in debug mode.

### Generated screens

- Index with search, sortable headings, a page-size selector and a row menu.
- Full-width create and edit forms with a guidance panel.
- Detail screen, printable report.
- Archiving: `archived_at` detected automatically, with Active / Archived /
  Trash tabs on one screen.

### The application shell

- A design system built on Bootstrap 5 compiled from SCSS source, with a
  responsive sidebar, a light/dark/system theme applied before first paint, and
  no CDN or paid theme.
- Searchable dropdowns (Tom Select) applied by option count.
- Settings at `/settings`: site name, logo, favicon, announcement bar, corner
  ribbon, search-engine metadata, timezone and cache controls.
- Menu management at `/menu`: drag or arrow-key reordering, one level of
  nesting, internal and external links, per-entry permissions.
- Users and roles at `/access`, with permissions discovered from the route table
  rather than declared by hand.
- Audit trail at `/audit`, with redaction and a prune command.
- Authentication: sign in, registration, password reset, profile with photo
  upload, and optional Google sign-in toggled from settings.
- An in-app manual at `/manual`, written as Markdown chapters shipped with the
  package and overridable per course.
- Progressive web app support toggled from settings, including a service worker
  that unregisters itself when the setting is switched off.

### Notes

- Requires PHP 8.3+ and Laravel 12 or 13.
- `resources/dist` is committed, so the package runs without installing npm.
- Everything shipped is permissively licensed; see the licence section of the
  README.

[Unreleased]: https://github.com/Asyraf-wa/ISProject-Framework/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/Asyraf-wa/ISProject-Framework/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/Asyraf-wa/ISProject-Framework/releases/tag/v0.1.0
