<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\Project;
use App\Support\ProjectAccess;
use App\Support\ProjectSpace;
use App\Support\SpaceChatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatMessageController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        $space = ProjectSpace::resolve($request, $project, $user);
        $this->authorizeSpace($user, $project, $space);

        $messages = $project->chatMessages()
            ->where('space_key', $space->key)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => $m->toPayload())
            ->values();

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        $space = ProjectSpace::resolve($request, $project, $user);
        $this->authorizeSpace($user, $project, $space);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $project->chatMessages()->create([
            'user_id' => $user->id,
            'rank_id' => $space->rankIdForCreate(),
            'space_key' => $space->key,
            'body' => trim($validated['body']),
        ]);

        $message->load('user:id,name');

        ChatMessageSent::dispatch($message);

        return response()->json(['message' => $message->toPayload()]);
    }

    private function authorizeSpace($user, Project $project, ProjectSpace $space): void
    {
        ProjectAccess::ensureAccess($user, $project);
        abort_unless(SpaceChatAccess::canAccess($user, $project, $space->key), 403);
        abort_if($space->isFull, 403);
    }
}
