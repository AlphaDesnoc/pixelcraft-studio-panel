<?php

namespace App\Support;

use App\Models\DirectConversation;
use App\Models\User;

class DirectConversationPayload
{
    public static function serialize(DirectConversation $conv, User $viewer): array
    {
        $other = $conv->otherParticipant($viewer);

        // Dernier message : on privilégie la relation dédiée `latestMessage`. En
        // repli, `messages` est trié par created_at ASC → le plus récent est le
        // dernier élément (et non le premier).
        $latest = null;
        if ($conv->relationLoaded('latestMessage')) {
            $latest = $conv->latestMessage->first();
        } elseif ($conv->relationLoaded('messages')) {
            $latest = $conv->messages->last();
        }

        return [
            'id' => $conv->id,
            'last_message_at' => optional($conv->last_message_at)?->toIso8601String(),
            'unread_count' => $conv->unreadCountFor($viewer),
            'participant' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'email' => $other->email,
            ] : null,
            'last_message' => $latest ? [
                'id' => $latest->id,
                'body' => $latest->body,
                'created_at' => $latest->created_at?->toIso8601String(),
                'user_id' => $latest->user_id,
            ] : null,
        ];
    }
}
