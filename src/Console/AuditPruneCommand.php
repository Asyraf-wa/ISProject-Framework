<?php

namespace IsProject\Framework\Console;

use Illuminate\Console\Command;
use IsProject\Framework\Models\Audit;

/**
 * Removes audit rows past their retention period.
 *
 * An audit table grows with every save and nothing ever deletes from it, so a
 * busy application will fill a disk given long enough. Schedule this:
 *
 *     Schedule::command('isproject:audit-prune')->daily();
 */
class AuditPruneCommand extends Command
{
    protected $signature = 'isproject:audit-prune
        {--days= : Keep this many days (default: config isproject.audit.retention_days)}
        {--pretend : Report what would go, delete nothing}';

    protected $description = 'Delete audit trail entries older than the retention period';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('isproject.audit.retention_days');

        if ($days === null || $days === '') {
            $this->components->warn('No retention period is set, so nothing was removed.');
            $this->components->info('Set isproject.audit.retention_days, or pass --days=90.');

            return self::SUCCESS;
        }

        $days = (int) $days;

        if ($days < 1) {
            $this->components->error('--days must be at least 1. Refusing to delete the whole trail.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = Audit::query()->where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->components->info("Nothing older than {$cutoff->toDateString()}.");

            return self::SUCCESS;
        }

        if ($this->option('pretend')) {
            $this->components->info("Would delete {$count} entries older than {$cutoff->toDateString()}.");

            return self::SUCCESS;
        }

        // Chunked: a year of audit rows can be millions, and one DELETE that
        // large will hold locks long enough to be noticed.
        $deleted = 0;

        do {
            $batch = $query->clone()->limit(1000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->components->info("Deleted {$deleted} entries older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
