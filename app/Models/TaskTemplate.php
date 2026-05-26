<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplate extends Model
{
    protected $fillable = [
        'project_id',
        'rank_id',
        'name',
        'title',
        'description',
        'priority',
        'checklist',
    ];

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
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

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'rank_id' => $this->rank_id,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'checklist' => $this->checklist ?? [],
        ];
    }
}
