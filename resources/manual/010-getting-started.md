---
title: Getting started
icon: home
summary: What this system is, how to sign in, and how to find your way around the screen.
---

# Getting started

%%app%% is built on the **isproject/framework** teaching scaffold. You will meet it
wearing two hats: as somebody *using* the screens, and as somebody *building* new
ones. This manual covers both, in that order.

If you only ever read one other chapter, make it
[Your first module](first-module) — it takes an empty database table to a working
screen in about five minutes.

## Signing in

Go to `/login` and use the email address and password you were given.

If your lecturer has switched it on, there is also a **Continue with Google**
button. Using it once links that Google account to your existing account, so
afterwards either route signs you in as the same person.

> [!NOTE]
> Forgotten your password? The **Forgotten your password?** link on the sign-in
> screen emails you a reset link. In a development environment those emails do
> not really leave the machine — ask your lecturer where they land.

## The parts of the screen

| Part | What it is |
|---|---|
| The rail down the left | The menu. Groups of links under headings |
| The bar across the top | The page title, the theme switch, and your account |
| Your name, top right | Your profile, and the way out |

The rail collapses to icons if you want more room — use the button beside the
page title. On a phone it slides in over the page instead. Whichever you choose
is remembered on this device.

## Light and dark

The moon and sun button in the top bar cycles between **light**, **dark** and
**follow my system**. The choice is remembered in this browser, and the page is
painted in the right theme before it first appears, so there is no flash of the
wrong one.

## Your profile

Click your name in the top bar, then **Profile**. From there you can:

- change your name and email address,
- upload a photo of yourself — PNG, JPEG or WebP up to 2 MB, shown as a circle,
- change your password (you must know the current one),
- disconnect a linked Google account.

Your photo replaces your initials everywhere the system shows you: the top bar,
the users list, and this screen.

## When something looks wrong

- **A menu link is missing.** It may be hidden from you by permissions, or the
  page may not exist yet. See [The menu](menu).
- **A screen says 403.** You are signed in, but your role does not include that
  page. See [Users and roles](users-and-roles).
- **A change did not stick.** Check the flash message at the top of the screen —
  a red one says why it was refused.

## Where to go next

| If you want to… | Read |
|---|---|
| Build a new screen | [Your first module](first-module) |
| Understand the screens you get | [The generated screens](generated-screens) |
| Control who may do what | [Users and roles](users-and-roles) |
| Rename the site, upload a logo | [Settings](settings) |
| See who changed a record | [The audit trail](audit-trail) |
