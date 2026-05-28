<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RankDashboardController;
use App\Models\Bug;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioController extends Controller
{
    /** @return array<string, mixed> */
    private function buildData(): array
    {
        $projects = Project::query()
            ->withCount([
                'members',
                'tasks as tasks_open' => fn ($q) => $q->where('status', '!=', Task::STATUS_DONE)->whereNull('archived_at'),
                'tasks as tasks_overdue' => fn ($q) => $q
                    ->where('status', '!=', Task::STATUS_DONE)
                    ->whereNull('archived_at')
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()),
                'bugs as bugs_open' => fn ($q) => $q->where('status', '!=', Bug::STATUS_CLOSED),
            ])
            ->orderBy('name')
            ->get();

        $rankDashboard = app(RankDashboardController::class);
        $capacityAlerts = [];
        $velocityTrend = [];

        $rows = $projects->map(function (Project $project) use ($rankDashboard, &$capacityAlerts, &$velocityTrend) {
            $payload = $rankDashboard->buildPayload($project);
            $projectAlerts = 0;

            foreach ($payload['ranks'] ?? [] as $rank) {
                $velocityTrend[] = [
                    'project' => $project->name,
                    'rank' => $rank['name'],
                    'velocity' => $rank['stats']['velocity'] ?? 0,
                    'sla_breached' => $rank['stats']['sla_breached'] ?? 0,
                ];
                foreach ($rank['capacity_alerts'] ?? [] as $alert) {
                    $projectAlerts++;
                    $capacityAlerts[] = [
                        'user_name' => $alert['name'] ?? 'Membre',
                        'rank_name' => $rank['name'] ?? 'Rank',
                        'open_tasks' => $alert['open_tasks'] ?? 0,
                        'project' => ['name' => $project->name, 'slug' => $project->slug],
                    ];
                }
            }

            $slaBreached = collect($payload['ranks'] ?? [])->sum(fn ($r) => $r['stats']['sla_breached'] ?? 0);

            return [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'members_count' => $project->members_count,
                'tasks_open' => $project->tasks_open,
                'tasks_overdue' => $project->tasks_overdue,
                'bugs_open' => $project->bugs_open,
                'sla_breached' => $slaBreached,
                'capacity_alerts' => $projectAlerts,
                'url' => route('projects.show', $project->slug),
            ];
        });

        return [
            'projects' => $rows,
            'capacityAlerts' => $capacityAlerts,
            'velocityTrend' => $velocityTrend,
            'summary' => [
                'projects' => $projects->count(),
                'tasks_open' => $rows->sum('tasks_open'),
                'tasks_overdue' => $rows->sum('tasks_overdue'),
                'bugs_open' => $rows->sum('bugs_open'),
                'sla_breached' => $rows->sum('sla_breached'),
                'capacity_alerts' => count($capacityAlerts),
            ],
            'capacityThreshold' => RankDashboardController::CAPACITY_OPEN_TASKS_THRESHOLD,
        ];
    }

    public function index(Request $request): Response
    {
        $data = $this->buildData();

        return Inertia::render('Admin/Portfolio/Index', $data);
    }

    public function apiIndex(): JsonResponse
    {
        return response()->json($this->buildData());
    }

    public function export(): StreamedResponse
    {
        $data = $this->buildData();
        $filename = 'portfolio-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Projet', 'Tâches ouvertes', 'Retard', 'Bugs', 'SLA', 'Alertes capacité'], ';');
            foreach ($data['projects'] as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['tasks_open'],
                    $row['tasks_overdue'],
                    $row['bugs_open'],
                    $row['sla_breached'],
                    $row['capacity_alerts'],
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
