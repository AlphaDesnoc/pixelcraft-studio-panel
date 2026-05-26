<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;

class AutoArchiveTasks extends Command
{
    protected $signature = 'panel:auto-archive-tasks';

    protected $description = 'Archive les tâches terminées dont la date auto-archive est dépassée';

    public function handle(): int
    {
        $count = Task::query()
            ->whereNull('archived_at')
            ->where('status', Task::STATUS_DONE)
            ->whereNotNull('auto_archive_at')
            ->where('auto_archive_at', '<=', now())
            ->update(['archived_at' => now()]);

        $this->info("Tasks auto-archived: {$count}");

        return self::SUCCESS;
    }
}
