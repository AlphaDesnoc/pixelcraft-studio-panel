<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\UserNotification;
use App\Support\ActivityLogger;
use App\Support\MentionParser;
use App\Support\PanelNotifier;
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

        $body = trim($validated['body']);

        $comment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'body' => $body,
        ]);

        $candidates = $project->members()->get(['users.id', 'users.email', 'users.name']);
        $mentions = MentionParser::extract($body, $candidates);
        $notified = MentionParser::notifiedUserIds($project, $mentions)
            ->reject(fn ($id) => (int) $id === (int) $request->user()->id);

        foreach ($notified as $userId) {
            PanelNotifier::send(
                (int) $userId,
                UserNotification::TYPE_TASK_COMMENT_MENTION,
                'Mention dans un commentaire',
                sprintf('%s vous a mentionné sur « %s »', $request->user()->name, $task->title),
                route('projects.show', $project->slug).'?tab=kanban&task='.$task->id,
                ['project_id' => $project->id, 'task_id' => $task->id],
            );
        }

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
}
