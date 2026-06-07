<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;

class CallAccess
{
    /**
     * Un utilisateur peut appeler un autre s'ils partagent au moins un projet.
     */
    public static function canCall(User $caller, User $callee): bool
    {
        if ((int) $caller->id === (int) $callee->id) {
            return false;
        }

        return $caller->projects()
            ->whereHas('members', fn ($q) => $q->whereKey($callee->id))
            ->exists();
    }

    /**
     * Les deux participants partagent-ils l'accès au salon vocal d'un projet/espace ?
     */
    public static function canJoinRoom(User $user, Project $project, string $spaceKey): bool
    {
        return SpaceChatAccess::canAccess($user, $project, $spaceKey);
    }
}
