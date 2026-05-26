<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use App\Models\UserPresence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SpaceChatAccess
{
    private const ONLINE_WINDOW_SECONDS = 90;

    public static function canAccess(User $user, Project $project, string $spaceKey): bool
    {
        if ($spaceKey === ProjectSpace::FULL) {
            return false;
        }

        if (Schema::hasColumn('users', 'is_active') && ! $user->is_active) {
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

        $query = User::query()->orderBy('name');

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        if ($spaceKey === ProjectSpace::GLOBAL) {
            $memberIds = $project->members()->pluck('users.id');
            if ($project->owner_id) {
                $memberIds = $memberIds->push($project->owner_id)->unique()->values();
            }

            return $query
                ->where(function ($q) use ($memberIds) {
                    $q->whereIn('id', $memberIds)
                        ->orWhere('role', User::ROLE_ADMIN);
                })
                ->get(['id', 'name', 'email']);
        }

        $rank = $project->ranks()->where('slug', $spaceKey)->first();
        if (! $rank) {
            return collect();
        }

        $rankMemberIds = $rank->members()->pluck('users.id');

        return $query
            ->where(function ($q) use ($rankMemberIds) {
                $q->whereIn('id', $rankMemberIds)
                    ->orWhere('role', User::ROLE_ADMIN);
            })
            ->get(['id', 'name', 'email']);
    }

    /** @return array<int, array{id: int, name: string, pseudo: string, is_online: bool}> */
    public static function membersWithPresence(Project $project, string $spaceKey, ?User $viewer = null): array
    {
        if ($viewer) {
            UserPresence::query()->updateOrCreate(
                ['user_id' => $viewer->id],
                ['last_seen_at' => now()],
            );
        }

        $eligible = self::eligibleUsers($project, $spaceKey);

        if ($eligible->isEmpty()) {
            return [];
        }

        $onlineIds = UserPresence::query()
            ->whereIn('user_id', $eligible->pluck('id'))
            ->where('last_seen_at', '>=', now()->subSeconds(self::ONLINE_WINDOW_SECONDS))
            ->pluck('user_id')
            ->flip();

        return $eligible
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'pseudo' => Str::before($user->email, '@'),
                'is_online' => $onlineIds->has($user->id)
                    || ($viewer && (int) $viewer->id === (int) $user->id),
            ])
            ->values()
            ->all();
    }
}
