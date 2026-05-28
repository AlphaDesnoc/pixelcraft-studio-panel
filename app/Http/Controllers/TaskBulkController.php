<?php

namespace App\Http\Controllers;

use App\Events\TaskKanbanUpdated;
use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskBulkController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:archive,assign,tag'],
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
            'assignee_id' => ['nullable', 'integer'],
            'tag_id' => ['nullable', 'integer'],
        ]);

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $validated['task_ids'])
            ->get();

        foreach ($tasks as $task) {
            match ($validated['action']) {
                'archive' => $task->update(['archived_at' => now()]),
                'assign' => $task->update(['assignee_id' => $validated['assignee_id'] ?? null]),
                'tag' => $validated['tag_id']
                    ? $task->tags()->syncWithoutDetaching([(int) $validated['tag_id']])
                    : null,
            };
        }

        TaskKanbanUpdated::dispatch($project, 'bulk', [
            'action' => $validated['action'],
            'task_ids' => $validated['task_ids'],
        ], $request->user()->id);

        return $this->apiOrBack($request, [
            'updated' => $tasks->count(),
        ]);
    }
}
