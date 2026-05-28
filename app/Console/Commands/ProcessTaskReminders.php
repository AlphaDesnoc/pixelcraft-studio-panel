<?php

namespace App\Console\Commands;

use App\Models\TaskReminder;
use App\Models\UserNotification;
use App\Support\PanelNotifier;
use Illuminate\Console\Command;

class ProcessTaskReminders extends Command
{
    protected $signature = 'panel:task-reminders';

    protected $description = 'Send due task reminders';

    public function handle(): int
    {
        $due = TaskReminder::query()
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->with(['task.project', 'user'])
            ->get();

        foreach ($due as $reminder) {
            $task = $reminder->task;
            $project = $task?->project;
            if (! $task || ! $project) {
                $reminder->update(['sent_at' => now()]);
                continue;
            }

            PanelNotifier::send(
                $reminder->user_id,
                UserNotification::TYPE_TASK_REMINDER,
                'Rappel de tâche',
                $task->title,
                route('projects.show', $project->slug).'?tab=kanban&task='.$task->id,
                ['project_id' => $project->id, 'task_id' => $task->id],
            );

            $reminder->update(['sent_at' => now()]);
        }

        $this->info('Processed '.$due->count().' task reminders');

        return self::SUCCESS;
    }
}
