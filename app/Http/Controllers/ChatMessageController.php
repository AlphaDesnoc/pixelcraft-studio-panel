<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageDeleted;
use App\Events\ChatMessageSent;
use App\Events\ChatMessageUpdated;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\UserNotification;
use App\Support\MentionParser;
use App\Support\PanelNotifier;
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
            ->with(['user:id,name', 'attachments', 'replyTo.user:id,name'])
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
            'reply_to_id' => ['nullable', 'integer', 'exists:chat_messages,id'],
        ]);

        $body = trim($validated['body']);
        $eligible = SpaceChatAccess::eligibleUsers($project, $space->key);
        $mentions = MentionParser::extract($body, $eligible);

        $message = $project->chatMessages()->create([
            'user_id' => $user->id,
            'rank_id' => $space->rankIdForCreate(),
            'space_key' => $space->key,
            'body' => $body,
            'mentions' => $mentions,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ]);

        $message->load(['user:id,name', 'attachments', 'replyTo.user:id,name']);
        $url = route('projects.show', $project->slug).'?space='.$space->key.'&tab=chat';

        $mentionedIds = collect($mentions)->pluck('id');

        foreach ($eligible as $member) {
            if ($member->id === $user->id) {
                continue;
            }

            if ($mentionedIds->contains($member->id)) {
                PanelNotifier::send(
                    $member,
                    UserNotification::TYPE_CHAT_MENTION,
                    'Mention dans le chat',
                    sprintf('%s vous a mentionné : %s', $user->name, str($body)->limit(80)),
                    $url,
                    ['project_id' => $project->id, 'message_id' => $message->id],
                );
            }
        }

        ChatMessageSent::dispatch($message);

        return response()->json(['message' => $message->toPayload()]);
    }

    public function update(Request $request, Project $project, ChatMessage $message): JsonResponse
    {
        $user = $request->user();
        $space = ProjectSpace::resolve($request, $project, $user);
        $this->authorizeSpace($user, $project, $space);
        abort_unless($message->project_id === $project->id, 404);
        abort_unless($message->canEditBy($user), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $body = trim($validated['body']);
        $eligible = SpaceChatAccess::eligibleUsers($project, $message->space_key);

        $message->update([
            'body' => $body,
            'mentions' => MentionParser::extract($body, $eligible),
            'edited_at' => now(),
        ]);

        $message->load('user:id,name', 'attachments');
        ChatMessageUpdated::dispatch($message);

        return response()->json(['message' => $message->toPayload()]);
    }

    public function destroy(Request $request, Project $project, ChatMessage $message): JsonResponse
    {
        $user = $request->user();
        $space = ProjectSpace::resolve($request, $project, $user);
        $this->authorizeSpace($user, $project, $space);
        abort_unless($message->project_id === $project->id, 404);
        abort_unless($message->canEditBy($user), 403);

        $messageId = $message->id;
        $spaceKey = $message->space_key;
        $message->delete();

        ChatMessageDeleted::dispatch($messageId, $project->id, $spaceKey);

        return response()->json(['ok' => true]);
    }

    public function presence(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        $space = ProjectSpace::resolve($request, $project, $user);
        $this->authorizeSpace($user, $project, $space);

        $members = SpaceChatAccess::membersWithPresence($project, $space->key, $user);

        return response()->json([
            'members' => $members,
            'online_count' => collect($members)->where('is_online', true)->count(),
        ]);
    }

    private function authorizeSpace($user, Project $project, ProjectSpace $space): void
    {
        ProjectAccess::ensureAccess($user, $project);
        abort_unless(SpaceChatAccess::canAccess($user, $project, $space->key), 403);
        abort_if($space->isFull, 403);
    }
}
