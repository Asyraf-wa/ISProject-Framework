---
title: Installing it as an app
icon: box
summary: Putting the system on a phone home screen, and what happens when the connection drops.
---

# Installing it as an app

%%app%% can be installed like an app: an icon on a phone home screen or a
desktop, opening in its own window with no address bar.

It is off until somebody switches it on in
[Settings → Install as an app](%%settingsPath%%).

## Installing it

Once it is switched on:

| Where | How |
|---|---|
| Android, Chrome | A prompt appears, or **⋮ → Add to Home screen** |
| iPhone, Safari | **Share → Add to Home Screen**. Safari never prompts by itself |
| Desktop Chrome or Edge | An install icon at the right of the address bar |

There is nothing to download and no app store involved. The icon points at the
same site you were already using.

## What you get offline

Not much, and that is deliberate.

If you lose your connection, you get a plain **You are offline** page instead of
the browser's error page, and it reloads by itself when the connection comes
back. What you do not get is the last page you were looking at.

> [!IMPORTANT]
> Pages are never stored on the device. Every screen here is behind sign-in and
> filtered by your role, and these are often shared machines — a stored page
> could show the next person at that computer whatever you were working on.
> Only the stylesheet, the scripts and the app icon are kept.

So this is not an offline-first system. You can open it without a connection and
be told so clearly; you cannot browse your data on a train.

## Switching it on (for whoever runs the system)

Two things beyond the switch itself:

**An app icon.** A square PNG, 512×512 or larger. Anything smaller than 192px or
not square is refused, because a browser will not use it. An SVG is never
accepted — see [Settings](settings) for why that rule applies to every upload.

**HTTPS.** Browsers refuse to install an app, or to run the service worker
behind it, on a plain `http://` address. The one exception is `localhost`, which
is why this works while you develop and stops the moment you deploy to a plain
http host. See [Going live](going-live).

The settings screen lists whichever of these is still missing once you switch
the feature on.

## Switching it off again

This is worth understanding, because it is not obvious.

The thing that makes an installed app work is a **service worker** — a small
script the browser keeps and runs even when no page of the site is open. Once a
browser has one, it does not go away because the site stopped asking for it. It
stays, with its stored files, until it is told to remove itself.

So turning the switch off does not merely stop offering the app. It serves a
replacement worker whose only job is to delete its own stored files and
unregister itself, and every page carries a script that does the same. Browsers
check for a new worker on navigation and at least once a day, so devices that
installed it clean themselves up without anybody visiting them.

> [!NOTE]
> If you had it installed and switched it off, the icon on your home screen
> stays until you delete it by hand — a home screen shortcut belongs to your
> phone, not to the site. Tapping it will simply open the site in a browser.

## When the app looks out of date

An installed app keeps its stylesheet and scripts on the device. After an
upgrade, the new worker takes over on the next visit and clears the old files.

If something still looks stale, close every window of the app and reopen it. As
a last resort, uninstall it from the home screen and install it again — nothing
of yours is stored in it, so there is nothing to lose.
