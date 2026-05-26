<?php

namespace App\Support;

use App\Models\Bug;
use App\Models\User;

class BugChatAccess
{
    public static function canAccess(User $user, Bug $bug): bool
    {
        return BugVisibility::canAccess($user, $bug, $bug->project);
    }
}
