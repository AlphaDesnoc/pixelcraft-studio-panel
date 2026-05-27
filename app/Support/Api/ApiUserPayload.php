<?php

namespace App\Support\Api;

use App\Models\User;

class ApiUserPayload
{
    public static function make(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
        ];
    }
}
