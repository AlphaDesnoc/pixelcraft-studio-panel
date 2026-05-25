<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
{
    public static function canAccess(User $user, Project $project, string $spaceKey): bool
    {
        if ($spaceKey === ProjectSpace::FULL) {
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
}
