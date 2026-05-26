<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTag;
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
        $result = [];
        foreach (array_keys(self::FEATURES) as $key) {
            $result[$key] = true;
            $result[self::writeKey($key)] = true;
        }

        return $result;
    }

    public static function writeKey(string $feature): string
    {
        return $feature.'_write';
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

        $merged = array_merge(self::defaults(), $stored);

        foreach (array_keys(self::FEATURES) as $feature) {
            if (($merged[$feature] ?? true) === false) {
                $merged[self::writeKey($feature)] = false;
            }
        }

        return $merged;
    }

    public static function can(User $user, Project $project, string $feature): bool
    {
        return self::canRead($user, $project, $feature);
    }

    public static function canRead(User $user, Project $project, string $feature): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if (! array_key_exists($feature, self::FEATURES)) {
            return true;
        }

        return (bool) (self::forMember($user, $project)[$feature] ?? true);
    }

    public static function canWrite(User $user, Project $project, string $feature): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if (! self::canRead($user, $project, $feature)) {
            return false;
        }

        if (! array_key_exists($feature, self::FEATURES)) {
            return true;
        }

        $writeKey = self::writeKey($feature);

        return (bool) (self::forMember($user, $project)[$writeKey] ?? true);
    }

    /** @param  array<string, bool>  $input */
    public static function sanitize(array $input): array
    {
        $result = self::defaults();

        foreach (array_keys(self::FEATURES) as $key) {
            if (array_key_exists($key, $input)) {
                $result[$key] = (bool) $input[$key];
            }
            $writeKey = self::writeKey($key);
            if (array_key_exists($writeKey, $input)) {
                $result[$writeKey] = (bool) $input[$writeKey];
            }
        }

        foreach (array_keys(self::FEATURES) as $feature) {
            if ($result[$feature] === false) {
                $result[self::writeKey($feature)] = false;
            }
        }

        return $result;
    }
}
