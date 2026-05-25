<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureCanEdit($request, $project);

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

        Task::create([
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

        return back();
    }

    public function update(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureCanEdit($request, $project);
        $this->ensureBelongs($project, $task);

        $validated = $request->validate([
            'list_id' => ['sometimes', 'integer', Rule::exists('task_lists', 'id')->where('project_id', $project->id)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', Rule::in(array_keys(Task::PRIORITIES))],
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ]);

        if (array_key_exists('list_id', $validated) && $validated['list_id'] !== $task->list_id) {
            $newList = TaskList::findOrFail($validated['list_id']);
            $validated['status'] = $newList->status_kind;
            $validated['completed_at'] = $newList->status_kind === TaskList::STATUS_DONE ? now() : null;
            $validated['progress'] = $this->progressForList($project, $newList->id);
        }

        $task->update($validated);

        return back();
    }

    public function destroy(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureCanEdit($request, $project);
        $this->ensureBelongs($project, $task);

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

        return back();
    }

    public function move(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureCanEdit($request, $project);
        $this->ensureBelongs($project, $task);

        $validated = $request->validate([
            'list_id' => ['required', 'integer', Rule::exists('task_lists', 'id')->where('project_id', $project->id)],
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'distinct'],
        ]);

        DB::transaction(function () use ($project, $task, $validated) {
            $newList = TaskList::findOrFail($validated['list_id']);
            $oldListId = $task->list_id;

            $task->list_id = $newList->id;
            $task->status = $newList->status_kind;
            $task->completed_at = $newList->status_kind === TaskList::STATUS_DONE
                ? ($task->completed_at ?? now())
                : null;
            $task->progress = $this->progressForList($project, $newList->id);

            $task->save();

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

        return back();
    }

    private function ensureCanEdit(Request $request, Project $project): void
    {
        $user = $request->user();
        $isAdmin = $user->is_admin;
        $isMember = $project->members()->whereKey($user->id)->exists();
        abort_unless($isAdmin || $isMember, 403);
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
