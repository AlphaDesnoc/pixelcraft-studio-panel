<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Project;
use App\Models\ProjectAutomationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectAutomationController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->ensureFeature($request, $project, 'kanban');

        $rules = ProjectAutomationRule::query()
            ->where('project_id', $project->id)
            ->orderBy('name')
            ->get()
            ->map->toPayload();

        return response()->json(['rules' => $rules]);
    }

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trigger' => ['required', Rule::in([
                ProjectAutomationRule::TRIGGER_BUG_CRITICAL,
                ProjectAutomationRule::TRIGGER_TASK_DONE,
                ProjectAutomationRule::TRIGGER_BUG_SLA_BREACH,
            ])],
            'trigger_config' => ['nullable', 'array'],
            'action' => ['required', Rule::in([
                ProjectAutomationRule::ACTION_ASSIGN_RANK,
                ProjectAutomationRule::ACTION_NOTIFY_MANAGER,
                ProjectAutomationRule::ACTION_LOG_ACTIVITY,
            ])],
            'action_config' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule = ProjectAutomationRule::query()->create([
            'project_id' => $project->id,
            ...$validated,
        ]);

        return $this->apiOrBack($request, ['rule' => $rule->toPayload()]);
    }

    public function update(Request $request, Project $project, ProjectAutomationRule $rule): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($rule->project_id === $project->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'trigger' => ['sometimes', Rule::in([
                ProjectAutomationRule::TRIGGER_BUG_CRITICAL,
                ProjectAutomationRule::TRIGGER_TASK_DONE,
                ProjectAutomationRule::TRIGGER_BUG_SLA_BREACH,
            ])],
            'trigger_config' => ['sometimes', 'nullable', 'array'],
            'action' => ['sometimes', Rule::in([
                ProjectAutomationRule::ACTION_ASSIGN_RANK,
                ProjectAutomationRule::ACTION_NOTIFY_MANAGER,
                ProjectAutomationRule::ACTION_LOG_ACTIVITY,
            ])],
            'action_config' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule->update($validated);

        return $this->apiOrBack($request, ['rule' => $rule->fresh()->toPayload()]);
    }

    public function destroy(Request $request, Project $project, ProjectAutomationRule $rule): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($rule->project_id === $project->id, 404);

        $rule->delete();

        return $this->apiOrBack($request, ['ok' => true]);
    }
}
