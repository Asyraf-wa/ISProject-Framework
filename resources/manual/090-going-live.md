---
title: Going live
icon: check-circle
summary: Search engines, the things to turn off, and the checklist before you hand it in.
---

# Going live

Whether "live" means a real server or a demonstration to your examiners, the same
handful of things matter.

## Let search engines in

**Settings → Search engines.** Everything is off until you say otherwise, because
a half-finished coursework project appearing in search results helps nobody.

| Setting | What it does |
|---|---|
| Allow search engines to index this site | The master switch. Off means every page carries `noindex` |
| Meta description | The grey line under your title in search results. Around 155 characters |
| Share image | The picture that appears when somebody pastes a link into a chat |
| Site keywords, author, publisher | Filled into the page metadata |

> [!NOTE]
> Admin screens are **always** `noindex`, whatever this setting says. Your
> settings page has no business in a search result.

### If a `public/robots.txt` file exists

The web server hands that file over before your application is ever asked, so
the setting above cannot affect it. The settings screen warns you when it spots
one. Delete it and let the application answer, or edit it by hand — but remember
that `robots.txt` only asks crawlers not to *visit*. The `noindex` above is what
actually keeps a page out of results.

## Turn the generator off

The code generator writes PHP files into your application. It is only available
while the application is in debug mode, and it must stay that way.

In your `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
```

With those set, the generator's routes are never registered at all — there is no
page to find and nothing to guess.

## Check the accounts

- Delete or rename any demonstration accounts.
- Make sure at least one real person holds a super admin role.
- Check that a limited role really cannot reach the admin screens — sign in as
  one in a private window and try typing `%%accessPath%%/users` directly.

## Check the uploads work

Photos, logos and favicons are stored on the public disk, which needs a symlink:

```bash
php artisan storage:link
```

Without it the files upload fine and simply never appear. If your logo is
missing, this is almost always why.

## Speed it up

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> [!WARNING]
> Run these **after** your `.env` is final. Cached config ignores later `.env`
> changes, and the resulting "why is it still using the old database" is one of
> the most confusing hours you can spend. `php artisan optimize:clear` undoes
> all three.

## A last pass

| Check | Where |
|---|---|
| System name, logo and favicon are yours | [Settings](settings) |
| The menu has no leftovers | [The menu](menu) |
| Roles do what you think | [Users and roles](users-and-roles) |
| Announcements and ribbons are turned off | [Settings](settings) |
| The audit trail is being pruned | [The audit trail](audit-trail) |
| Search engines allowed, description written | Settings → Search engines |
