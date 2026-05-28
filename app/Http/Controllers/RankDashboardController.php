<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Bug;
use App\Models\Project;
use App\Models\Task;
use App\Support\BugSla;
use App\Support\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RankDashboardController extends Controller
{
    public const CAPACITY_OPEN_TASKS_THRESHOLD = 15;

    public function index(Request $request, Project $project): Response|JsonResponse
    {
        ProjectAccess::ensureAccess($request->user(), $project);

        $payload = $this->buildPayload($project);

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Projects/RankDashboard', $payload);
    }

    public function export(Request $request, Project $project): StreamedResponse
    {
        ProjectAccess::ensureAccess($request->user(), $project);

        $payload = $this->buildPayload($project);
        $filename = 'rank-dashboard-'.$project->slug.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($payload) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Rank',
                'Tâches ouvertes',
                'En retard',
                'Bugs ouverts',
                'SLA dépassés',
                'Vélocité (2 sem.)',
                'Membres actifs',
            ], ';');

            foreach ($payload['ranks'] as $rank) {
                fputcsv($out, [
                    $rank['name'],
                    $rank['stats']['open_tasks'],
                    $rank['stats']['overdue_tasks'],
                    $rank['stats']['open_bugs'],
                    $rank['stats']['sla_breached'],
                    $rank['stats']['velocity'],
                    $rank['stats']['active_members'],
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array<string, mixed> */
    public function buildPayload(Project $project): array
    {
        $since = now()->subDays(14);

        $ranks = $project->ranks()
            ->with(['responsible:id,name', 'members:id,name'])
            ->orderBy('position')
            ->get()
            ->map(function ($rank) use ($project, $since) {
                $memberIds = $rank->members->pluck('id');

                $memberWorkload = $rank->members->map(function ($member) use ($project, $rank) {
                    $tasksQuery = Task::query()
                        ->where('project_id', $project->id)
                        ->whereNull('archived_at')
                        ->where('assignee_id', $member->id)
                        ->whereHas('list', fn ($q) => $q->where('rank_id', $rank->id));

                    $open = (clone $tasksQuery)->where('status', '!=', 'done')->count();
                    $overdue = (clone $tasksQuery)
                        ->where('status', '!=', 'done')
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '<', now())
                        ->count();
                    $stale = (clone $tasksQuery)
                        ->where('status', '!=', 'done')
                        ->where('updated_at', '<', now()->subDays(7))
                        ->count();

                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'open_tasks' => $open,
                        'overdue_tasks' => $overdue,
                        'stale_tasks' => $stale,
                        'over_capacity' => $open >= self::CAPACITY_OPEN_TASKS_THRESHOLD,
                    ];
                })->values();

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
                    'member_workload' => $memberWorkload,
                    'burndown' => $this->burndownForRank($project, $rank->id),
                    'capacity_alerts' => $memberWorkload
                        ->filter(fn ($m) => $m['over_capacity'])
                        ->values(),
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

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
            ],
            'capacity_threshold' => self::CAPACITY_OPEN_TASKS_THRESHOLD,
            'ranks' => $ranks,
        ];
    }

    /** @return array<int, array{label: string, completed: int, open: int}> */
    private function burndownForRank(Project $project, int $rankId): array
    {
        $weeks = [];

        for ($i = 3; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

            $completed = Task::query()
                ->where('project_id', $project->id)
                ->where('status', Task::STATUS_DONE)
                ->whereBetween('updated_at', [$start, $end])
                ->whereHas('list', fn ($q) => $q->where('rank_id', $rankId))
                ->count();

            $openAtEnd = Task::query()
                ->where('project_id', $project->id)
                ->where('status', '!=', Task::STATUS_DONE)
                ->whereNull('archived_at')
                ->where('created_at', '<=', $end)
                ->whereHas('list', fn ($q) => $q->where('rank_id', $rankId))
                ->count();

            $weeks[] = [
                'label' => $start->format('d/m'),
                'completed' => $completed,
                'open' => $openAtEnd,
            ];
        }

        return $weeks;
    }
}
