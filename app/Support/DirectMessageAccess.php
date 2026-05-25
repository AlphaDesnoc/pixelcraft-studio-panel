<?php

namespace App\Support;

use App\Models\DirectConversation;
use App\Models\User;

class DirectMessageAccess
{
    public static function canAccess(User $user, DirectConversation $conversation): bool
    {
        return $conversation->involves($user->id);
    }

    public static function canMessage(User $sender, User $recipient): bool
    {
        if ((int) $sender->id === (int) $recipient->id) {
            return false;
        }

        if ($sender->is_admin || $recipient->is_admin) {
            return true;
        }

        $senderProjectIds = $sender->projects()->pluck('projects.id');

        if ($senderProjectIds->isEmpty()) {
            return false;
        }

        return $recipient->projects()
            ->whereIn('projects.id', $senderProjectIds)
            ->exists();
    }

    public static function sharedContacts(User $user)
    {
        if ($user->is_admin) {
            return User::query()
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        $projectIds = $user->projects()->pluck('projects.id');

        if ($projectIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereKeyNot($user->id)
            ->whereHas('projects', fn ($q) => $q->whereIn('projects.id', $projectIds))
            ->select('users.*')
            ->distinct()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public static function ensureAccess(User $user, DirectConversation $conversation): void
    {
        abort_unless(self::canAccess($user, $conversation), 403);
    }

    public static function ensureCanMessage(User $sender, User $recipient): void
    {
        abort_unless(self::canMessage($sender, $recipient), 403);
    }
}
