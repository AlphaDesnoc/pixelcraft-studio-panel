<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Events\BugMessageSent;
use App\Models\Bug;
use App\Models\Project;
use App\Support\BugChatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BugMessageController extends Controller
{
    use EnsuresProjectFeature;

    public function index(Request $request, Project $project, Bug $bug): JsonResponse
    {
        $this->authorizeAccess($request, $project, $bug);

        $messages = $bug->messages()
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => $m->toPayload())
            ->values();

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, Project $project, Bug $bug): JsonResponse
    {
        $this->authorizeAccess($request, $project, $bug);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $bug->messages()->create([
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        $message->load('user:id,name');

        BugMessageSent::dispatch($message);

        return response()->json(['message' => $message->toPayload()]);
    }

    private function authorizeAccess(Request $request, Project $project, Bug $bug): void
    {
        abort_unless($bug->project_id === $project->id, 404);
        $this->ensureFeature($request, $project, 'bugs');

        $user = $request->user();
        abort_unless(
            $user->is_admin || $project->members()->whereKey($user->id)->exists(),
            403,
        );
        abort_unless(BugChatAccess::canAccess($user, $bug), 403);
    }
}
