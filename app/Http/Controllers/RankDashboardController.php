<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
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

        $since = now()->subDays(14);

        $ranks = $project->ranks()
            ->with(['responsible:id,name', 'members:id,name'])
            ->orderBy('position')
            ->get()
            ->map(function ($rank) use ($project, $since) {
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

                $completedRecently = Task::query()
                    ->where('project_id', $project->id)
                    ->where('status', Task::STATUS_DONE)
                    ->where('updated_at', '>=', $since)
                    ->whereHas('list', fn ($q) => $q->where('rank_id', $rank->id))
                    ->count();

                $velocity = round($completedRecently / 2, 1);

                $openBugs = 0;
                $slaBreached = 0;
                $avgResolutionHours = null;

                if ($rank->manages_bugs) {
                    $bugsQuery = Bug::query()
                        ->where('project_id', $project->id)
                        ->where('assigned_rank_id', $rank->id);

                    $openBugs = (clone $bugsQuery)
                        ->where('status', '!=', Bug::STATUS_CLOSED)
                        ->count();

                    $slaBreached = (clone $bugsQuery)
                        ->where('status', '!=', Bug::STATUS_CLOSED)
                        ->whereNotNull('sla_due_at')
                        ->where('sla_due_at', '<', now())
                        ->count();

                    $resolved = (clone $bugsQuery)
                        ->where('status', Bug::STATUS_CLOSED)
                        ->where('updated_at', '>=', $since)
                        ->get(['created_at', 'updated_at']);

                    if ($resolved->isNotEmpty()) {
                        $avgResolutionHours = round(
                            $resolved->avg(fn (Bug $b) => $b->created_at->diffInHours($b->updated_at)),
                            1,
                        );
                    }
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
                        'velocity' => $velocity,
                        'sla_breached' => $slaBreached,
                        'avg_bug_resolution_hours' => $avgResolutionHours,
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
