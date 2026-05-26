<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;

class ProjectPermissions
{
    public const FEATURES = [
        'kanban' => 'Kanban',
        'calendar' => 'Calendrier',
        'gantt' => 'Gantt',
        'notes' => 'Notes',
        'spreadsheet' => 'Tableur',
        'files' => 'Fichiers',
        'chat' => 'Chat',
        'bugs' => 'Bugs',
        'team' => 'Équipe',
    ];

    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return array_fill_keys(array_keys(self::FEATURES), true);
    }

    /** @return array<string, bool> */
    public static function forMember(User $user, Project $project): array
    {
        if ($user->is_admin) {
            return self::defaults();
        }

        $member = $project->members()->whereKey($user->id)->first();
        $stored = $member?->pivot?->permissions ?? [];

        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($stored)) {
            $stored = [];
        }

        return array_merge(self::defaults(), $stored);
    }

    public static function can(User $user, Project $project, string $feature): bool
    {
        if (! array_key_exists($feature, self::FEATURES)) {
            return true;
        }

        return self::forMember($user, $project)[$feature] ?? true;
    }

    /** @param  array<string, bool>  $input */
    public static function sanitize(array $input): array
    {
        $result = self::defaults();
        foreach (array_keys(self::FEATURES) as $key) {
            if (array_key_exists($key, $input)) {
                $result[$key] = (bool) $input[$key];
            }
        }

        return $result;
    }
}
