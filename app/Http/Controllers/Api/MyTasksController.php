<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyTasksController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tasks = Task::query()
            ->with(['project:id,name,slug', 'list:id,name'])
            ->where('assignee_id', $user->id)
            ->where('status', '!=', Task::STATUS_DONE)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'priority' => $task->priority,
                'status' => $task->status,
                'due_date' => optional($task->due_date)?->toDateString(),
                'is_overdue' => $task->isOverdue(),
                'list_name' => $task->list?->name,
                'project' => $task->project ? [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                    'slug' => $task->project->slug,
                ] : null,
            ]);

        return response()->json([
            'tasks' => $tasks,
            'priorities' => Task::PRIORITIES,
        ]);
    }
}
