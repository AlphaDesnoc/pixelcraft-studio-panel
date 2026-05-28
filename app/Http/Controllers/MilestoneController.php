<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MilestoneController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->ensureFeature($request, $project, 'kanban');

        $milestones = Milestone::query()
            ->where('project_id', $project->id)
            ->with('tasks:id,status')
            ->orderBy('position')
            ->get()
            ->map->toPayload();

        return response()->json(['milestones' => $milestones]);
    }

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'task_ids' => ['sometimes', 'array'],
            'task_ids.*' => ['integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
        ]);

        $position = (int) Milestone::where('project_id', $project->id)->max('position') + 1;

        $milestone = Milestone::query()->create([
            'project_id' => $project->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'position' => $position,
        ]);

        if (! empty($validated['task_ids'])) {
            $milestone->tasks()->sync($validated['task_ids']);
        }

        $milestone->load('tasks:id,status');

        return $this->apiOrBack($request, ['milestone' => $milestone->toPayload()]);
    }

    public function update(Request $request, Project $project, Milestone $milestone): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($milestone->project_id === $project->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'task_ids' => ['sometimes', 'array'],
            'task_ids.*' => ['integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
        ]);

        $milestone->update(collect($validated)->except('task_ids')->all());

        if (array_key_exists('task_ids', $validated)) {
            $milestone->tasks()->sync($validated['task_ids']);
        }

        $milestone->load('tasks:id,status');

        return $this->apiOrBack($request, ['milestone' => $milestone->fresh()->toPayload()]);
    }

    public function destroy(Request $request, Project $project, Milestone $milestone): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($milestone->project_id === $project->id, 404);

        $milestone->delete();

        return $this->apiOrBack($request, ['ok' => true]);
    }
}
