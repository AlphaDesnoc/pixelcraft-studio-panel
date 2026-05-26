<?php

namespace App\Support;

use App\Events\UserNotificationSent;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\NotificationPreferences;

class PanelNotifier
{
    public static function send(
        User|int $user,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        array $data = [],
    ): ?UserNotification {
        $userId = $user instanceof User ? $user->id : $user;

        if (! NotificationPreferences::allows($userId, $type)) {
            return null;
        }

        $notification = UserNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'data' => $data,
        ]);

        UserNotificationSent::dispatch($notification);

        return $notification;
    }

    public static function unreadCount(User|int $user): int
    {
        $userId = $user instanceof User ? $user->id : $user;

        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
