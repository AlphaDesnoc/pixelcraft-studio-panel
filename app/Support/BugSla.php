<?php

namespace App\Support;

use App\Models\Bug;
use Carbon\Carbon;

class BugSla
{
    /** Hours until SLA breach by priority */
    public const HOURS = [
        Bug::PRIORITY_URGENT => 24,
        Bug::PRIORITY_HIGH => 48,
        Bug::PRIORITY_MEDIUM => 120,
        Bug::PRIORITY_LOW => 168,
    ];

    public static function dueAt(Bug $bug): ?Carbon
    {
        if ($bug->status === Bug::STATUS_CLOSED) {
            return null;
        }

        $hours = self::HOURS[$bug->priority] ?? self::HOURS[Bug::PRIORITY_MEDIUM];

        return $bug->created_at->copy()->addHours($hours);
    }

    public static function isBreached(Bug $bug): bool
    {
        if ($bug->status === Bug::STATUS_CLOSED) {
            return false;
        }

        $due = $bug->sla_due_at ?? self::dueAt($bug);

        return $due && $due->isPast();
    }
}
