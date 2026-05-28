<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ChatMessage;
use App\Models\Bug;
use App\Models\Project;
use App\Models\Rank;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TaskTag;
use App\Models\KanbanSavedView;
use App\Models\Milestone;
use App\Models\ProjectAutomationRule;
use App\Models\User;
use App\Support\ProjectAccess;
use App\Support\ProjectPermissions;
use App\Support\ProjectSpace;
use App\Support\SpaceChatAccess;
use App\Support\TaskActivityFeed;
use App\Support\BugVisibility;
use App\Http\Controllers\RankDashboardController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        return Inertia::render('Projects/Show', $this->buildShowPayload($request, $project));
    }

    public function updateCapacityThreshold(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        ProjectAccess::ensureCanManageTeam($request->user(), $project);

        $validated = $request->validate([
            'capacity_threshold' => ['required', 'integer', 'min:5', 'max:100'],
        ]);

        $project->update(['capacity_threshold' => $validated['capacity_threshold']]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['capacity_threshold' => $project->capacity_threshold]);
        }

        return back();
    }

    /** @return array<string, mixed> */
    public function buildShowPayload(Request $request, Project $project): array
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
            'lists.tasks.comments' => fn ($q) => $q->with('user:id,name')->latest(),
            'lists.tasks.attachments',
            'lists.tasks.tags',
            'lists.tasks.linkedBug',
            'lists.tasks.dependencies:id,status,title',
            'events' => fn ($q) => $featureScope($q)->with('exceptions')->orderBy('start_at'),
            'notes' => fn ($q) => $featureScope($q)->orderByDesc('pinned')->orderByDesc('pinned_at')->orderByDesc('created_at'),
            'notes.creator:id,name,email',
            'sheets' => fn ($q) => $featureScope($q)->orderBy('position'),
            'fileNodes' => fn ($q) => $featureScope($q)->orderByRaw("CASE WHEN type = 'folder' THEN 0 ELSE 1 END")->orderBy('name'),
            'fileNodes.uploader:id,name',
            'chatMessages' => fn ($q) => $q->where('space_key', $space->key)->orderByDesc('pinned_at')->orderBy('created_at'),
            'chatMessages.user:id,name',
            'chatMessages.attachments',
            'chatMessages.replyTo.user:id,name',
            'chatMessages.reactions',
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
            'recurrence' => $e->recurrence,
            'recurrence_weekdays' => $e->recurrence_weekdays ?? [],
            'recurrence_until' => optional($e->recurrence_until)?->toDateString(),
            'reminder_minutes' => $e->reminder_minutes,
            'exceptions' => $e->exceptions->map(fn ($ex) => [
                'occurrence_date' => $ex->occurrence_date->toDateString(),
                'type' => $ex->type,
                'title' => $ex->title,
                'description' => $ex->description,
                'start_at' => optional($ex->start_at)?->toIso8601String(),
                'end_at' => optional($ex->end_at)?->toIso8601String(),
                'all_day' => $ex->all_day,
                'color' => $ex->color,
            ])->values(),
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
            : $project->chatMessages->map(fn ($m) => $m->toPayload())->values();

        $lists = $space->isFull
            ? $this->buildMergedKanbanLists($project)
            : $this->mapLists($project->lists);

        $tasksQuery = Task::query()->where('project_id', $project->id);

        if (! $space->isGlobal && ! $space->isFull) {
            $tasksQuery->whereHas('list', fn ($q) => $space->applyScope($q, 'rank_id'));
        }

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
        $canManageBugs = match (true) {
            $space->isFull => $user->is_admin,
            $space->isGlobal => BugVisibility::userManagesAnyBugRank($user, $project),
            $space->rankId && ($currentRank?->manages_bugs ?? false) => BugVisibility::userManagesRank(
                $user,
                $project,
                $space->rankId,
            ),
            default => false,
        };

        $bugsQuery = BugVisibility::queryForSpace(
            $project->bugs()->with(['reporter:id,name', 'assignee:id,name', 'assignedRank:id,name']),
            $user,
            $project,
            $space,
        );

        $bugsCollection = $bugsQuery->get();

        $bugActivities = ActivityLog::query()
            ->whereIn('bug_id', $bugsCollection->pluck('id'))
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('bug_id');

        $bugs = $bugsCollection->map(fn ($b) => [
            'id' => $b->id,
            'title' => $b->title,
            'description' => $b->description,
            'priority' => $b->priority,
            'status' => $b->status,
            'task_id' => $b->task_id ?? null,
            'sla_due_at' => optional($b->sla_due_at)?->toIso8601String(),
            'is_sla_breached' => $b->sla_due_at
                && $b->sla_due_at->isPast()
                && $b->status !== Bug::STATUS_CLOSED,
            'created_at' => optional($b->created_at)?->toIso8601String(),
            'reporter' => $b->reporter ? ['id' => $b->reporter->id, 'name' => $b->reporter->name] : null,
            'assignee' => $b->assignee ? ['id' => $b->assignee->id, 'name' => $b->assignee->name] : null,
            'assigned_rank' => $b->assignedRank ? ['id' => $b->assignedRank->id, 'name' => $b->assignedRank->name] : null,
            'screenshots' => collect($b->screenshots ?? [])->map(fn ($p) => '/storage/'.ltrim($p, '/'))->values(),
            'activity' => ($bugActivities[$b->id] ?? collect())
                ->take(30)
                ->map(fn (ActivityLog $log) => $log->toPayload())
                ->values(),
            'can_manage' => BugVisibility::canManage($user, $b, $project),
            'can_edit' => BugVisibility::canEditReport($user, $b),
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

        $teamMembers = $project->members->map(function ($m) use ($project) {
            $perms = $m->pivot->permissions ?? null;
            if (is_string($perms)) {
                $decoded = json_decode($perms, true);
                $perms = is_array($decoded) ? $decoded : [];
            } elseif (! is_array($perms)) {
                $perms = [];
            }

            return [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'role' => $m->pivot->role ?? ProjectAccess::ROLE_MEMBER,
                'joined_at' => optional($m->pivot->joined_at)?->toIso8601String(),
                'is_owner' => (int) $project->owner_id === (int) $m->id
                    || ($m->pivot->role ?? null) === ProjectAccess::ROLE_OWNER,
                'permissions' => $perms,
            ];
        })->values();

        $taskTagsQuery = TaskTag::query()
            ->where('project_id', $project->id)
            ->orderBy('name');

        if ($space->isFull) {
            // Vue totale : toutes les étiquettes de tous les ranks.
        } elseif ($space->isGlobal) {
            $taskTagsQuery->whereNull('rank_id');
        } else {
            $taskTagsQuery->where('rank_id', $space->rankId);
        }

        $taskTagsPayload = $taskTagsQuery
            ->get()
            ->map(fn (TaskTag $tag) => $tag->toPayload())
            ->values();

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

        return [
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
            'tags' => $taskTagsPayload,
            'events' => $events,
            'notes' => $notes,
            'sheets' => $sheets,
            'fileNodes' => $fileNodes,
            'chatMessages' => $chatMessages,
            'chatMembers' => $space->isFull
                ? []
                : SpaceChatAccess::membersWithPresence($project, $space->key, $user),
            'chatRankMentions' => $space->isGlobal
                ? $project->ranks
                    ->sortBy('position')
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'slug' => $r->slug,
                        'name' => $r->name,
                        'color' => $r->color,
                    ])
                    ->values()
                : [],
            'activityLogs' => ActivityLog::query()
                ->where('project_id', $project->id)
                ->with('user:id,name')
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (ActivityLog $log) => $log->toPayload()),
            'taskActivityByRank' => TaskActivityFeed::groupedForSpace($project, $space, $ranks),
            'members' => $members,
            'teamMembers' => $teamMembers,
            'teamCandidates' => $teamCandidates,
            'canManageTeam' => $canManageTeam,
            'myPermissions' => ProjectPermissions::forMember($user, $project),
            'memberRoles' => ProjectAccess::ROLES,
            'taskTemplates' => TaskTemplate::query()
                ->where('project_id', $project->id)
                ->orderBy('name')
                ->get()
                ->map(fn (TaskTemplate $t) => $t->toPayload())
                ->values(),
            'pinnedChatMessages' => ChatMessage::query()
                ->where('project_id', $project->id)
                ->whereNotNull('pinned_at')
                ->with('user:id,name')
                ->orderByDesc('pinned_at')
                ->limit(15)
                ->get()
                ->map(fn (ChatMessage $m) => [
                    'id' => $m->id,
                    'space_key' => $m->space_key,
                    'body' => str($m->body)->limit(120),
                    'user' => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name] : null,
                    'pinned_at' => $m->pinned_at?->toIso8601String(),
                ])
                ->values(),
            'capacityThreshold' => $project->capacity_threshold ?? RankDashboardController::CAPACITY_OPEN_TASKS_THRESHOLD,
            'kanbanSavedViews' => KanbanSavedView::query()
                ->where('project_id', $project->id)
                ->where(function ($q) use ($user) {
                    $q->where('is_shared', true)->orWhere('user_id', $user->id);
                })
                ->orderBy('name')
                ->get()
                ->map->toPayload()
                ->values(),
            'milestones' => Milestone::query()
                ->where('project_id', $project->id)
                ->with('tasks:id,status')
                ->orderBy('position')
                ->get()
                ->map->toPayload()
                ->values(),
            'automationRules' => ProjectAutomationRule::query()
                ->where('project_id', $project->id)
                ->orderBy('name')
                ->get()
                ->map->toPayload()
                ->values(),
            'priorities' => Task::PRIORITIES,
            'statusKinds' => [
                TaskList::STATUS_TODO => 'À faire',
                TaskList::STATUS_IN_PROGRESS => 'En cours',
                TaskList::STATUS_DONE => 'Terminée',
            ],
            'globalKanban' => $space->isFull,
        ];
    }

    private function mapLists($lists)
    {
        return $lists->map(function ($list) {
            return [
                'id' => $list->id,
                'name' => $list->name,
                'color' => $list->color,
                'status_kind' => $list->status_kind,
                'position' => $list->position,
                'rank_id' => $list->rank_id,
                'tasks' => $list->tasks->map(fn ($task) => $this->mapTask($task))->values(),
            ];
        })->values();
    }

    private function buildMergedKanbanLists(Project $project)
    {
        $this->ensureGlobalKanbanLists($project);

        $defaultNames = collect(TaskList::defaultsFor($project->id))->pluck('name')->all();

        $globalLists = $project->lists()
            ->whereNull('rank_id')
            ->whereIn('name', $defaultNames)
            ->get()
            ->sortBy(fn ($list) => array_search($list->name, $defaultNames, true))
            ->values();

        $allTasks = Task::query()
            ->where('project_id', $project->id)
            ->with([
                'list',
                'checklists' => fn ($q) => $q->orderBy('position'),
                'checklists.items' => fn ($q) => $q->orderBy('position'),
                'comments' => fn ($q) => $q->with('user:id,name')->latest(),
                'attachments',
                'dependencies:id,status,title',
            ])
            ->orderBy('position')
            ->get();

        return $globalLists->map(function ($globalList) use ($allTasks) {
            $tasks = $allTasks
                ->filter(function ($task) use ($globalList) {
                    $source = $task->list;
                    if (! $source) {
                        return false;
                    }

                    if ($source->rank_id === null && $source->id === $globalList->id) {
                        return true;
                    }

                    if ($source->name === $globalList->name) {
                        return true;
                    }

                    return $source->name === 'Tout' && $globalList->name === 'À faire';
                })
                ->values();

            return [
                'id' => $globalList->id,
                'name' => $globalList->name,
                'color' => $globalList->color,
                'status_kind' => $globalList->status_kind,
                'position' => $globalList->position,
                'rank_id' => null,
                'tasks' => $tasks->map(fn ($task) => $this->mapTask($task))->values(),
            ];
        })->values();
    }

    private function ensureGlobalKanbanLists(Project $project): void
    {
        $existingNames = $project->lists()
            ->whereNull('rank_id')
            ->pluck('name')
            ->all();

        foreach (TaskList::defaultsFor($project->id) as $default) {
            if (in_array($default['name'], $existingNames, true)) {
                continue;
            }

            $project->lists()->create(
                collect($default)
                    ->except('project_id')
                    ->merge(['rank_id' => null])
                    ->all()
            );
        }
    }

    private function mapTask(Task $task): array
    {
        $linked = $task->relationLoaded('linkedBug') ? $task->linkedBug : null;

        return [
            'id' => $task->id,
            'list_id' => $task->list_id,
            'rank_id' => $task->list?->rank_id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'position' => $task->position,
            'progress' => (int) $task->progress,
            'assignee_id' => $task->assignee_id,
            'start_date' => optional($task->start_date)?->toDateString(),
            'due_date' => optional($task->due_date)?->toDateString(),
            'is_overdue' => $task->isOverdue(),
            'archived_at' => optional($task->archived_at)?->toIso8601String(),
            'recurrence_rule' => $task->recurrence_rule,
            'estimated_minutes' => $task->estimated_minutes,
            'logged_minutes' => (int) ($task->logged_minutes ?? 0),
            'auto_archive_at' => optional($task->auto_archive_at)?->toDateString(),
            'dependency_ids' => $task->relationLoaded('dependencies')
                ? $task->dependencies->pluck('id')->values()->all()
                : [],
            'is_blocked' => $task->isBlocked(),
            'tags' => $task->tags->map(fn (TaskTag $tg) => $tg->toPayload())->values(),
            'checklist_progress' => $this->taskChecklistProgress($task),
            'linked_bug' => $linked ? [
                'id' => $linked->id,
                'title' => $linked->title,
                'url' => null,
            ] : null,
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
            'comments' => $task->comments->map(fn ($c) => $c->toPayload())->values(),
            'attachments' => $task->attachments->map(fn ($a) => $a->toPayload())->values(),
        ];
    }

    private function taskChecklistProgress(Task $task): array
    {
        $done = 0;
        $total = 0;
        foreach ($task->checklists as $cl) {
            foreach ($cl->items as $it) {
                $total++;
                if ($it->is_done) {
                    $done++;
                }
            }
        }

        return ['done' => $done, 'total' => $total];
    }
}
