<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
    'archived_at',
    'recurrence_rule',
    'recurrence_source_id',
    'next_recurrence_at',
    'estimated_minutes',
    'logged_minutes',
    'auto_archive_at',
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
            'archived_at' => 'datetime',
            'next_recurrence_at' => 'datetime',
            'auto_archive_at' => 'datetime',
            'estimated_minutes' => 'integer',
            'logged_minutes' => 'integer',
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

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TaskTag::class, 'task_tag', 'task_id', 'task_tag_id')->orderBy('task_tags.name');
    }

    public function linkedBug(): HasOne
    {
        return $this->hasOne(Bug::class, 'task_id');
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id');
    }

    public function isBlocked(): bool
    {
        if (! $this->relationLoaded('dependencies')) {
            $this->load('dependencies:id,status');
        }

        return $this->dependencies->contains(
            fn (Task $dep) => $dep->status !== self::STATUS_DONE,
        );
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && $this->status !== self::STATUS_DONE;
    }
}
