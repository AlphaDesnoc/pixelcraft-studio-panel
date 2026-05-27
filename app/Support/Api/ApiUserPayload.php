<?php

namespace App\Support\Api;

use App\Models\User;
use App\Support\NotificationPreferences;

class ApiUserPayload
{
    public static function make(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_admin' => (bool) $user->is_admin,
            'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
            'notification_preferences' => NotificationPreferences::forUser($user),
        ];
    }
}
