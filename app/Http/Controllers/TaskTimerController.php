<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTimeEntry;
use App\Support\TaskKanbanPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskTimerController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function status(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id, 404);

        $entry = TaskTimeEntry::query()
            ->where('task_id', $task->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();

        return response()->json([
            'running' => $entry !== null,
            'entry' => $entry ? [
                'id' => $entry->id,
                'started_at' => $entry->started_at?->toIso8601String(),
            ] : null,
            'logged_minutes' => (int) ($task->logged_minutes ?? 0),
        ]);
    }

    public function start(Request $request, Project $project, Task $task): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id, 404);

        TaskTimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('stopped_at')
            ->each(fn (TaskTimeEntry $e) => $this->stopEntry($e));

        $entry = TaskTimeEntry::query()->create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'started_at' => now(),
        ]);

        return $this->apiOrBack($request, [
            'entry' => [
                'id' => $entry->id,
                'started_at' => $entry->started_at->toIso8601String(),
            ],
            'task' => TaskKanbanPayload::from($task->fresh()),
        ]);
    }

    public function stop(Request $request, Project $project, Task $task): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id, 404);

        $entry = TaskTimeEntry::query()
            ->where('task_id', $task->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();

        if ($entry) {
            $this->stopEntry($entry);
        }

        return $this->apiOrBack($request, [
            'task' => TaskKanbanPayload::from($task->fresh()),
        ]);
    }

    private function stopEntry(TaskTimeEntry $entry): void
    {
        $stopped = now();
        $minutes = max(1, (int) $entry->started_at->diffInMinutes($stopped));
        $entry->update([
            'stopped_at' => $stopped,
            'minutes' => $minutes,
        ]);

        $task = $entry->task;
        $task->update([
            'logged_minutes' => (int) ($task->logged_minutes ?? 0) + $minutes,
        ]);
    }
}
