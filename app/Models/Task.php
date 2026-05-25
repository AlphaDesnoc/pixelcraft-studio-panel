<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'list_id',
    'assignee_id',
    'title',
    'description',
    'status',
    'priority',
    'position',
    'progress',
    'due_date',
    'start_date',
    'completed_at',
])]
class Task extends Model
{
    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUSES = [
        self::STATUS_TODO => 'À faire',
        self::STATUS_IN_PROGRESS => 'En cours',
        self::STATUS_DONE => 'Terminée',
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Basse',
        self::PRIORITY_MEDIUM => 'Moyenne',
        self::PRIORITY_HIGH => 'Haute',
        self::PRIORITY_URGENT => 'Urgente',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'start_date' => 'date',
            'completed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'list_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function checklists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('position');
    }
}
