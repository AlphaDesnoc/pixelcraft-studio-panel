<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    use EnsuresProjectFeature;

    public function store(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id, 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        $task->loadMissing('list:id,rank_id');

        ActivityLogger::log(
            $project,
            $request->user(),
            'task_commented',
            sprintf('%s a commenté « %s »', $request->user()->name, $task->title),
            $task,
            [
                'comment_id' => $comment->id,
                'rank_id' => $task->list?->rank_id,
                'task_title' => $task->title,
            ],
        );

        return back();
    }

    public function destroy(Request $request, Project $project, Task $task, TaskComment $comment): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'kanban');
        abort_unless($task->project_id === $project->id && $comment->task_id === $task->id, 404);
        abort_unless(
            $comment->user_id === $request->user()->id || $request->user()->is_admin,
            403,
        );

        $comment->delete();

        return back();
    }

    private function ensureCanEdit(Request $request, Project $project): void
    {
        $user = $request->user();
        abort_unless(
            $user->is_admin || $project->members()->whereKey($user->id)->exists(),
            403,
        );
    }
}
