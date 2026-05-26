<?php

namespace App\Http\Controllers;

use App\Events\ChatReactionUpdated;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\Project;
use App\Support\ProjectAccess;
use App\Support\ProjectSpace;
use App\Support\SpaceChatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatReactionController extends Controller
{
    private const ALLOWED = ['👍', '✅', '❤️', '😂'];

    public function toggle(Request $request, Project $project, ChatMessage $message): JsonResponse
    {
        $user = $request->user();
        $space = ProjectSpace::resolve($request, $project, $user);
        ProjectAccess::ensureAccess($user, $project);
        abort_unless(SpaceChatAccess::canAccess($user, $project, $space->key), 403);
        abort_unless($message->project_id === $project->id, 404);

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        abort_unless(in_array($validated['emoji'], self::ALLOWED, true), 422);

        $existing = ChatMessageReaction::query()
            ->where('chat_message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $validated['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            ChatMessageReaction::query()->create([
                'chat_message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $validated['emoji'],
            ]);
        }

        $reactions = $this->groupedReactions($message);

        ChatReactionUpdated::dispatch(
            $message->id,
            $project->id,
            $message->space_key,
            $reactions,
        );

        return response()->json(['reactions' => $reactions]);
    }

    /** @return array<int, array{emoji: string, count: int, users: array<int, string>}> */
    public static function groupedReactions(ChatMessage $message): array
    {
        return ChatMessageReaction::query()
            ->where('chat_message_id', $message->id)
            ->with('user:id,name')
            ->get()
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'users' => $group->pluck('user.name')->filter()->values()->all(),
            ])
            ->values()
            ->all();
    }
}
