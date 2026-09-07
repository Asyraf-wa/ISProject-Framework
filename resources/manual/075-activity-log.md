---
title: The activity log
icon: eye
summary: Who signed in, who failed to, and who was locked out — and how it differs from the audit trail.
---

# The activity log

**`%%activityPath%%`**, under *System*. What people **did**: signed in, signed
out, failed to sign in, were locked out, changed a password.

## Activity or audit?

They answer different questions, which is why they are separate screens.

| | Activity log | [Audit trail](audit-trail) |
|---|---|---|
| Answers | Who has been trying to get in | Who changed this record |
| A typical row | "Sign-in failed, tried admin@… , from 10.0.0.4" | "Aisha changed Product #12 price from 40 to 45" |
| Has a user | Often not — the interesting rows are attempts by people who are *not* signed in | Always |
| Read when | An account looks compromised | A record looks wrong |

Looking for both at once usually means you want the activity log first: it tells
you whose session to distrust, and the audit trail then tells you what that
session touched.

## Security events

The button at the top right narrows the list to the events worth a second look:
failed sign-ins, lockouts, password resets and password changes.

> [!TIP]
> Several failed sign-ins from one address within a minute is the pattern worth
> noticing. One failure is somebody's caps lock.

## What is not recorded

| Left out | Why |
|---|---|
| Passwords | Dropped entirely, not masked. Unlike a changed field in the audit trail, there is no version of a password worth keeping |
| Tokens, secrets, API keys | Same rule, and it applies to anything you log yourself |

A failed sign-in is **not** filed against the account somebody tried to reach.
Filtering by a person shows what *they* did, never what was done *to* them by
somebody else — the address that was attempted is shown on the row instead.

## Logging your own

Anything your application does can go in the same list:

```php
isproject_activity('invoice.exported', 'Exported the March invoices', ['count' => 42]);
isproject_activity('order.shipped', 'Marked it shipped', [], $order);
```

The event is any string you like. The third argument is extra detail shown on
the entry; the fourth optionally links the row to a record.

> [!IMPORTANT]
> Logging never breaks the thing it is watching. If the write fails — the table
> is missing, the disk is full — the error is reported and your code carries on.
> A log that can take down a sign-in is worse than no log.

## Keeping it from growing forever

It grows with every sign-in. Prune it:

```bash
php artisan isproject:activity-prune --days=90 --pretend   # count what would go
php artisan isproject:activity-prune
```

Scheduled, in `routes/console.php`:

```php
Schedule::command('isproject:activity-prune')->daily();
```

## Turning it off

```dotenv
ISPROJECT_ACTIVITY=false
```

Or silence one noisy event while keeping the rest, in `config/isproject.php`:

```php
'activity' => ['ignored_events' => ['logout']],
```
