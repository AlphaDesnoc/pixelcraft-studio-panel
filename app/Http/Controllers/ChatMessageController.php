<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Events\ChatMessageDeleted;
use App\Events\ChatMessageSent;
use App\Events\ChatMessageUpdated;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\MentionParser;
use App\Support\PanelNotifier;
use App\Support\ProjectAccess;
use App\Support\ProjectPermissions;
use App\Support\ProjectSpace;
use App\Support\SpaceChatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatMessageController extends Controller
{
    use EnsuresProjectFeature;

    public function index(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        $space = ProjectSpace::resolve($request, $project, $user);
        $this->authorizeSpace($user, $project, $space);

        $messages = $project->chatMessages()
            ->where('space_key', $space->key)
            ->with(['user:id,name', 'attachments', 'replyTo.user:id,name'])
            ->orderByDesc('pinned_at')
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
        $this->authorizeSpaceWrite($user, $project, $space);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:chat_messages,id'],
        ]);

        $body = trim($validated['body']);
        $eligible = SpaceChatAccess::eligibleUsers($project, $space->key);
        $ranks = $space->isGlobal ? $project->ranks()->orderBy('position')->get() : null;
        $mentions = MentionParser::extract($body, $eligible, $ranks);

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

        $mentionedIds = MentionParser::notifiedUserIds($project, $mentions);

        foreach ($mentionedIds as $memberId) {
            if ($memberId === $user->id) {
                continue;
            }

            $member = User::query()->find($memberId);
            if (! $member) {
                continue;
            }

            PanelNotifier::send(
                $member,
                UserNotification::TYPE_CHAT_MENTION,
                'Mention dans le chat',
                sprintf('%s vous a mentionné : %s', $user->name, str($body)->limit(80)),
                $url,
                ['project_id' => $project->id, 'message_id' => $message->id],
            );
        }

        ChatMessageSent::dispatch($message);

        return response()->json(['message' => $message->toPayload()]);
    }

    public function update(Request $request, Project $project, ChatMessage $message): JsonResponse
    {
        $user = $request->user();
        $space = ProjectSpace::resolve($request, $project, $user);
        $this->authorizeSpaceWrite($user, $project, $space);
        abort_unless($message->project_id === $project->id, 404);
        abort_unless($message->canEditBy($user), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $body = trim($validated['body']);
        $eligible = SpaceChatAccess::eligibleUsers($project, $message->space_key);
        $ranks = $message->space_key === ProjectSpace::GLOBAL
            ? $project->ranks()->orderBy('position')->get()
            : null;

        $message->update([
            'body' => $body,
            'mentions' => MentionParser::extract($body, $eligible, $ranks),
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
        $this->authorizeSpaceWrite($user, $project, $space);
        abort_unless($message->project_id === $project->id, 404);
        abort_unless($message->canEditBy($user), 403);

        $messageId = $message->id;
        $spaceKey = $message->space_key;
        $message->delete();

        ChatMessageDeleted::dispatch($messageId, $project->id, $spaceKey);

        return response()->json(['ok' => true]);
    }

    public function pin(Request $request, Project $project, ChatMessage $message): JsonResponse
    {
        $user = $request->user();
        $space = ProjectSpace::resolve($request, $project, $user);
        $this->authorizeSpaceWrite($user, $project, $space);
        abort_unless($message->project_id === $project->id, 404);

        if ($message->pinned_at) {
            $message->update(['pinned_at' => null, 'pinned_by' => null]);
        } else {
            $message->update(['pinned_at' => now(), 'pinned_by' => $user->id]);
        }

        $message->load(['user:id,name', 'attachments', 'replyTo.user:id,name']);

        return response()->json(['message' => $message->toPayload()]);
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
        abort_unless(ProjectPermissions::canRead($user, $project, 'chat'), 403);
        abort_unless(SpaceChatAccess::canAccess($user, $project, $space->key), 403);
        abort_if($space->isFull, 403);
    }

    private function authorizeSpaceWrite($user, Project $project, ProjectSpace $space): void
    {
        $this->authorizeSpace($user, $project, $space);
        abort_unless(ProjectPermissions::canWrite($user, $project, 'chat'), 403);
    }
}
