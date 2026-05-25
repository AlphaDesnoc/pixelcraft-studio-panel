<?php

namespace App\Support;

use App\Models\Bug;
use App\Models\User;

class BugChatAccess
{
    public static function canAccess(User $user, Bug $bug): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if (! ProjectAccess::canAccess($user, $bug->project)) {
            return false;
        }

        if ((int) $bug->reporter_id === (int) $user->id) {
            return true;
        }

        return $bug->project->ranks()
            ->where('manages_bugs', true)
            ->whereHas('members', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }
}
