<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\Project;
use App\Models\Task;
use App\Support\BugSla;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RankDashboardController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        ProjectAccess::ensureAccess($request->user(), $project);

        $ranks = $project->ranks()
            ->with(['responsible:id,name', 'members:id,name'])
            ->orderBy('position')
            ->get()
            ->map(function ($rank) use ($project) {
                $memberIds = $rank->members->pluck('id');

                $tasksQuery = Task::query()
                    ->where('project_id', $project->id)
                    ->whereNull('archived_at')
                    ->whereHas('list', fn ($q) => $q->where('rank_id', $rank->id));

                $openTasks = (clone $tasksQuery)->where('status', '!=', 'done')->count();
                $overdueTasks = (clone $tasksQuery)
                    ->where('status', '!=', 'done')
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now())
                    ->count();

                $openBugs = 0;
                if ($rank->manages_bugs) {
                    $openBugs = Bug::query()
                        ->where('project_id', $project->id)
                        ->where('assigned_rank_id', $rank->id)
                        ->where('status', '!=', Bug::STATUS_CLOSED)
                        ->count();
                }

                return [
                    'id' => $rank->id,
                    'name' => $rank->name,
                    'slug' => $rank->slug,
                    'color' => $rank->color,
                    'manages_bugs' => (bool) $rank->manages_bugs,
                    'responsible' => $rank->responsible ? [
                        'id' => $rank->responsible->id,
                        'name' => $rank->responsible->name,
                    ] : null,
                    'members_count' => $rank->members->count(),
                    'stats' => [
                        'open_tasks' => $openTasks,
                        'overdue_tasks' => $overdueTasks,
                        'open_bugs' => $openBugs,
                        'active_members' => $memberIds->count(),
                    ],
                ];
            });

        return Inertia::render('Projects/RankDashboard', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
            ],
            'ranks' => $ranks,
        ]);
    }
}
