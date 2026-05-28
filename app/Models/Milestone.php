<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Milestone extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'description',
        'start_date',
        'due_date',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'position' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }

    public function toPayload(): array
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();
        $done = $tasks->where('status', Task::STATUS_DONE)->count();
        $total = $tasks->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'position' => $this->position,
            'task_ids' => $tasks->pluck('id')->values(),
            'burndown' => [
                'done' => $done,
                'total' => $total,
                'open' => max(0, $total - $done),
            ],
        ];
    }
}
