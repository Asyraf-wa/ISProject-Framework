---
title: Settings
icon: settings
summary: Naming the site, logos and favicons, announcements, the corner ribbon, and clearing caches.
---

# Settings

**`%%settingsPath%%`**, under *System*. Everything here is stored in the
database, so changing it needs no deployment and no file editing.

Settings win over the config file, which wins over the framework default. The
screen a lecturer edits is the one that decides.

## General

| Setting | What it changes |
|---|---|
| System name | The browser tab, the sidebar, the sign-in screen |
| Tagline | Shown under the name on the sign-in screen |
| Support email | The address in the footer |
| Footer text | The line along the bottom of every page |

## Appearance

| Setting | What it changes |
|---|---|
| Accent colour | Buttons, links, the current menu item, checkboxes and the first chart series |
| Brand icon | The mark beside the name when no logo is uploaded |
| Default theme | Light, dark, or follow the visitor's system — the starting point for somebody who has not chosen |
| Logo | Replaces the mark and name in the sidebar |
| Favicon | The little icon in the browser tab |

> [!NOTE]
> Uploads accept PNG, JPEG, WebP and ICO — **never SVG**. An SVG can carry
> script, and these files are served from your own address, so accepting one
> would let anybody who can reach this screen run code in everyone's browser.

The accent is a fixed set of seven rather than a colour picker, and each swatch
shows its contrast in the light and dark themes. Every one is legible in both;
a picker could not promise that, and the failure would be silent — a colour can
look excellent and still leave white button text unreadable on it.

## Sign in

| Setting | Effect |
|---|---|
| Allow self-registration | Adds a "Create an account" link. Off means accounts are made on the Users screen only |
| Allow Google sign-in | Adds a "Continue with Google" button |
| Google may create accounts | Off means Google can only sign in somebody who *already* has an account — usually what a class roster wants |

The Google toggle stays **disabled** until `GOOGLE_CLIENT_ID` and
`GOOGLE_CLIENT_SECRET` are in the application's `.env`. The screen names whichever
one is missing. A switch that turns on a flow which can only end at a Google
error page is worse than no switch, so this is enforced on the server too.

## Announcement bar

A strip across the top of every page. Give it a message, optionally a link, and a
tone (grey, blue, green, amber, red).

Readers can close it. It then stays gone for the number of hours you set —
one hour by default — and comes back afterwards if the announcement is still
running. **Show until** ends it on a date regardless.

> [!TIP]
> Edit the wording and it reappears immediately, even for somebody who closed it
> a minute ago. Dismissal is remembered against *that message*, not against
> "the announcement bar" in general — which is the whole point of announcing
> something new.

## Corner ribbon

A small diagonal banner in the top right with text and a link. Useful for
"Demo system" or "Coursework submission closes Friday".

Both the ribbon and the announcement accept only addresses starting `http://`,
`https://` or `/`. Anything else is refused.

## Search engines

Covered in [Going live](going-live).

## Regional

Timezone and date format. These change how dates are shown and stored across
every screen, so set them once at the start of a project rather than halfway
through.

## Clearing caches

At the bottom of the screen. Laravel caches configuration, routes, views and
application data; when something you changed stubbornly refuses to appear, this
is usually why.

| Button | Clears |
|---|---|
| Application cache | Cached values your code stored |
| Views | Compiled Blade templates |
| Config | The cached configuration file |
| Routes | The cached route table |
| Everything | All of the above |

> [!WARNING]
> Clearing caches on a live system makes the next few requests slower while
> everything is rebuilt. It is safe, but it is not free.
