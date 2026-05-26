<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\Project;
use App\Models\Rank;
use App\Models\UserNotification;
use App\Support\PanelNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BugController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureMember($request, $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', Rule::in(array_keys(Bug::PRIORITIES))],
            'screenshots' => ['nullable', 'array', 'max:5'],
            'screenshots.*' => ['image', 'max:5120'],
        ]);

        $defaultRank = $project->ranks()->where('manages_bugs', true)->orderBy('position')->first();

        $paths = [];
        if ($request->hasFile('screenshots')) {
            foreach ($request->file('screenshots') as $file) {
                $paths[] = $file->store("projects/{$project->id}/bugs", 'public');
            }
        }

        $project->bugs()->create([
            'reporter_id' => $request->user()->id,
            'assigned_rank_id' => $defaultRank?->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? Bug::PRIORITY_MEDIUM,
            'status' => Bug::STATUS_OPEN,
            'screenshots' => $paths ?: null,
        ]);

        return back();
    }

    public function update(Request $request, Project $project, Bug $bug): RedirectResponse
    {
        $this->ensureCanManage($request, $project);
        abort_unless($bug->project_id === $project->id, 404);

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

        $bug->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? $bug->priority,
            'status' => $validated['status'] ?? $bug->status,
            'assignee_id' => $validated['assignee_id'] ?? null,
            'assigned_rank_id' => array_key_exists('assigned_rank_id', $validated)
                ? $validated['assigned_rank_id']
                : $bug->assigned_rank_id,
            'screenshots' => $screenshots ?: null,
        ]);

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

        return back();
    }

    public function destroy(Request $request, Project $project, Bug $bug): RedirectResponse
    {
        $this->ensureCanManage($request, $project);
        abort_unless($bug->project_id === $project->id, 404);

        foreach ($bug->screenshots ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $bug->delete();

        return back();
    }

    private function ensureMember(Request $request, Project $project): void
    {
        $user = $request->user();
        abort_unless(
            $user->is_admin || $project->members()->whereKey($user->id)->exists(),
            403,
        );
    }

    private function ensureCanManage(Request $request, Project $project): void
    {
        $user = $request->user();
        if ($user->is_admin) {
            return;
        }

        $managesBugs = $project->ranks()
            ->where('manages_bugs', true)
            ->whereHas('members', fn ($q) => $q->whereKey($user->id))
            ->exists();

        abort_unless($managesBugs, 403);
    }
}
