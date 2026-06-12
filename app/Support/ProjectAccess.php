<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Rank;
use App\Models\User;

class ProjectAccess
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_MEMBER = 'member';

    public const ROLES = [
        self::ROLE_OWNER => 'Propriétaire',
        self::ROLE_MANAGER => 'Gestionnaire',
        self::ROLE_MEMBER => 'Membre',
    ];

    public static function canAccess(User $user, Project $project): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return $project->members()->whereKey($user->id)->exists();
    }

    public static function canManageTeam(User $user, Project $project): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if ((int) $project->owner_id === (int) $user->id) {
            return true;
        }

        $role = self::memberRole($user, $project);

        return in_array($role, [self::ROLE_OWNER, self::ROLE_MANAGER], true);
    }

    /**
     * Gestion (création/renommage/suppression) d'un salon vocal.
     * Les « admins » du projet (admin global, propriétaire, gestionnaire) gèrent
     * tous les salons ; un responsable de rang ne gère que les salons de SON
     * rang. Les salons globaux (rang nul) restent réservés aux « admins ».
     */
    public static function canManageVoiceChannel(User $user, Project $project, ?Rank $rank): bool
    {
        if (self::canManageTeam($user, $project)) {
            return true;
        }

        return $rank !== null && $rank->responsibles()->whereKey($user->id)->exists();
    }

    public static function memberRole(User $user, Project $project): ?string
    {
        $member = $project->members()->whereKey($user->id)->first();

        return $member?->pivot?->role;
    }

    /**
     * Clairance (niveau d'accréditation) de l'utilisateur dans le projet.
     * Les gestionnaires d'équipe (propriétaire, gestionnaire, admin global)
     * disposent de la clairance maximale afin d'administrer les verrous.
     */
    public static function clearanceLevel(User $user, Project $project): int
    {
        if (self::canManageTeam($user, $project)) {
            return AccessLevels::max($project);
        }

        $member = $project->members()->whereKey($user->id)->first();

        return (int) ($member?->pivot?->access_level ?? 0);
    }

    public static function ensureAccess(User $user, Project $project): void
    {
        abort_unless(self::canAccess($user, $project), 403);
    }

    public static function ensureCanManageTeam(User $user, Project $project): void
    {
        self::ensureAccess($user, $project);
        abort_unless(self::canManageTeam($user, $project), 403);
    }

    public static function isProjectOwner(User $user, Project $project): bool
    {
        if ((int) $project->owner_id === (int) $user->id) {
            return true;
        }

        return self::memberRole($user, $project) === self::ROLE_OWNER;
    }
}
