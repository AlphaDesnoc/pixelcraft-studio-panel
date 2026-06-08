<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'caller_id',
    'callee_id',
    'status',
    'with_video',
    'started_at',
    'ended_at',
])]
class Call extends Model
{
    public const STATUS_RINGING = 'ringing';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_ENDED = 'ended';

    public const STATUS_MISSED = 'missed';

    protected function casts(): array
    {
        return [
            'with_video' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function isParticipant(int $userId): bool
    {
        return (int) $this->caller_id === $userId || (int) $this->callee_id === $userId;
    }

    /** Nom de la room LiveKit dédiée à cet appel 1:1. */
    public function roomName(): string
    {
        return 'call-'.$this->id;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_RINGING, self::STATUS_ACCEPTED], true);
    }

    public function toPayload(): array
    {
        $this->loadMissing(['caller:id,name,avatar_path', 'callee:id,name,avatar_path']);

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'status' => $this->status,
            'with_video' => (bool) $this->with_video,
            'channel' => 'call.'.$this->id,
            'caller' => $this->caller ? [
                'id' => $this->caller->id,
                'name' => $this->caller->name,
                'avatar_url' => $this->caller->avatar_url,
            ] : null,
            'callee' => $this->callee ? [
                'id' => $this->callee->id,
                'name' => $this->callee->name,
                'avatar_url' => $this->callee->avatar_url,
            ] : null,
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'ended_at' => optional($this->ended_at)?->toIso8601String(),
        ];
    }
}
