<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'rank_id',
    'creator_id',
    'title',
    'description',
    'start_at',
    'end_at',
    'all_day',
    'color',
    'recurrence',
    'recurrence_weekdays',
    'recurrence_until',
    'reminder_minutes',
])]
class CalendarEvent extends Model
{
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'all_day' => 'boolean',
            'recurrence_weekdays' => 'array',
            'recurrence_until' => 'date',
            'reminder_minutes' => 'integer',
        ];
    }

    public function exceptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CalendarEventException::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
