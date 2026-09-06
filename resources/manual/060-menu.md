---
title: The menu
icon: menu
summary: Adding a generated module to the sidebar, reordering it, and nesting one level.
---

# The menu

**`%%menuPath%%`**, under *System*. This is where the sidebar comes from.

## "I generated a module and it is not in the sidebar"

This is the most common surprise, and it is not a bug. Generating a module writes
**code**; your menu is **data**. The generator does not reach into your menu, in
the same way it does not reach into your database.

It does not need to. The route it created is enough to find the module
afterwards, so the menu screen lists every module with a listing page that
nothing links to, under **modules not in the menu**. One click adds it, with the
matching permission already filled in. Then drag it where you want it.

## Where the sidebar comes from

Two possible sources, and one blunt rule:

- **While the menu items table is empty**, the sidebar comes from the array in
  `config/isproject.php`.
- **As soon as it holds a single row**, the table is the menu. Config is ignored
  entirely.

Nothing in between. A menu half in config and half in the database would quietly
reintroduce a config entry among rows somebody had curated, with no way to remove
it from the screen.

So the first time you open the screen it offers **Manage the menu here**, which
copies the config menu in exactly as it stands — the sidebar looks identical
afterwards, because it is a copy, not an edit. **Back to config** empties the
table and hands control back.

## The four kinds of entry

| Kind | Points at | Notes |
|---|---|---|
| Page in this system | A named route, like `books.index` | Survives URL changes. What a generated module wants |
| Path | `/reports` | Must start with a slash |
| Another website | `https://…` | Opens in a new tab. Never highlighted as the current page |
| Heading | Nothing | Labels the group under it |

Each one takes an icon, an optional permission, an optional badge, and a parent.

> [!NOTE]
> A heading with nothing under it — because everything in that group was hidden
> by permissions — is dropped rather than left floating above empty space.

## Reordering

Drag a row by the handle on its left. Drop it onto the indented area under
another row to make it a sub-item.

Or use the up and down arrows. They do exactly the same thing, and they work from
a keyboard, on a touch screen, and with JavaScript switched off.

The menu goes **two levels deep**. A third would be unusable in a rail this
narrow, so it is refused — as is nesting a row under itself, or putting items
under a heading. A refused move changes nothing and the screen reloads, so what
you see is the order that really holds.

## Hiding without deleting

The eye button switches a row off. It disappears from the sidebar but stays on
this screen, marked *Hidden*, keeping its icon, its place and its settings.
Useful while a module is half-built.

## Permissions

The **Only show to people who may** field takes a permission name, normally the
same route the entry points at. Leave it empty and everybody signed in sees the
entry.

Hiding a link is not the same as protecting a page — see
[Users and roles](users-and-roles).

> [!TIP]
> Upgraded and a new framework screen is missing from your menu? The
> "modules not in the menu" list only offers modules *you* generated. Add a
> framework screen with **New item → Page in this system** and pick its route.
