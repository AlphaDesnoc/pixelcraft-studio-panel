<?php

namespace App\Support;

use App\Models\Task;

class TaskKanbanPayload
{
    public static function from(Task $task): array
    {
        $task->loadMissing([
            'tags',
            'dependencies:id,status,title',
            'linkedBug',
            'checklists.items',
            'comments.user:id,name',
            'attachments',
        ]);

        $linked = $task->linkedBug;

        $checklistDone = 0;
        $checklistTotal = 0;
        foreach ($task->checklists as $cl) {
            foreach ($cl->items as $it) {
                $checklistTotal++;
                if ($it->is_done) {
                    $checklistDone++;
                }
            }
        }

        return [
            'id' => $task->id,
            'list_id' => $task->list_id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'position' => $task->position,
            'progress' => (int) $task->progress,
            'assignee_id' => $task->assignee_id,
            'start_date' => optional($task->start_date)?->toDateString(),
            'due_date' => optional($task->due_date)?->toDateString(),
            'is_overdue' => $task->isOverdue(),
            'archived_at' => optional($task->archived_at)?->toIso8601String(),
            'recurrence_rule' => $task->recurrence_rule,
            'estimated_minutes' => $task->estimated_minutes,
            'logged_minutes' => (int) ($task->logged_minutes ?? 0),
            'auto_archive_at' => optional($task->auto_archive_at)?->toDateString(),
            'dependency_ids' => $task->dependencies->pluck('id')->values()->all(),
            'is_blocked' => $task->isBlocked(),
            'tags' => $task->tags->map(fn ($tg) => $tg->toPayload())->values(),
            'checklist_progress' => [
                'done' => $checklistDone,
                'total' => $checklistTotal,
            ],
            'linked_bug' => $linked ? [
                'id' => $linked->id,
                'title' => $linked->title,
                'url' => null,
            ] : null,
        ];
    }
}
