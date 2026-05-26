<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskTagController extends Controller
{
    use EnsuresProjectFeature;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:16'],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
        ]);

        TaskTag::query()->create([
            'project_id' => $project->id,
            'rank_id' => $validated['rank_id'] ?? null,
            'name' => trim($validated['name']),
            'color' => $validated['color'] ?? '#7c5cff',
        ]);

        return back();
    }

    public function sync(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id, 404);

        $validated = $request->validate([
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer'],
        ]);

        $ids = collect($validated['tag_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $task->loadMissing('list:id,rank_id');
        $taskRankId = $task->list?->rank_id;

        $allowed = TaskTag::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $ids)
            ->where(function ($query) use ($taskRankId) {
                if ($taskRankId === null) {
                    $query->whereNull('rank_id');
                } else {
                    $query->where('rank_id', $taskRankId);
                }
            })
            ->pluck('id')
            ->all();

        $task->tags()->sync($allowed);

        return back();
    }

    public function destroy(Request $request, Project $project, TaskTag $tag): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($tag->project_id === $project->id, 404);

        $tag->tasks()->detach();
        $tag->delete();

        return back();
    }
}
