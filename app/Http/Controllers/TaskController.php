<?php

namespace App\Http\Controllers;

use App\Events\TaskKanbanUpdated;
use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ActivityLogger;
use App\Support\PanelNotifier;
use App\Support\TaskKanbanPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    use EnsuresProjectFeature;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');

        $validated = $request->validate([
            'list_id' => ['required', 'integer', Rule::exists('task_lists', 'id')->where('project_id', $project->id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        $list = TaskList::findOrFail($validated['list_id']);
        $position = (int) Task::where('list_id', $list->id)->max('position') + 1;

        $task = Task::create([
            'project_id' => $project->id,
            'list_id' => $list->id,
            'assignee_id' => $validated['assignee_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? Task::PRIORITY_MEDIUM,
            'status' => $list->status_kind,
            'position' => $position,
            'progress' => $this->progressForList($project, $list->id),
            'start_date' => $validated['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'completed_at' => $list->status_kind === TaskList::STATUS_DONE ? now() : null,
        ]);

        $this->logTask(
            $project,
            $request->user(),
            'task_created',
            sprintf('%s a créé « %s »', $request->user()->name, $task->title),
            $task,
        );

        if (! empty($validated['assignee_id']) && (int) $validated['assignee_id'] !== (int) $request->user()->id) {
            PanelNotifier::send(
                (int) $validated['assignee_id'],
                UserNotification::TYPE_TASK_ASSIGNED,
                'Tâche assignée',
                sprintf('%s vous a assigné « %s »', $request->user()->name, $validated['title']),
                route('projects.show', $project->slug).'?tab=kanban',
                ['project_id' => $project->id],
            );
        }

        $this->broadcastKanban($project, 'created', [
            'task' => TaskKanbanPayload::from($task->fresh()),
            'list_id' => $task->list_id,
        ], $request->user()->id);

        return back();
    }

    public function update(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        $this->ensureBelongs($project, $task);

        $validated = $request->validate([
            'list_id' => ['sometimes', 'integer', Rule::exists('task_lists', 'id')->where('project_id', $project->id)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', Rule::in(array_keys(Task::PRIORITIES))],
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'recurrence_rule' => ['sometimes', 'nullable', 'string', Rule::in(['weekly', 'monthly'])],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60000'],
            'logged_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60000'],
            'auto_archive_at' => ['sometimes', 'nullable', 'date'],
            'dependency_ids' => ['sometimes', 'array'],
            'dependency_ids.*' => ['integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
        ]);

        if (array_key_exists('dependency_ids', $validated)) {
            $ids = collect($validated['dependency_ids'])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id !== $task->id)
                ->unique()
                ->values()
                ->all();
            $task->dependencies()->sync($ids);
            unset($validated['dependency_ids']);
        }

        if (array_key_exists('list_id', $validated) && $validated['list_id'] !== $task->list_id) {
            $newList = TaskList::findOrFail($validated['list_id']);
            $validated['status'] = $newList->status_kind;
            $validated['completed_at'] = $newList->status_kind === TaskList::STATUS_DONE ? now() : null;
            $validated['progress'] = $this->progressForList($project, $newList->id);
        }

        if (array_key_exists('recurrence_rule', $validated) && $validated['recurrence_rule']) {
            $task->next_recurrence_at = $task->next_recurrence_at ?? now()->addWeek();
        }

        $previousAssignee = $task->assignee_id;
        $task->update(collect($validated)->except('dependency_ids')->all());

        if (! empty($validated)) {
            $this->logTask(
                $project,
                $request->user(),
                'task_updated',
                sprintf('%s a modifié « %s »', $request->user()->name, $task->title),
                $task,
                ['fields' => array_keys($validated)],
            );
        }

        if (
            array_key_exists('assignee_id', $validated)
            && $validated['assignee_id']
            && (int) $validated['assignee_id'] !== (int) $previousAssignee
            && (int) $validated['assignee_id'] !== (int) $request->user()->id
        ) {
            PanelNotifier::send(
                (int) $validated['assignee_id'],
                UserNotification::TYPE_TASK_ASSIGNED,
                'Tâche assignée',
                sprintf('%s vous a assigné « %s »', $request->user()->name, $task->title),
                route('projects.show', $project->slug).'?tab=kanban',
                ['project_id' => $project->id, 'task_id' => $task->id],
            );
        }

        $this->broadcastKanban($project, 'updated', [
            'task' => TaskKanbanPayload::from($task->fresh()),
            'list_id' => $task->list_id,
        ], $request->user()->id);

        return back();
    }

    public function duplicate(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        $this->ensureBelongs($project, $task);

        $clone = null;

        DB::transaction(function () use ($task, &$clone) {
            $task->load('tags');

            $nextPosition = ((int) Task::where('list_id', $task->list_id)->max('position')) + 1;

            $clone = $task->replicate(['position', 'archived_at']);
            $clone->title = $task->title.' (copie)';
            $clone->position = $nextPosition;
            $clone->archived_at = null;
            $clone->save();

            $clone->tags()->sync($task->tags->pluck('id')->all());
        });

        if ($clone) {
            $this->logTask(
                $project,
                $request->user(),
                'task_duplicated',
                sprintf('%s a dupliqué « %s »', $request->user()->name, $task->title),
                $clone,
                ['source_task_id' => $task->id],
            );

            $this->broadcastKanban($project, 'created', [
                'task' => TaskKanbanPayload::from($clone->fresh()),
                'list_id' => $clone->list_id,
            ], $request->user()->id);
        }

        return back();
    }

    public function archive(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        $this->ensureBelongs($project, $task);

        $task->update(['archived_at' => now()]);

        $this->logTask(
            $project,
            $request->user(),
            'task_archived',
            sprintf('%s a archivé « %s »', $request->user()->name, $task->title),
            $task,
        );

        $this->broadcastKanban($project, 'archived', [
            'task_id' => $task->id,
            'list_id' => $task->list_id,
        ], $request->user()->id);

        return back();
    }

    public function unarchive(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        $this->ensureBelongs($project, $task);

        $task->update(['archived_at' => null]);

        $this->logTask(
            $project,
            $request->user(),
            'task_unarchived',
            sprintf('%s a désarchivé « %s »', $request->user()->name, $task->title),
            $task,
        );

        $this->broadcastKanban($project, 'updated', [
            'task' => TaskKanbanPayload::from($task->fresh()),
            'list_id' => $task->list_id,
        ], $request->user()->id);

        return back();
    }

    public function destroy(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        $this->ensureBelongs($project, $task);

        $taskId = $task->id;
        $listId = $task->list_id;
        $taskTitle = $task->title;

        $this->logTask(
            $project,
            $request->user(),
            'task_deleted',
            sprintf('%s a supprimé « %s »', $request->user()->name, $taskTitle),
            $task,
        );

        DB::transaction(function () use ($task) {
            $listId = $task->list_id;
            $task->delete();

            if ($listId) {
                Task::where('list_id', $listId)
                    ->orderBy('position')
                    ->get()
                    ->values()
                    ->each(fn ($t, $i) => $t->update(['position' => $i]));
            }
        });

        $this->broadcastKanban($project, 'deleted', [
            'task_id' => $taskId,
            'list_id' => $listId,
        ], $request->user()->id);

        return back();
    }

    public function move(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        $this->ensureBelongs($project, $task);

        $validated = $request->validate([
            'list_id' => ['required', 'integer', Rule::exists('task_lists', 'id')->where('project_id', $project->id)],
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'distinct'],
        ]);

        $oldListId = $task->list_id;

        DB::transaction(function () use ($project, $task, $validated, $request, &$oldListId) {
            $newList = TaskList::findOrFail($validated['list_id']);
            $task->loadMissing('list');
            $oldListId = $task->list_id;
            $oldListName = $task->list?->name;

            $task->list_id = $newList->id;
            $task->status = $newList->status_kind;
            $task->completed_at = $newList->status_kind === TaskList::STATUS_DONE
                ? ($task->completed_at ?? now())
                : null;
            $task->progress = $this->progressForList($project, $newList->id);

            $task->save();

            $this->logTask(
                $project,
                $request->user(),
                'task_moved',
                sprintf(
                    '%s a déplacé « %s » de %s vers %s',
                    $request->user()->name,
                    $task->title,
                    $oldListName ?? '—',
                    $newList->name,
                ),
                $task,
                ['from_list_id' => $oldListId, 'to_list_id' => $newList->id],
            );

            foreach ($validated['order'] as $position => $id) {
                Task::whereKey($id)
                    ->where('list_id', $newList->id)
                    ->update(['position' => $position]);
            }

            if ($oldListId && $oldListId !== $newList->id) {
                Task::where('list_id', $oldListId)
                    ->orderBy('position')
                    ->get()
                    ->values()
                    ->each(fn ($t, $i) => $t->update(['position' => $i]));
            }
        });

        $task->refresh();

        $this->broadcastKanban($project, 'moved', [
            'task_id' => $task->id,
            'list_id' => (int) $validated['list_id'],
            'order' => array_values(array_map('intval', $validated['order'])),
            'old_list_id' => $oldListId,
            'task' => TaskKanbanPayload::from($task),
        ], $request->user()->id);

        return back();
    }

    private function logTask(
        Project $project,
        User $user,
        string $action,
        string $message,
        Task $task,
        array $meta = [],
    ): void {
        $task->loadMissing('list:id,rank_id');

        ActivityLogger::log(
            $project,
            $user,
            $action,
            $message,
            $task,
            array_merge($meta, [
                'rank_id' => $task->list?->rank_id,
                'task_title' => $task->title,
            ]),
        );
    }

    private function broadcastKanban(Project $project, string $action, array $payload, ?int $actorId): void
    {
        TaskKanbanUpdated::dispatch($project, $action, $payload, $actorId);
    }

    private function ensureBelongs(Project $project, Task $task): void
    {
        abort_unless($task->project_id === $project->id, 404);
    }

    private function progressForList(Project $project, int $listId): int
    {
        $ids = $project->lists()->orderBy('position')->pluck('id')->all();
        $count = count($ids);
        if ($count <= 1) {
            return 0;
        }
        $idx = array_search($listId, $ids, true);
        if ($idx === false) {
            return 0;
        }

        return (int) round(($idx / ($count - 1)) * 100);
    }
}
