<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $user = $request->user();
        $q = $validated['q'];
        $like = '%'.$q.'%';

        $projectIds = Project::query()
            ->when(! $user->is_admin, fn ($query) => $query->whereHas(
                'members',
                fn ($m) => $m->whereKey($user->id),
            ))
            ->pluck('id');

        $projects = Project::query()
            ->whereIn('id', $projectIds)
            ->where('name', 'like', $like)
            ->limit(5)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Project $p) => [
                'type' => 'project',
                'label' => $p->name,
                'url' => route('projects.show', $p->slug),
            ]);

        $tasks = Task::query()
            ->whereIn('project_id', $projectIds)
            ->whereNull('archived_at')
            ->where('title', 'like', $like)
            ->with('project:id,slug,name')
            ->limit(8)
            ->get()
            ->map(fn (Task $t) => [
                'type' => 'task',
                'label' => $t->title,
                'meta' => $t->project?->name,
                'url' => route('projects.show', $t->project->slug).'?tab=kanban',
            ]);

        $bugs = Bug::query()
            ->whereIn('project_id', $projectIds)
            ->where('title', 'like', $like)
            ->when(! $user->is_admin, function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->whereNull('assigned_rank_id')
                        ->orWhere('reporter_id', $user->id)
                        ->orWhereHas(
                            'assignedRank.members',
                            fn ($m) => $m->whereKey($user->id),
                        );
                });
            })
            ->with('project:id,slug,name')
            ->limit(8)
            ->get()
            ->map(fn (Bug $b) => [
                'type' => 'bug',
                'label' => $b->title,
                'meta' => $b->project?->name,
                'url' => route('projects.show', $b->project->slug).'?tab=bugs',
            ]);

        $messages = ChatMessage::query()
            ->whereIn('project_id', $projectIds)
            ->where('body', 'like', $like)
            ->with('project:id,slug,name')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ChatMessage $m) => [
                'type' => 'chat',
                'label' => str($m->body)->limit(60),
                'meta' => $m->project?->name,
                'url' => route('projects.show', $m->project->slug).'?space='.$m->space_key.'&tab=chat',
            ]);

        $members = User::query()
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->whereHas('projects', fn ($p) => $p->whereIn('projects.id', $projectIds))
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'type' => 'member',
                'label' => $u->name,
                'meta' => $u->email,
                'url' => route('messages.index'),
            ]);

        return response()->json([
            'results' => collect()
                ->merge($projects)
                ->merge($tasks)
                ->merge($bugs)
                ->merge($messages)
                ->merge($members)
                ->values(),
        ]);
    }
}
