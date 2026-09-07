<?php

namespace IsProject\Framework\Console;

use Illuminate\Console\Command;
use IsProject\Framework\Models\Activity;

/**
 * An activity log grows with every sign-in and never shrinks on its own.
 */
class ActivityPruneCommand extends Command
{
    protected $signature = 'isproject:activity-prune
        {--days= : Keep this many days (default: isproject.activity.retention_days)}
        {--pretend : Count what would go, delete nothing}';

    protected $description = 'Delete activity log entries older than the retention period';

    public function handle(): int
    {
        // ?? not ?:, because "0" is falsy. With ?: a --days=0 would silently
        // become the configured default instead of being refused below, which
        // is the opposite of what somebody typing 0 is asking for.
        $days = (int) ($this->option('days') ?? config('isproject.activity.retention_days', 365));

        if ($days < 1) {
            $this->components->error('Retention must be at least one day. Nothing was deleted.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = Activity::query()->where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($this->option('pretend')) {
            $this->components->info("{$count} entries are older than {$days} days. Nothing was deleted.");

            return self::SUCCESS;
        }

        // Chunked so a table that has been left for a year does not become one
        // enormous delete that locks everything reading it.
        $deleted = 0;

        while (($batch = $query->clone()->limit(1000)->delete()) > 0) {
            $deleted += $batch;
        }

        $this->components->info("Deleted {$deleted} entries older than {$days} days.");

        return self::SUCCESS;
    }
}
