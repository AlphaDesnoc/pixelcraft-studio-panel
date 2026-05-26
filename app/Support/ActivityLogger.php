<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public static function log(
        Project $project,
        ?User $user,
        string $action,
        string $message,
        ?Model $subject = null,
        array $meta = [],
    ): ActivityLog {
        return ActivityLog::query()->create([
            'project_id' => $project->id,
            'user_id' => $user?->id,
            'action' => $action,
            'message' => $message,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
        ]);
    }
}
