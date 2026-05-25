<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'reporter_id',
    'assignee_id',
    'assigned_rank_id',
    'title',
    'description',
    'priority',
    'status',
    'screenshots',
])]
class Bug extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN => 'Ouvert',
        self::STATUS_IN_PROGRESS => 'En cours',
        self::STATUS_CLOSED => 'Fermé',
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
            'screenshots' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function assignedRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'assigned_rank_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BugMessage::class)->orderBy('created_at');
    }
}
