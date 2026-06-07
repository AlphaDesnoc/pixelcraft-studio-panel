<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'rank_id',
    'name',
    'with_video',
    'position',
])]
class VoiceChannel extends Model
{
    protected function casts(): array
    {
        return [
            'with_video' => 'boolean',
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

    public function participants(): HasMany
    {
        return $this->hasMany(VoiceParticipant::class);
    }

    public function roomName(): string
    {
        return "proj-{$this->project_id}-vc-{$this->id}";
    }

    /** Clé d'espace pour la vérification d'accès (slug du rang, ou "global"). */
    public function spaceKey(): string
    {
        return $this->rank?->slug ?? 'global';
    }

    public function toPayload(): array
    {
        $this->loadMissing(['rank:id,name,slug,color', 'participants.user:id,name,avatar_path']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'rank_id' => $this->rank_id,
            'rank' => $this->rank ? [
                'id' => $this->rank->id,
                'name' => $this->rank->name,
                'color' => $this->rank->color,
            ] : null,
            'with_video' => (bool) $this->with_video,
            'position' => (int) $this->position,
            'participants' => $this->participants
                ->filter(fn ($p) => $p->user)
                ->map(fn ($p) => [
                    'id' => $p->user->id,
                    'name' => $p->user->name,
                    'avatar_url' => $p->user->avatar_url,
                ])
                ->values(),
        ];
    }
}
