<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

class SpaceChatAccess
{
    private const ONLINE_WINDOW_SECONDS = 90;

    public static function canAccess(User $user, Project $project, string $spaceKey): bool
    {
        if ($spaceKey === ProjectSpace::FULL) {
            return false;
        }

        if (! $user->is_active) {
            return false;
        }

        if (! ProjectAccess::canAccess($user, $project)) {
            return false;
        }

        if ($spaceKey === ProjectSpace::GLOBAL) {
            return true;
        }

        if ($user->is_admin) {
            return true;
        }

        return $project->ranks()
            ->where('slug', $spaceKey)
            ->whereHas('members', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }

    /** @return Collection<int, User> */
    public static function eligibleUsers(Project $project, string $spaceKey): Collection
    {
        if ($spaceKey === ProjectSpace::FULL) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (User $user) => self::canAccess($user, $project, $spaceKey))
            ->values();
    }

    /** @return array<int, array{id: int, name: string, is_online: bool}> */
    public static function membersWithPresence(Project $project, string $spaceKey): array
    {
        $eligible = self::eligibleUsers($project, $spaceKey);

        if ($eligible->isEmpty()) {
            return [];
        }

        $onlineIds = \App\Models\UserPresence::query()
            ->whereIn('user_id', $eligible->pluck('id'))
            ->where('last_seen_at', '>=', now()->subSeconds(self::ONLINE_WINDOW_SECONDS))
            ->pluck('user_id')
            ->flip();

        return $eligible
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'is_online' => $onlineIds->has($user->id),
            ])
            ->values()
            ->all();
    }
}
