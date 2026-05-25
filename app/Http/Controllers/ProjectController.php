<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\Project;
use App\Models\Rank;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Support\ProjectAccess;
use App\Support\ProjectSpace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        $user = $request->user();
        $isAdmin = $user->is_admin;
        $canManageTeam = ProjectAccess::canManageTeam($user, $project);

        if (! $project->ranks()->exists()) {
            foreach (Rank::defaultsFor($project->id) as $r) {
                $project->ranks()->create($r);
            }
        }

        $space = ProjectSpace::resolve($request, $project, $user);
        $space->ensureResources($project);

        $listScope = fn ($q) => $space->applyScope($q, 'rank_id');
        $featureScope = fn ($q) => $space->applyScope($q, 'rank_id');

        $project->load([
            'members:id,name,email',
            'owner:id,name,email',
            'lists' => fn ($q) => $listScope($q)->orderBy('position'),
            'lists.tasks' => fn ($q) => $q->orderBy('position'),
            'lists.tasks.checklists' => fn ($q) => $q->orderBy('position'),
            'lists.tasks.checklists.items' => fn ($q) => $q->orderBy('position'),
            'events' => fn ($q) => $featureScope($q)->orderBy('start_at'),
            'notes' => fn ($q) => $featureScope($q)->orderByDesc('pinned')->orderByDesc('pinned_at')->orderByDesc('created_at'),
            'notes.creator:id,name,email',
            'sheets' => fn ($q) => $featureScope($q)->orderBy('position'),
            'fileNodes' => fn ($q) => $featureScope($q)->orderByRaw("CASE WHEN type = 'folder' THEN 0 ELSE 1 END")->orderBy('name'),
            'fileNodes.uploader:id,name',
            'chatMessages' => fn ($q) => $q->where('space_key', $space->key)->orderBy('created_at'),
            'chatMessages.user:id,name',
            'ranks' => fn ($q) => $q->orderBy('position'),
            'ranks.members:id',
        ]);

        $events = $project->events->map(fn ($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'description' => $e->description,
            'start_at' => optional($e->start_at)?->toIso8601String(),
            'end_at' => optional($e->end_at)?->toIso8601String(),
            'all_day' => (bool) $e->all_day,
            'color' => $e->color,
            'creator_id' => $e->creator_id,
            'rank_id' => $e->rank_id,
        ])->values();

        $fileNodes = $project->fileNodes->map(fn ($n) => [
            'id' => $n->id,
            'parent_id' => $n->parent_id,
            'type' => $n->type,
            'name' => $n->name,
            'path' => $n->path,
            'url' => $n->path ? '/storage/'.ltrim($n->path, '/') : null,
            'mime' => $n->mime,
            'size' => $n->size ? (int) $n->size : null,
            'rank_id' => $n->rank_id,
            'created_at' => optional($n->created_at)?->toIso8601String(),
            'updated_at' => optional($n->updated_at)?->toIso8601String(),
            'uploader' => $n->uploader ? ['id' => $n->uploader->id, 'name' => $n->uploader->name] : null,
        ])->values();

        $sheets = $project->sheets->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'position' => (int) $s->position,
            'rows' => (int) $s->rows,
            'cols' => (int) $s->cols,
            'data' => $s->data ?: new \stdClass,
            'rank_id' => $s->rank_id,
        ])->values();

        $notes = $project->notes->map(fn ($n) => [
            'id' => $n->id,
            'title' => $n->title,
            'content' => $n->content,
            'color' => $n->color,
            'pinned' => (bool) $n->pinned,
            'pinned_at' => optional($n->pinned_at)?->toIso8601String(),
            'created_at' => optional($n->created_at)?->toIso8601String(),
            'updated_at' => optional($n->updated_at)?->toIso8601String(),
            'rank_id' => $n->rank_id,
            'creator' => $n->creator ? [
                'id' => $n->creator->id,
                'name' => $n->creator->name,
            ] : null,
        ])->values();

        $chatMessages = $space->isFull
            ? collect()
            : $project->chatMessages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'space_key' => $m->space_key,
                'created_at' => optional($m->created_at)?->toIso8601String(),
                'user' => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name] : null,
            ])->values();

        $lists = $project->lists->map(function ($list) {
            return [
                'id' => $list->id,
                'name' => $list->name,
                'color' => $list->color,
                'status_kind' => $list->status_kind,
                'position' => $list->position,
                'rank_id' => $list->rank_id,
                'tasks' => $list->tasks->map(fn ($task) => [
                    'id' => $task->id,
                    'list_id' => $task->list_id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'priority' => $task->priority,
                    'status' => $task->status,
                    'position' => $task->position,
                    'progress' => (int) $task->progress,
                    'assignee_id' => $task->assignee_id,
                    'start_date' => optional($task->start_date)?->toDateString(),
                    'due_date' => optional($task->due_date)?->toDateString(),
                    'checklists' => $task->checklists->map(fn ($cl) => [
                        'id' => $cl->id,
                        'name' => $cl->name,
                        'position' => $cl->position,
                        'items' => $cl->items->map(fn ($it) => [
                            'id' => $it->id,
                            'content' => $it->content,
                            'is_done' => (bool) $it->is_done,
                            'position' => $it->position,
                        ])->values(),
                    ])->values(),
                ])->values(),
            ];
        })->values();

        $tasksQuery = Task::query()
            ->where('project_id', $project->id)
            ->whereHas('list', fn ($q) => $space->applyScope($q, 'rank_id'));

        $statusCounts = (clone $tasksQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $priorityCounts = (clone $tasksQuery)
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $tasksTotal = (clone $tasksQuery)->count();
        $tasksDone = (int) ($statusCounts[Task::STATUS_DONE] ?? 0);
        $tasksInProgress = (int) ($statusCounts[Task::STATUS_IN_PROGRESS] ?? 0);
        $tasksTodo = (int) ($statusCounts[Task::STATUS_TODO] ?? 0);
        $tasksOverdue = (clone $tasksQuery)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->where('status', '!=', Task::STATUS_DONE)
            ->count();

        $stats = [
            'tasks_total' => $tasksTotal,
            'tasks_done' => $tasksDone,
            'tasks_in_progress' => $tasksInProgress,
            'tasks_todo' => $tasksTodo,
            'tasks_overdue' => $tasksOverdue,
            'members' => $project->members->count(),
            'notes' => $project->notes->count(),
            'events' => $project->events->count(),
        ];

        $byStatus = collect(Task::STATUSES)
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'count' => (int) ($statusCounts[$key] ?? 0),
            ])
            ->values();

        $byPriority = collect(Task::PRIORITIES)
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'count' => (int) ($priorityCounts[$key] ?? 0),
            ])
            ->values();

        $progress = $tasksTotal > 0
            ? (int) round(($tasksDone / $tasksTotal) * 100)
            : 0;

        $spaces = [
            [
                'key' => ProjectSpace::GLOBAL,
                'label' => 'Global',
                'icon' => 'globe',
                'kind' => 'system',
            ],
        ];

        if ($isAdmin) {
            $spaces[] = [
                'key' => ProjectSpace::FULL,
                'label' => 'Vue totale',
                'icon' => 'eye',
                'kind' => 'admin',
            ];
        }

        $ranks = $project->ranks
            ->filter(function ($r) use ($isAdmin, $user) {
                if ($isAdmin) {
                    return true;
                }

                return $r->members->contains('id', $user->id);
            })
            ->map(fn ($r) => [
                'id' => $r->id,
                'key' => $r->slug,
                'label' => $r->name,
                'color' => $r->color,
                'manages_bugs' => (bool) $r->manages_bugs,
            ])
            ->values();

        $currentRank = $space->rankId
            ? $project->ranks->firstWhere('id', $space->rankId)
            : null;

        $canReportBugs = $space->isGlobal;
        $canManageBugs = (bool) ($currentRank?->manages_bugs);

        $bugsQuery = $project->bugs()->with(['reporter:id,name', 'assignee:id,name', 'assignedRank:id,name']);

        if ($canManageBugs) {
            $bugsCollection = $bugsQuery->get();
        } elseif ($canReportBugs) {
            $bugsCollection = $bugsQuery->where('reporter_id', $user->id)->get();
        } else {
            $bugsCollection = collect();
        }

        $bugs = $bugsCollection->map(fn ($b) => [
            'id' => $b->id,
            'title' => $b->title,
            'description' => $b->description,
            'priority' => $b->priority,
            'status' => $b->status,
            'created_at' => optional($b->created_at)?->toIso8601String(),
            'reporter' => $b->reporter ? ['id' => $b->reporter->id, 'name' => $b->reporter->name] : null,
            'assignee' => $b->assignee ? ['id' => $b->assignee->id, 'name' => $b->assignee->name] : null,
            'assigned_rank' => $b->assignedRank ? ['id' => $b->assignedRank->id, 'name' => $b->assignedRank->name] : null,
            'screenshots' => collect($b->screenshots ?? [])->map(fn ($p) => '/storage/'.ltrim($p, '/'))->values(),
        ])->values();

        $bugRanks = $project->ranks
            ->where('manages_bugs', true)
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
            ->values();

        $spaceLabel = match (true) {
            $space->isFull => 'Vue totale',
            $space->isGlobal => 'Global',
            default => $project->ranks->firstWhere('id', $space->rankId)?->name ?? $space->key,
        };

        $members = $project->members->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
        ])->values();

        $teamMembers = $project->members->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'role' => $m->pivot->role ?? ProjectAccess::ROLE_MEMBER,
            'joined_at' => optional($m->pivot->joined_at)?->toIso8601String(),
            'is_owner' => (int) $project->owner_id === (int) $m->id
                || ($m->pivot->role ?? null) === ProjectAccess::ROLE_OWNER,
        ])->values();

        $teamCandidates = $canManageTeam
            ? User::query()
                ->whereNotIn('id', $project->members->pluck('id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                ])
                ->values()
            : collect();

        return Inertia::render('Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                'image_url' => $project->image_url,
                'status' => $project->status,
                'start_date' => optional($project->start_date)?->toDateString(),
            ],
            'activeSpace' => $space->key,
            'activeRankId' => $space->rankId,
            'spaceLabel' => $spaceLabel,
            'stats' => $stats,
            'progress' => $progress,
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'spaces' => $spaces,
            'ranks' => $ranks,
            'canManageRanks' => $isAdmin,
            'canReportBugs' => $canReportBugs,
            'canManageBugs' => $canManageBugs,
            'bugs' => $bugs,
            'bugRanks' => $bugRanks,
            'bugPriorities' => Bug::PRIORITIES,
            'bugStatuses' => Bug::STATUSES,
            'lists' => $lists,
            'events' => $events,
            'notes' => $notes,
            'sheets' => $sheets,
            'fileNodes' => $fileNodes,
            'chatMessages' => $chatMessages,
            'members' => $members,
            'teamMembers' => $teamMembers,
            'teamCandidates' => $teamCandidates,
            'canManageTeam' => $canManageTeam,
            'memberRoles' => ProjectAccess::ROLES,
            'priorities' => Task::PRIORITIES,
            'statusKinds' => [
                TaskList::STATUS_TODO => 'À faire',
                TaskList::STATUS_IN_PROGRESS => 'En cours',
                TaskList::STATUS_DONE => 'Terminée',
            ],
        ]);
    }
}
