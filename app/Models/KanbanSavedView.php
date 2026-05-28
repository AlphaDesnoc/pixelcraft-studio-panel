<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanSavedView extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'name',
        'filters',
        'is_shared',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'filters' => $this->filters ?? [],
            'is_shared' => (bool) $this->is_shared,
            'user_id' => $this->user_id,
        ];
    }
}
