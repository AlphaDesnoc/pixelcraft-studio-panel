<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskReminderController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function store(Request $request, Project $project, Task $task): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id, 404);

        $validated = $request->validate([
            'remind_at' => ['required', 'date', 'after:now'],
        ]);

        $reminder = TaskReminder::query()->create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'remind_at' => $validated['remind_at'],
        ]);

        return $this->apiOrBack($request, [
            'reminder' => [
                'id' => $reminder->id,
                'remind_at' => $reminder->remind_at->toIso8601String(),
            ],
        ]);
    }

    public function destroy(Request $request, Project $project, Task $task, TaskReminder $reminder): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id && $reminder->task_id === $task->id, 404);
        abort_unless($reminder->user_id === $request->user()->id || $request->user()->is_admin, 403);

        $reminder->delete();

        return $this->apiOrBack($request, ['ok' => true]);
    }
}
