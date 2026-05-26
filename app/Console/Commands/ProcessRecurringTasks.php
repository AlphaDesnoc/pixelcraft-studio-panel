<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskList;
use Illuminate\Console\Command;

class ProcessRecurringTasks extends Command
{
    protected $signature = 'panel:recurring-tasks';

    protected $description = 'Crée les occurrences des tâches récurrentes';

    public function handle(): int
    {
        $sources = Task::query()
            ->whereNotNull('recurrence_rule')
            ->whereNull('recurrence_source_id')
            ->whereNull('archived_at')
            ->where(function ($q) {
                $q->whereNull('next_recurrence_at')
                    ->orWhere('next_recurrence_at', '<=', now());
            })
            ->get();

        foreach ($sources as $source) {
            $clone = $source->replicate(['position', 'archived_at', 'completed_at', 'next_recurrence_at']);
            $clone->recurrence_source_id = $source->id;
            $clone->recurrence_rule = null;
            $clone->title = $source->title;
            $clone->position = ((int) Task::where('list_id', $source->list_id)->max('position')) + 1;
            $clone->status = Task::STATUS_TODO;
            $clone->progress = 0;
            $clone->completed_at = null;
            $clone->archived_at = null;
            $clone->save();

            $source->next_recurrence_at = match ($source->recurrence_rule) {
                'weekly' => now()->addWeek(),
                'monthly' => now()->addMonth(),
                default => now()->addWeek(),
            };
            $source->save();
        }

        $this->info("Recurring tasks processed: {$sources->count()}");

        return self::SUCCESS;
    }
}
