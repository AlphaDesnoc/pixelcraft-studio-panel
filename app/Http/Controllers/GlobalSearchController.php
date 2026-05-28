<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\ChatMessage;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\FileNode;
use App\Models\Note;
use App\Models\Project;
use App\Models\Sheet;
use App\Models\Task;
use App\Models\User;
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
                'url' => route('projects.show', $t->project->slug).'?tab=kanban&task='.$t->id,
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
                'url' => route('projects.show', $b->project->slug).'?tab=bugs&bug='.$b->id,
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

        $notes = Note::query()
            ->whereIn('project_id', $projectIds)
            ->where(function ($query) use ($like) {
                $query->where('title', 'like', $like)
                    ->orWhere('content', 'like', $like);
            })
            ->with('project:id,slug,name')
            ->limit(6)
            ->get()
            ->map(fn (Note $n) => [
                'type' => 'note',
                'label' => $n->title,
                'meta' => $n->project?->name,
                'url' => route('projects.show', $n->project->slug).'?tab=notes',
            ]);

        $files = FileNode::query()
            ->whereIn('project_id', $projectIds)
            ->where('type', FileNode::TYPE_FILE)
            ->where('name', 'like', $like)
            ->with('project:id,slug,name')
            ->limit(6)
            ->get()
            ->map(fn (FileNode $f) => [
                'type' => 'file',
                'label' => $f->name,
                'meta' => $f->project?->name,
                'url' => route('projects.show', $f->project->slug).'?tab=files',
            ]);

        $sheets = Sheet::query()
            ->whereIn('project_id', $projectIds)
            ->where('name', 'like', $like)
            ->with('project:id,slug,name')
            ->limit(6)
            ->get()
            ->map(fn (Sheet $s) => [
                'type' => 'sheet',
                'label' => $s->name,
                'meta' => $s->project?->name,
                'url' => route('projects.show', $s->project->slug).'?tab=spreadsheet',
            ]);

        $conversationIds = DirectConversation::query()
            ->where(fn ($q) => $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id))
            ->pluck('id');

        $directMessages = DirectMessage::query()
            ->whereIn('direct_conversation_id', $conversationIds)
            ->where('body', 'like', $like)
            ->with(['user:id,name', 'conversation'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (DirectMessage $m) use ($user) {
                $other = $m->conversation?->otherParticipant($user);

                return [
                    'type' => 'dm',
                    'label' => str($m->body)->limit(60),
                    'meta' => $other?->name ?? $m->user?->name,
                    'url' => route('messages.index', ['c' => $m->direct_conversation_id]),
                ];
            });

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
                ->merge($notes)
                ->merge($files)
                ->merge($sheets)
                ->merge($messages)
                ->merge($directMessages)
                ->merge($members)
                ->values(),
        ]);
    }
}
