<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskChecklistController extends Controller
{
    use EnsuresProjectFeature;

    public function store(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        $this->ensureCanEdit($request, $project, $task);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $task->checklists()->create([
            'name' => $validated['name'],
            'position' => (int) ($task->checklists()->max('position') + 1),
        ]);

        return back();
    }

    public function update(Request $request, Project $project, Task $task, TaskChecklist $checklist): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        $this->ensureCanEdit($request, $project, $task);
        abort_unless($checklist->task_id === $task->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $checklist->update(['name' => $validated['name']]);

        return back();
    }

    public function destroy(Request $request, Project $project, Task $task, TaskChecklist $checklist): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        $this->ensureCanEdit($request, $project, $task);
        abort_unless($checklist->task_id === $task->id, 404);

        $checklist->delete();

        return back();
    }

    public function storeItem(Request $request, Project $project, Task $task, TaskChecklist $checklist): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        $this->ensureCanEdit($request, $project, $task);
        abort_unless($checklist->task_id === $task->id, 404);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:500'],
        ]);

        $checklist->items()->create([
            'content' => $validated['content'],
            'position' => (int) ($checklist->items()->max('position') + 1),
        ]);

        return back();
    }

    public function updateItem(Request $request, Project $project, Task $task, TaskChecklist $checklist, TaskChecklistItem $item): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        $this->ensureCanEdit($request, $project, $task);
        abort_unless($checklist->task_id === $task->id, 404);
        abort_unless($item->checklist_id === $checklist->id, 404);

        $validated = $request->validate([
            'content' => ['sometimes', 'required', 'string', 'max:500'],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_done', $validated)) {
            $validated['completed_at'] = $validated['is_done'] ? now() : null;
        }

        $item->update($validated);

        return back();
    }

    public function destroyItem(Request $request, Project $project, Task $task, TaskChecklist $checklist, TaskChecklistItem $item): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'kanban');
        $this->ensureCanEdit($request, $project, $task);
        abort_unless($checklist->task_id === $task->id, 404);
        abort_unless($item->checklist_id === $checklist->id, 404);

        DB::transaction(function () use ($checklist, $item) {
            $item->delete();
            $checklist->items()
                ->orderBy('position')
                ->get()
                ->values()
                ->each(fn ($it, $i) => $it->update(['position' => $i]));
        });

        return back();
    }

    private function ensureCanEdit(Request $request, Project $project, Task $task): void
    {
        $user = $request->user();
        $isAdmin = $user->is_admin;
        $isMember = $project->members()->whereKey($user->id)->exists();
        abort_unless($isAdmin || $isMember, 403);
        abort_unless($task->project_id === $project->id, 404);
    }
}
