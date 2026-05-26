<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use App\Models\DirectMessageReaction;
use App\Support\DirectMessageAccess;
use App\Support\EmojiValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectMessageReactionController extends Controller
{
    public function toggle(Request $request, DirectMessage $message): JsonResponse
    {
        $user = $request->user();
        $conversation = $message->conversation;
        DirectMessageAccess::ensureAccess($user, $conversation);

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:32'],
        ]);

        abort_unless(EmojiValidator::isReactionEmoji($validated['emoji']), 422);

        $existing = DirectMessageReaction::query()
            ->where('direct_message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $validated['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            DirectMessageReaction::query()->create([
                'direct_message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $validated['emoji'],
            ]);
        }

        return response()->json([
            'reactions' => self::groupedReactions($message, $user->id),
        ]);
    }

    /** @return array<int, array{emoji: string, count: int, users: array<int, string>, me: bool}> */
    public static function groupedReactions(DirectMessage $message, ?int $viewerId = null): array
    {
        $viewerId ??= auth()->id();

        return DirectMessageReaction::query()
            ->where('direct_message_id', $message->id)
            ->with('user:id,name')
            ->get()
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'users' => $group->pluck('user.name')->filter()->values()->all(),
                'me' => $viewerId ? $group->contains('user_id', $viewerId) : false,
            ])
            ->values()
            ->all();
    }
}
