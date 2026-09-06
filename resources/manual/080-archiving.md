---
title: Archiving
icon: inbox
summary: Putting records out of the way without deleting them, and how it differs from the trash.
---

# Archiving

Deleting is rarely what people mean. A finished semester, a discontinued product,
a graduated student — you want them out of the everyday list, not gone.

That is archiving, and it is opt-in per table.

## Archived is not deleted

The framework has both, and they answer different questions.

| | Archived | Deleted (soft) |
|---|---|---|
| Means | Done with, kept on purpose | Removed, recoverable for now |
| Column | `archived_at` | `deleted_at` |
| Shows in the list | No | No |
| Where you find it | The **Archived** tab | The **Trash** tab |
| Normal reason to use it | Housekeeping | A mistake |

Both are on the same screen, as tabs above the table, so there is one place to
look for "a record I cannot see".

## Adding it to a table

Archiving needs a column, and the framework will not alter your schema behind
your back. It writes the migration; you run it.

```bash
php artisan isproject:archivable books
php artisan migrate
```

Then regenerate the module so the screens know about it:

```bash
php artisan isproject:crud Book --force
```

The generator detects the `archived_at` column by itself. There is no flag to
remember and nothing to configure — if the column is there, the tabs, the archive
buttons and the filtering appear.

> [!TIP]
> Planning ahead? Put `$table->timestamp('archived_at')->nullable();` straight
> into the original migration. Generate the module afterwards and archiving is
> there from the start.

## Using it

**Archive** is in the row menu, beside Edit and Delete. Archived records move to
the **Archived** tab, where each one offers **Restore**.

The three tabs are:

| Tab | Shows |
|---|---|
| Active | Everything not archived and not deleted — the default |
| Archived | Records with an `archived_at` |
| Trash | Soft-deleted records, if the model uses soft deletes |

Counts on each tab tell you whether it is worth clicking.

## For a model you wrote yourself

```php
use IsProject\Framework\Concerns\Archivable;

class Book extends Model
{
    use Archivable;
}
```

You then get `$book->archive()`, `$book->unarchive()`, `$book->isArchived()`, and
queries that leave archived rows out unless you ask:

```php
Book::all();                       // active only
Book::withArchived()->get();       // both
Book::onlyArchived()->get();       // archived only
```

Archiving and unarchiving are both written to [the audit trail](audit-trail).
