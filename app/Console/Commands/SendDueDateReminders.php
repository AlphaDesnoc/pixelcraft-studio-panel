<?php

namespace App\Console\Commands;

use App\Models\DueDateReminder;
use App\Models\Task;
use App\Models\UserNotification;
use App\Support\PanelNotifier;
use Illuminate\Console\Command;

class SendDueDateReminders extends Command
{
    protected $signature = 'panel:due-reminders';

    protected $description = 'Send due date notifications (today, tomorrow, overdue)';

    public function handle(): int
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $tasks = Task::query()
            ->with(['project:id,name,slug', 'assignee:id'])
            ->whereNotNull('assignee_id')
            ->whereNotNull('due_date')
            ->where('status', '!=', Task::STATUS_DONE)
            ->where(function ($q) use ($today, $tomorrow) {
                $q->whereDate('due_date', $today)
                    ->orWhereDate('due_date', $tomorrow)
                    ->orWhereDate('due_date', '<', $today);
            })
            ->get();

        foreach ($tasks as $task) {
            if (! $task->assignee || ! $task->project) {
                continue;
            }

            $due = $task->due_date->toDateString();
            $url = route('projects.show', $task->project->slug).'?tab=kanban';

            if ($due < $today) {
                $this->notifyOnce($task, UserNotification::TYPE_OVERDUE, 'Tâche en retard', $task->title.' est en retard.', $url);
            } elseif ($due === $today) {
                $this->notifyOnce($task, UserNotification::TYPE_DUE_TODAY, 'Échéance aujourd\'hui', $task->title.' est due aujourd\'hui.', $url);
            } elseif ($due === $tomorrow) {
                $this->notifyOnce($task, UserNotification::TYPE_DUE_TOMORROW, 'Échéance demain', $task->title.' est due demain.', $url);
            }
        }

        return self::SUCCESS;
    }

    private function notifyOnce(Task $task, string $type, string $title, string $body, string $url): void
    {
        $kind = match ($type) {
            UserNotification::TYPE_DUE_TOMORROW => 'tomorrow',
            UserNotification::TYPE_DUE_TODAY => 'today',
            default => 'overdue',
        };

        $exists = DueDateReminder::query()
            ->where('task_id', $task->id)
            ->where('user_id', $task->assignee_id)
            ->where('kind', $kind)
            ->exists();

        if ($exists) {
            return;
        }

        PanelNotifier::send($task->assignee_id, $type, $title, $body, $url, [
            'task_id' => $task->id,
            'project_id' => $task->project_id,
        ]);

        DueDateReminder::query()->create([
            'task_id' => $task->id,
            'user_id' => $task->assignee_id,
            'kind' => $kind,
            'due_date' => $task->due_date,
        ]);
    }
}
