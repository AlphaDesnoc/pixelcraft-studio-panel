<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'rank_id', 'name', 'color', 'status_kind', 'position'])]
class TaskList extends Model
{
    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_KINDS = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
    ];

    public static function defaultsFor(int $projectId): array
    {
        return [
            ['project_id' => $projectId, 'name' => 'À faire', 'color' => '#9ca3af', 'status_kind' => self::STATUS_TODO, 'position' => 0],
            ['project_id' => $projectId, 'name' => 'En cours', 'color' => '#3b82f6', 'status_kind' => self::STATUS_IN_PROGRESS, 'position' => 1],
            ['project_id' => $projectId, 'name' => 'Revue', 'color' => '#f59e0b', 'status_kind' => self::STATUS_IN_PROGRESS, 'position' => 2],
            ['project_id' => $projectId, 'name' => 'Terminé', 'color' => '#10b981', 'status_kind' => self::STATUS_DONE, 'position' => 3],
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'list_id')->orderBy('position');
    }
}
