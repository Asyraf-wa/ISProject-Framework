---
title: Users and roles
icon: people
summary: Who exists, what a role may reach, and why the permission list writes itself.
---

# Users and roles

Two screens under *System*: **Users** at `%%accessPath%%/users` and **Roles** at
`%%accessPath%%/roles`.

The idea is ordinary: a person holds one or more **roles**, and a role holds
**permissions**. What is unusual is where the permissions come from.

## The permission list writes itself

A permission is one named route. Nobody types a list of permissions, because the
application already has one — its route table.

So when you run `isproject:crud Book`, the routes it writes *are* the new
permissions. Open the Roles screen, press **Rescan routes**, and `books.index`,
`books.create`, `books.store`, `books.edit`, `books.update`, `books.destroy` and
`books.report` are all there, grouped under **Books**, waiting to be ticked.

From the command line, the same thing:

```bash
php artisan isproject:permissions
```

> [!NOTE]
> Only *named* routes can be permitted. The enforcement works by looking at the
> current route's name, so an unnamed route has nothing to match against.
> Everything the generator writes is named.

## Creating a role

**Roles → New role.** Give it a name, then tick what it may reach. The matrix
puts the reading actions first (List, View, Report) and the changing ones after
(Add, Create, Edit, Update, Delete), because that is the order you think in when
deciding what a role should be able to do.

A useful starting set for a class project:

| Role | Typically holds |
|---|---|
| Administrator | Super admin — everything |
| Staff | List, View, Add, Edit on the data modules |
| Student | List and View only |

## The super admin escape hatch

A role can be marked **super admin**. Someone holding it passes every check
without holding a single permission row.

This exists so a mis-ticked matrix can never lock the last administrator out of
the screen that would fix it. Two rules protect it:

- You cannot delete your own account.
- You cannot remove the **last** super admin, or take the super admin role off
  them. The screen refuses and tells you to promote somebody else first.

## Assigning roles to people

**Users → edit somebody → tick their roles.** A user with no roles at all can
sign in and see their profile, and not much else.

Adding a user here creates the account outright — there is no invitation email.
Set a password and hand it over, or switch on self-registration in
[Settings](settings) and let people make their own.

## What happens when somebody lacks a permission

Two things, and they work together:

1. **The menu hides it.** A link whose permission you do not hold is not drawn,
   so people mostly never meet a page they cannot open.
2. **The route refuses it.** Hiding a link is not security — typing the address
   still has to fail, and it does, with a 403.

> [!TIP]
> Testing permissions is much easier in a second browser profile or a private
> window: sign in as the restricted user there and keep your admin session in
> the main window.

## A route nobody has scanned yet

If a route exists but has never been scanned into the permission list, the
default is to **allow** it. A forgotten rescan turning every new page into a 403
that nobody can explain is the worse failure for a teaching system. Your lecturer
can switch this to deny for production.
