<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserNotification;

class NotificationPreferences
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            UserNotification::TYPE_CHAT_MENTION => 'Mentions dans le chat',
            UserNotification::TYPE_CHAT_MESSAGE => 'Messages chat projet',
            UserNotification::TYPE_DIRECT_MESSAGE => 'Messages privés',
            UserNotification::TYPE_TASK_ASSIGNED => 'Tâche assignée',
            UserNotification::TYPE_BUG_ASSIGNED => 'Bug assigné',
            UserNotification::TYPE_DUE_TOMORROW => 'Échéance demain (J-1)',
            UserNotification::TYPE_DUE_TODAY => 'Échéance aujourd\'hui (jour J)',
            UserNotification::TYPE_OVERDUE => 'Tâche en retard',
            UserNotification::TYPE_CALENDAR_REMINDER => 'Rappels calendrier',
            UserNotification::TYPE_BUG_SLA_BREACH => 'Alertes SLA bugs',
            UserNotification::TYPE_CALL_INCOMING => 'Appels entrants',
        ];
    }

    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return array_fill_keys(array_keys(self::labels()), true);
    }

    /** @return array<string, bool> */
    public static function forUser(User $user): array
    {
        $stored = is_array($user->notification_preferences)
            ? $user->notification_preferences
            : [];

        return array_merge(self::defaults(), $stored);
    }

    public static function allows(User|int $user, string $type): bool
    {
        if (! array_key_exists($type, self::labels())) {
            return true;
        }

        $model = $user instanceof User
            ? $user
            : User::query()->find($user);

        if (! $model) {
            return false;
        }

        return self::forUser($model)[$type] ?? true;
    }

    /** @param  array<string, bool>  $input */
    public static function sanitize(array $input): array
    {
        $result = self::defaults();

        foreach (self::labels() as $type => $_label) {
            if (array_key_exists($type, $input)) {
                $result[$type] = (bool) $input[$type];
            }
        }

        return $result;
    }
}
