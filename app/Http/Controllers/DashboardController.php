<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Task;
use App\Support\CalendarRecurrenceExpander;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Carbon;

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

        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY);

        $weekEvents = [];
        $events = CalendarEvent::query()
            ->whereIn('project_id', $projectIds)
            ->with(['project:id,slug,name', 'exceptions'])
            ->get();

        foreach ($events as $event) {
            $occurrences = CalendarRecurrenceExpander::expandForRange(
                $event,
                $weekStart,
                $weekEnd,
                $event->exceptions,
            );

            foreach ($occurrences as $occurrence) {
                $weekEvents[] = [
                    'id' => $event->id,
                    'title' => $occurrence['title'] ?? $event->title,
                    'start_at' => $occurrence['start_at'],
                    'end_at' => $occurrence['end_at'],
                    'all_day' => (bool) ($occurrence['all_day'] ?? $event->all_day),
                    'color' => $occurrence['color'] ?? $event->color,
                    'project' => [
                        'name' => $event->project->name,
                        'slug' => $event->project->slug,
                    ],
                    'url' => route('projects.show', $event->project->slug).'?tab=calendar',
                ];
            }
        }

        usort($weekEvents, fn ($a, $b) => strcmp($a['start_at'], $b['start_at']));

        $myOpenTasks = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('assignee_id', $user->id)
            ->whereNull('archived_at')
            ->where('status', '!=', Task::STATUS_DONE)
            ->with('project:id,slug,name')
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit(8)
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'due_date' => optional($t->due_date)?->toDateString(),
                'project' => $t->project ? [
                    'name' => $t->project->name,
                    'slug' => $t->project->slug,
                ] : null,
                'url' => $t->project
                    ? route('projects.show', $t->project->slug).'?tab=kanban&task='.$t->id
                    : null,
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'projects' => $projects,
            'weekEvents' => array_slice($weekEvents, 0, 20),
            'myOpenTasks' => $myOpenTasks,
            'dashboardWidgets' => array_merge(
                ProfileDashboardWidgetsController::defaults(),
                $user->dashboard_widgets ?? [],
            ),
            'availableWidgets' => ProfileDashboardWidgetsController::WIDGETS,
        ]);
    }
}
