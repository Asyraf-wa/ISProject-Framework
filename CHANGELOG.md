# Changelog

All notable changes to `isproject/framework` are recorded here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Packagist reads versions from git tags, so every release below has a matching
`vX.Y.Z` tag.

## [Unreleased]

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

[Unreleased]: https://github.com/Asyraf-wa/ISProject-Framework/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Asyraf-wa/ISProject-Framework/releases/tag/v0.1.0
