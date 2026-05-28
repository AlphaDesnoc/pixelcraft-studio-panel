<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TaskViewPresence;
use App\Support\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskPresenceController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        ProjectAccess::ensureAccess($request->user(), $project);

        $validated = $request->validate([
            'context' => ['required', 'string', 'in:kanban,task,gantt'],
            'task_id' => ['nullable', 'integer'],
        ]);

        TaskViewPresence::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'context' => $validated['context'],
                'task_id' => $validated['task_id'] ?? null,
            ],
            ['last_seen_at' => now()],
        );

        TaskViewPresence::query()
            ->where('project_id', $project->id)
            ->where('last_seen_at', '<', now()->subMinutes(2))
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function index(Request $request, Project $project): JsonResponse
    {
        ProjectAccess::ensureAccess($request->user(), $project);

        $since = now()->subMinutes(2);

        $viewers = TaskViewPresence::query()
            ->where('project_id', $project->id)
            ->where('last_seen_at', '>=', $since)
            ->with('user:id,name')
            ->get()
            ->map(fn (TaskViewPresence $p) => [
                'user_id' => $p->user_id,
                'user_name' => $p->user?->name,
                'context' => $p->context,
                'task_id' => $p->task_id,
                'last_seen_at' => $p->last_seen_at?->toIso8601String(),
            ]);

        return response()->json(['viewers' => $viewers]);
    }
}
