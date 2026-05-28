<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventException extends Model
{
    public const TYPE_CANCELLED = 'cancelled';

    public const TYPE_MODIFIED = 'modified';

    protected $fillable = [
        'calendar_event_id',
        'occurrence_date',
        'type',
        'title',
        'description',
        'start_at',
        'end_at',
        'all_day',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'occurrence_date' => 'date',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }
}
