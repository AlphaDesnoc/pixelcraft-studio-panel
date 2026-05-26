<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskTagController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->is_admin || $project->members()->whereKey($user->id)->exists(),
            403,
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        TaskTag::query()->create([
            'project_id' => $project->id,
            'name' => trim($validated['name']),
            'color' => $validated['color'] ?? '#7c5cff',
        ]);

        return back();
    }

    public function sync(Request $request, Project $project, Task $task): RedirectResponse
    {
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

        $allowed = TaskTag::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        $task->tags()->sync($allowed);

        return back();
    }
}
