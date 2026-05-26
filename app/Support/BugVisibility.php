<?php

namespace App\Support;

use App\Models\Bug;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class BugVisibility
{
    public static function queryForSpace(
        Builder|Relation $query,
        User $user,
        Project $project,
        ProjectSpace $space,
    ): Builder|Relation {
        if ($user->is_admin && $space->isFull) {
            return $query;
        }

        if ($space->isGlobal) {
            return $query->where(function (Builder $q) use ($user) {
                $q->whereNull('assigned_rank_id')
                    ->orWhere('reporter_id', $user->id);
            });
        }

        if ($space->rankId !== null) {
            $rank = $project->ranks->firstWhere('id', $space->rankId);
            if ($rank && $rank->manages_bugs) {
                return $query->where('assigned_rank_id', $space->rankId);
            }
        }

        return $query->whereRaw('0 = 1');
    }

    public static function canAccess(User $user, Bug $bug, Project $project): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if (! ProjectAccess::canAccess($user, $project)) {
            return false;
        }

        if ((int) $bug->reporter_id === (int) $user->id) {
            return true;
        }

        if (! $bug->assigned_rank_id) {
            return true;
        }

        return self::userManagesRank($user, $project, (int) $bug->assigned_rank_id);
    }

    public static function canManage(User $user, Bug $bug, Project $project): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if (! $bug->assigned_rank_id) {
            return self::userManagesAnyBugRank($user, $project);
        }

        return self::userManagesRank($user, $project, (int) $bug->assigned_rank_id);
    }

    public static function canEditReport(User $user, Bug $bug): bool
    {
        return ! $bug->assigned_rank_id
            && (int) $bug->reporter_id === (int) $user->id;
    }

    public static function userManagesAnyBugRank(User $user, Project $project): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return $project->ranks()
            ->where('manages_bugs', true)
            ->whereHas('members', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }

    public static function userManagesRank(User $user, Project $project, int $rankId): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return $project->ranks()
            ->whereKey($rankId)
            ->where('manages_bugs', true)
            ->whereHas('members', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }

    /** @param  \Illuminate\Support\Collection<int, \App\Models\Bug>  $bugs */
    public static function filterAccessible(User $user, $bugs, Project $project): \Illuminate\Support\Collection
    {
        return $bugs->filter(fn (Bug $bug) => self::canAccess($user, $bug, $project))->values();
    }
}
