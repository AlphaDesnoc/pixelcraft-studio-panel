<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskTemplateController extends Controller
{
    use EnsuresProjectFeature;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'rank_id' => ['nullable', 'integer', Rule::exists('ranks', 'id')->where('project_id', $project->id)],
            'checklist' => ['nullable', 'array'],
        ]);

        $project->taskTemplates()->create($validated);

        return back();
    }

    public function apply(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id, 404);

        $validated = $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('task_templates', 'id')->where('project_id', $project->id)],
        ]);

        $template = TaskTemplate::query()->findOrFail($validated['template_id']);

        $task->update([
            'title' => $template->title,
            'description' => $template->description,
            'priority' => $template->priority,
        ]);

        if ($template->checklist) {
            $checklist = $task->checklists()->create([
                'name' => $template->name,
                'position' => 0,
            ]);
            foreach ($template->checklist as $index => $item) {
                $checklist->items()->create([
                    'content' => is_string($item) ? $item : ($item['content'] ?? ''),
                    'position' => $index,
                    'is_done' => false,
                ]);
            }
        }

        return back();
    }
}
