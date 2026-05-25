<?php

namespace App\Support;

use App\Models\User;

class PresenceUser
{
    public static function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
