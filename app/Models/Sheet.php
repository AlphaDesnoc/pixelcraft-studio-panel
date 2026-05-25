<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'rank_id',
    'name',
    'position',
    'rows',
    'cols',
    'data',
])]
class Sheet extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'rows' => 'integer',
            'cols' => 'integer',
            'position' => 'integer',
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
}
