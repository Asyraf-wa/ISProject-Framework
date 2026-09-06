---
title: Your first module
icon: box
summary: From an empty database table to a working screen, in three steps.
---

# Your first module

The order matters, and it is the opposite of what people expect: **the database
table comes first.** The generator reads a real table and writes code to match
it. It never invents columns, and it never changes your schema.

Say you are building a library and you want to manage books.

## 1. Create the table

Write a migration the normal Laravel way:

```bash
php artisan make:migration create_books_table
```

Fill it in:

```php
Schema::create('books', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('author');
    $table->string('isbn', 20)->nullable();
    $table->date('published_on')->nullable();
    $table->foreignId('category_id')->nullable()->constrained();
    $table->boolean('is_available')->default(true);
    $table->timestamps();
});
```

Then run it:

```bash
php artisan migrate
```

> [!IMPORTANT]
> Run the migration before the next step. The generator introspects the table
> that actually exists — if it is not there, there is nothing to read.

## 2. Generate the module

```bash
php artisan isproject:crud Book
```

The model name is singular and StudlyCase. The table is guessed from it
(`Book` → `books`); pass `--table=` if yours is named differently.

That one command writes:

| File | What it is |
|---|---|
| `app/Models/Book.php` | The model, with fillable, casts and relations filled in |
| `app/Http/Controllers/BookController.php` | Index, create, store, show, edit, update, destroy, report |
| `app/Http/Requests/StoreBookRequest.php` | Validation rules derived from the column types |
| `app/Http/Requests/UpdateBookRequest.php` | The same, adjusted for editing |
| `resources/views/books/*.blade.php` | Index, create, edit, show and report screens |
| `database/factories/BookFactory.php` | For seeding and tests |
| `app/Policies/BookPolicy.php` | Per-action authorisation |
| `routes/web.php` | The resource routes, appended |

Useful options:

```bash
php artisan isproject:crud Book --only=model,controller   # just those
php artisan isproject:crud Book --except=policy,factory   # everything but those
php artisan isproject:crud Book --force                   # overwrite what exists
```

> [!TIP]
> Prefer clicking to typing? The same thing is on the **Generator** screen under
> *Development* in the menu, with the tables listed for you. It is only available
> while the application is in debug mode.

## 3. Put it in the menu

Generating a module does not touch your menu — it writes code, and your menu is
data. Go to [%%menuPath%%](%%menuPath%%) and the new module is waiting to be added
under **modules not in the menu**. One click adds it with the right permission
already set.

See [The menu](menu) for the rest.

## 4. Look at it

Visit `/books`. You have a searchable, sortable, paginated list, a create form,
an edit form, a detail screen and a printable report — all reading the columns
you defined.

## What the generator decides from your columns

It is worth knowing why the forms look the way they do, because the answer is
always "because of the migration".

| In your migration | In the generated screens |
|---|---|
| `string('title')` | A required text box |
| `->nullable()` | Not required |
| `text('summary')` | A textarea |
| `boolean('is_available')` | A checkbox, and a Yes/No column |
| `date` / `datetime` | A date picker, formatted in the list |
| `decimal` / `integer` | A number field |
| `foreignId('category_id')` | A dropdown of categories, showing each one's name |
| `enum` or a `->default()` | A select of the allowed values |
| `unique()` | A uniqueness rule that ignores the row being edited |
| `email` in the column name | An email field and an email rule |
| `password` in the column name | Hidden from lists and reports |

So the way to change a form is usually to change the migration and regenerate,
not to hand-edit the Blade file.

## Changing your mind

To remove a module you generated:

```bash
php artisan isproject:crud-remove Book --dry-run   # see what would go
php artisan isproject:crud-remove Book
```

> [!WARNING]
> This deletes the **files** it generated — the model, controller, requests,
> views, factory, policy and the routes it appended. It never touches the
> database table or the rows in it. Dropping the table is a migration you write
> yourself, deliberately.

## Regenerating after a schema change

Add a column, then:

```bash
php artisan migrate
php artisan isproject:crud Book --force
```

`--force` overwrites. Anything you hand-edited in those files goes with it, so if
you have customised a controller, regenerate only what you need:

```bash
php artisan isproject:crud Book --only=requests,views --force
```
