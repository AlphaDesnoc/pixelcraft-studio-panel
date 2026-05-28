<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\KanbanSavedView;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KanbanSavedViewController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->ensureFeature($request, $project, 'kanban');

        $views = KanbanSavedView::query()
            ->where('project_id', $project->id)
            ->where(function ($q) use ($request) {
                $q->where('is_shared', true)
                    ->orWhere('user_id', $request->user()->id);
            })
            ->orderBy('name')
            ->get()
            ->map->toPayload();

        return response()->json(['views' => $views]);
    }

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['required', 'array'],
            'is_shared' => ['sometimes', 'boolean'],
        ]);

        $view = KanbanSavedView::query()->create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'filters' => $validated['filters'],
            'is_shared' => $validated['is_shared'] ?? true,
        ]);

        return $this->apiOrBack($request, ['view' => $view->toPayload()]);
    }

    public function update(Request $request, Project $project, KanbanSavedView $view): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($view->project_id === $project->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'filters' => ['sometimes', 'array'],
            'is_shared' => ['sometimes', 'boolean'],
        ]);

        $view->update($validated);

        return $this->apiOrBack($request, ['view' => $view->fresh()->toPayload()]);
    }

    public function destroy(Request $request, Project $project, KanbanSavedView $view): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($view->project_id === $project->id, 404);

        $view->delete();

        return $this->apiOrBack($request, ['ok' => true]);
    }
}
