<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = $user
            ->projects()
            ->withCount([
                'members as members_count',
                'tasks as tasks_total',
                'tasks as tasks_done' => fn ($query) => $query->where('status', Task::STATUS_DONE),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'members_count' => $project->members_count,
                'tasks_total' => $project->tasks_total,
                'tasks_done' => $project->tasks_done,
            ])
            ->values();

        $projectIds = $projects->pluck('id');

        $tasks = Task::query()->whereIn('project_id', $projectIds);

        return response()->json([
            'projects' => $projects,
            'stats' => [
                'projects' => $projects->count(),
                'tasks' => (clone $tasks)->count(),
                'completed' => (clone $tasks)->where('status', Task::STATUS_DONE)->count(),
                'overdue' => (clone $tasks)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now())
                    ->where('status', '!=', Task::STATUS_DONE)
                    ->count(),
            ],
        ]);
    }
}
