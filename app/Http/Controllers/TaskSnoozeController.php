<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Project;
use App\Models\Task;
use App\Support\TaskKanbanPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskSnoozeController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function store(Request $request, Project $project, Task $task): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id, 404);

        $validated = $request->validate([
            'duration' => ['required', 'string', 'in:1d,1w,custom'],
            'until' => ['nullable', 'date', 'after:now'],
        ]);

        $until = match ($validated['duration']) {
            '1d' => now()->addDay(),
            '1w' => now()->addWeek(),
            'custom' => $validated['until'] ?? now()->addDay(),
        };

        $task->update(['snoozed_until' => $until]);

        return $this->apiOrBack($request, [
            'task' => TaskKanbanPayload::from($task->fresh()),
        ]);
    }

    public function destroy(Request $request, Project $project, Task $task): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id, 404);

        $task->update(['snoozed_until' => null]);

        return $this->apiOrBack($request, [
            'task' => TaskKanbanPayload::from($task->fresh()),
        ]);
    }
}
