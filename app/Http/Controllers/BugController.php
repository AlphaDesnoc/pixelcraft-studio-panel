<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Bug;
use App\Models\Project;
use App\Models\Rank;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\ActivityLog;
use App\Models\UserNotification;
use App\Support\ActivityLogger;
use App\Support\BugVisibility;
use App\Support\PanelNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BugController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureMember($request, $project);
        $this->ensureFeature($request, $project, 'bugs');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', Rule::in(array_keys(Bug::PRIORITIES))],
            'screenshots' => ['nullable', 'array', 'max:5'],
            'screenshots.*' => ['image', 'max:5120'],
        ]);

        $paths = [];
        if ($request->hasFile('screenshots')) {
            foreach ($request->file('screenshots') as $file) {
                $paths[] = $file->store("projects/{$project->id}/bugs", 'public');
            }
        }

        $bug = $project->bugs()->create([
            'reporter_id' => $request->user()->id,
            'assigned_rank_id' => null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? Bug::PRIORITY_MEDIUM,
            'status' => Bug::STATUS_OPEN,
            'screenshots' => $paths ?: null,
        ]);

        $bug->update(['sla_due_at' => \App\Support\BugSla::dueAt($bug)]);

        ActivityLogger::log(
            $project,
            $request->user(),
            'bug_created',
            sprintf('%s a signalé le bug « %s »', $request->user()->name, $bug->title),
            $bug,
            ['priority' => $bug->priority],
        );

        if ($bug->priority === Bug::PRIORITY_URGENT) {
            $notified = [];
            foreach ($project->ranks()->where('manages_bugs', true)->with('members:id')->get() as $rank) {
                foreach ($rank->members as $member) {
                    if (in_array($member->id, $notified, true) || (int) $member->id === (int) $request->user()->id) {
                        continue;
                    }
                    $notified[] = $member->id;
                    PanelNotifier::send(
                        (int) $member->id,
                        UserNotification::TYPE_BUG_ASSIGNED,
                        'Bug urgent signalé',
                        sprintf('Bug urgent : %s', $bug->title),
                        route('projects.show', $project->slug).'?tab=bugs',
                        ['project_id' => $project->id, 'bug_id' => $bug->id],
                    );
                }
            }
        }

        return $this->apiOrBack($request, [
            'bug' => $this->bugPayload($bug->fresh(['reporter:id,name,avatar_path', 'assignee:id,name', 'assignedRank:id,name'])),
        ]);
    }

    public function update(Request $request, Project $project, Bug $bug): JsonResponse|RedirectResponse
    {
        $this->ensureFeature($request, $project, 'bugs');
        abort_unless($bug->project_id === $project->id, 404);

        $user = $request->user();
        $canManage = BugVisibility::canManage($user, $bug, $project);
        $canEditReport = BugVisibility::canEditReport($user, $bug);
        abort_unless($canManage || $canEditReport, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', Rule::in(array_keys(Bug::PRIORITIES))],
            'status' => ['nullable', Rule::in(array_keys(Bug::STATUSES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
            'screenshots' => ['nullable', 'array', 'max:5'],
            'screenshots.*' => ['image', 'max:5120'],
            'remove_screenshots' => ['nullable', 'array'],
            'remove_screenshots.*' => ['string'],
        ]);

        if ($canManage) {
            if (array_key_exists('assignee_id', $validated) && $validated['assignee_id']) {
                abort_unless(
                    $project->members()->whereKey($validated['assignee_id'])->exists(),
                    422,
                    'Assigné invalide.',
                );
            }

            if (! empty($validated['assigned_rank_id'])) {
                $rank = Rank::find($validated['assigned_rank_id']);
                abort_unless($rank && $rank->project_id === $project->id && $rank->manages_bugs, 422);
            }
        }

        $screenshots = $bug->screenshots ?? [];

        if (! empty($validated['remove_screenshots'])) {
            foreach ($validated['remove_screenshots'] as $path) {
                if (in_array($path, $screenshots, true)) {
                    Storage::disk('public')->delete($path);
                    $screenshots = array_values(array_filter($screenshots, fn ($p) => $p !== $path));
                }
            }
        }

        if ($request->hasFile('screenshots')) {
            foreach ($request->file('screenshots') as $file) {
                if (count($screenshots) >= 5) {
                    break;
                }
                $screenshots[] = $file->store("projects/{$project->id}/bugs", 'public');
            }
        }

        $previousAssignee = $bug->assignee_id;
        $previousStatus = $bug->status;
        $previousPriority = $bug->priority;

        $bug->fill([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? $bug->priority,
            'screenshots' => $screenshots ?: null,
        ]);

        if ($canManage) {
            $bug->fill([
                'status' => $validated['status'] ?? $bug->status,
                'assignee_id' => $validated['assignee_id'] ?? null,
                'assigned_rank_id' => array_key_exists('assigned_rank_id', $validated)
                    ? $validated['assigned_rank_id']
                    : $bug->assigned_rank_id,
            ]);
        }

        $bug->sla_due_at = \App\Support\BugSla::dueAt($bug);
        $bug->save();

        if ($bug->wasChanged('status')) {
            ActivityLogger::log(
                $project,
                $request->user(),
                'bug_status_changed',
                sprintf(
                    '%s a changé le statut de « %s » : %s → %s',
                    $request->user()->name,
                    $bug->title,
                    Bug::STATUSES[$previousStatus] ?? $previousStatus,
                    Bug::STATUSES[$bug->status] ?? $bug->status,
                ),
                $bug,
                ['from' => $previousStatus, 'to' => $bug->status],
            );
        }

        if ($bug->wasChanged('priority')) {
            ActivityLogger::log(
                $project,
                $request->user(),
                'bug_priority_changed',
                sprintf(
                    '%s a changé la priorité de « %s » : %s → %s',
                    $request->user()->name,
                    $bug->title,
                    Bug::PRIORITIES[$previousPriority] ?? $previousPriority,
                    Bug::PRIORITIES[$bug->priority] ?? $bug->priority,
                ),
                $bug,
                ['from' => $previousPriority, 'to' => $bug->priority],
            );

            if ($bug->priority === Bug::PRIORITY_URGENT) {
                $rank = $bug->assignedRank ?? Rank::find($bug->assigned_rank_id);
                if ($rank) {
                    foreach ($rank->members()->pluck('users.id') as $memberId) {
                        if ((int) $memberId === (int) $request->user()->id) {
                            continue;
                        }
                        PanelNotifier::send(
                            (int) $memberId,
                            UserNotification::TYPE_BUG_ASSIGNED,
                            'Bug urgent',
                            sprintf('Priorité urgente : %s', $bug->title),
                            route('projects.show', $project->slug).'?tab=bugs',
                            ['project_id' => $project->id, 'bug_id' => $bug->id],
                        );
                    }
                }
            }
        }

        if ($bug->wasChanged('assignee_id') && $bug->assignee_id) {
            ActivityLogger::log(
                $project,
                $request->user(),
                'bug_assigned',
                sprintf('%s a assigné « %s »', $request->user()->name, $bug->title),
                $bug,
                ['assignee_id' => $bug->assignee_id],
            );
        }

        if (
            $bug->assignee_id
            && (int) $bug->assignee_id !== (int) $previousAssignee
            && (int) $bug->assignee_id !== (int) $request->user()->id
        ) {
            PanelNotifier::send(
                $bug->assignee_id,
                UserNotification::TYPE_BUG_ASSIGNED,
                'Bug assigné',
                sprintf('« %s » vous a été assigné', $bug->title),
                route('projects.show', $project->slug).'?tab=bugs',
                ['project_id' => $project->id, 'bug_id' => $bug->id],
            );
        }

        return $this->apiOrBack($request, [
            'bug' => $this->bugPayload($bug->fresh(['reporter:id,name,avatar_path', 'assignee:id,name', 'assignedRank:id,name'])),
        ]);
    }

    public function linkTask(Request $request, Project $project, Bug $bug): JsonResponse|RedirectResponse
    {
        $this->ensureCanManageBug($request, $project, $bug);
        $this->ensureFeature($request, $project, 'bugs');
        abort_unless($bug->project_id === $project->id, 404);

        $validated = $request->validate([
            'task_id' => ['required', 'integer'],
        ]);

        $task = Task::query()->findOrFail($validated['task_id']);
        abort_unless($task->project_id === $project->id, 422);

        $bug->update(['task_id' => $task->id]);

        return $this->apiOrBack($request, [
            'bug' => $this->bugPayload($bug->fresh(['reporter:id,name,avatar_path', 'assignee:id,name', 'assignedRank:id,name'])),
        ]);
    }

    public function createTaskFromBug(Request $request, Project $project, Bug $bug): JsonResponse|RedirectResponse
    {
        $this->ensureCanManageBug($request, $project, $bug);
        $this->ensureFeature($request, $project, 'bugs');
        abort_unless($bug->project_id === $project->id, 404);

        // Crée la tâche dans le Kanban du rang assigné au bug (le cas échéant),
        // sinon sur le tableau global (rank_id null).
        $rankId = $bug->assigned_rank_id;

        $scopedLists = fn () => $rankId === null
            ? $project->lists()->whereNull('rank_id')
            : $project->lists()->where('rank_id', $rankId);

        $list = $scopedLists()
            ->where('status_kind', TaskList::STATUS_TODO)
            ->orderBy('position')
            ->first()
            ?? $scopedLists()->orderBy('position')->first();

        // Repli sur le tableau global si le rang assigné n'a aucune colonne.
        if (! $list && $rankId !== null) {
            $list = $project->lists()->whereNull('rank_id')
                ->where('status_kind', TaskList::STATUS_TODO)
                ->orderBy('position')
                ->first()
                ?? $project->lists()->whereNull('rank_id')->orderBy('position')->first();
        }

        abort_if(! $list, 422, 'Aucune colonne disponible pour créer la tâche.');

        $position = ((int) Task::query()->where('list_id', $list->id)->max('position')) + 1;

        $task = Task::query()->create([
            'project_id' => $project->id,
            'list_id' => $list->id,
            'assignee_id' => $bug->assignee_id,
            'title' => $bug->title,
            'description' => $bug->description,
            'priority' => $bug->priority,
            'status' => $list->status_kind,
            'position' => $position,
            'progress' => $this->guessProgressFromList($project, $list),
            'completed_at' => $list->status_kind === TaskList::STATUS_DONE ? now() : null,
        ]);

        $bug->update(['task_id' => $task->id]);

        return $this->apiOrBack($request, [
            'bug' => $this->bugPayload($bug->fresh(['reporter:id,name,avatar_path', 'assignee:id,name', 'assignedRank:id,name'])),
            'task_id' => $task->id,
        ]);
    }

    /** @todo share with TaskController */
    private function guessProgressFromList(Project $project, TaskList $list): int
    {
        $ids = $project->lists()->orderBy('position')->pluck('id')->all();
        $count = count($ids);
        if ($count <= 1) {
            return 0;
        }
        $idx = array_search($list->id, $ids, true);
        if ($idx === false) {
            return 0;
        }

        return (int) round(($idx / ($count - 1)) * 100);
    }

    public function destroy(Request $request, Project $project, Bug $bug): JsonResponse|RedirectResponse
    {
        $this->ensureCanManageBug($request, $project, $bug);
        $this->ensureFeature($request, $project, 'bugs');
        abort_unless($bug->project_id === $project->id, 404);

        foreach ($bug->screenshots ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $bugId = $bug->id;
        $bug->delete();

        return $this->apiOrBack($request, ['bug_id' => $bugId]);
    }

    /** @return array<string, mixed> */
    private function bugPayload(Bug $bug): array
    {
        return [
            'id' => $bug->id,
            'title' => $bug->title,
            'description' => $bug->description,
            'priority' => $bug->priority,
            'status' => $bug->status,
            'task_id' => $bug->task_id,
            'sla_due_at' => optional($bug->sla_due_at)?->toIso8601String(),
            'is_sla_breached' => $bug->sla_due_at
                && $bug->sla_due_at->isPast()
                && $bug->status !== Bug::STATUS_CLOSED,
            'created_at' => optional($bug->created_at)?->toIso8601String(),
            'reporter' => $bug->reporter ? ['id' => $bug->reporter->id, 'name' => $bug->reporter->name, 'avatar_url' => $bug->reporter->avatar_url] : null,
            'assignee' => $bug->assignee ? ['id' => $bug->assignee->id, 'name' => $bug->assignee->name] : null,
            'assigned_rank' => $bug->assignedRank ? ['id' => $bug->assignedRank->id, 'name' => $bug->assignedRank->name] : null,
            'screenshots' => collect($bug->screenshots ?? [])->map(fn ($p) => '/storage/'.ltrim($p, '/'))->values(),
        ];
    }

    private function ensureMember(Request $request, Project $project): void
    {
        $user = $request->user();
        abort_unless(
            $user->is_admin || $project->members()->whereKey($user->id)->exists(),
            403,
        );
    }

    private function ensureCanManageBug(Request $request, Project $project, Bug $bug): void
    {
        abort_unless(
            BugVisibility::canManage($request->user(), $bug, $project),
            403,
        );
    }
}
