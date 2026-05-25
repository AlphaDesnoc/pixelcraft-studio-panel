<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
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
            ->get();

        $projectIds = $projects->pluck('id');

        $tasks = Task::query()->whereIn('project_id', $projectIds);

        $stats = [
            'projects' => $projects->count(),
            'tasks' => (clone $tasks)->count(),
            'completed' => (clone $tasks)->where('status', Task::STATUS_DONE)->count(),
            'overdue' => (clone $tasks)
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->where('status', '!=', Task::STATUS_DONE)
                ->count(),
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'projects' => $projects,
        ]);
    }
}
