<?php

namespace App\Support;

use App\Models\DirectConversation;
use App\Models\User;

class DirectConversationPayload
{
    public static function serialize(DirectConversation $conv, User $viewer): array
    {
        $other = $conv->otherParticipant($viewer);
        $latest = $conv->relationLoaded('messages') ? $conv->messages->first() : null;

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
