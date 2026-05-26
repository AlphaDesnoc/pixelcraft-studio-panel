<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\ResolvesProjectSpace;
use App\Models\Project;
use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskListController extends Controller
{
    use EnsuresProjectFeature;
    use ResolvesProjectSpace;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        $this->ensureCanEdit($request, $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'status_kind' => ['nullable', Rule::in(TaskList::STATUS_KINDS)],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
        ]);

        $rankId = $validated['rank_id'] ?? null;

        $project->lists()->create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#9ca3af',
            'status_kind' => $validated['status_kind'] ?? TaskList::STATUS_TODO,
            'position' => (int) ($project->lists()->where('rank_id', $rankId)->max('position') + 1),
            'rank_id' => $rankId,
        ]);

        return back();
    }

    public function update(Request $request, Project $project, TaskList $list): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        $this->ensureCanEdit($request, $project);
        $this->ensureBelongs($project, $list);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'status_kind' => ['nullable', Rule::in(TaskList::STATUS_KINDS)],
        ]);

        $list->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? $list->color,
            'status_kind' => $validated['status_kind'] ?? $list->status_kind,
        ]);

        return back();
    }

    public function destroy(Request $request, Project $project, TaskList $list): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        $this->ensureCanEdit($request, $project);
        $this->ensureBelongs($project, $list);

        DB::transaction(function () use ($project, $list) {
            $list->tasks()->delete();
            $list->delete();

            $project->lists()
                ->where('rank_id', $list->rank_id)
                ->orderBy('position')
                ->get()
                ->values()
                ->each(fn ($l, $i) => $l->update(['position' => $i]));
        });

        return back();
    }

    public function reorder(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        $this->ensureCanEdit($request, $project);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'distinct'],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
        ]);

        $rankId = $validated['rank_id'] ?? null;
        $projectListIds = $project->lists()->where('rank_id', $rankId)->pluck('id')->all();

        foreach ($validated['order'] as $id) {
            abort_unless(in_array($id, $projectListIds, true), 404);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $position => $id) {
                TaskList::whereKey($id)->update(['position' => $position]);
            }
        });

        return back();
    }

    private function ensureCanEdit(Request $request, Project $project): void
    {
        $user = $request->user();
        $isAdmin = $user->is_admin;
        $isMember = $project->members()->whereKey($user->id)->exists();
        abort_unless($isAdmin || $isMember, 403);
    }

    private function ensureBelongs(Project $project, TaskList $list): void
    {
        abort_unless($list->project_id === $project->id, 404);
    }
}
