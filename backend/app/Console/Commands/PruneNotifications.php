<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete read notifications older than the retention window';

    public function handle(): int
    {
        $retentionDays = (int) config('reminders.retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        $deleted = Notification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} read notification(s) older than {$retentionDays} days.");

        return Command::SUCCESS;
    }
}
