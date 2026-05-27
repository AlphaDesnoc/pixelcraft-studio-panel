<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = Project::query()
            ->with('owner:id,name,email')
            ->withCount([
                'members as members_count',
                'tasks as tasks_count',
                'tasks as tasks_done_count' => fn ($q) => $q->where('status', Task::STATUS_DONE),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Project $project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'description' => $project->description,
                    'status' => $project->status,
                    'image_url' => $project->image_url,
                    'owner' => $project->owner ? [
                        'id' => $project->owner->id,
                        'name' => $project->owner->name,
                    ] : null,
                    'members_count' => $project->members_count,
                    'tasks_count' => $project->tasks_count,
                    'tasks_done_count' => $project->tasks_done_count,
                    'created_at' => $project->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'projects' => $projects,
            'statuses' => Project::STATUSES,
            'projectTemplates' => ProjectTemplate::query()->orderBy('name')->get()->map->toPayload(),
        ]);
    }
}
