<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'minecraft_server_id',
    'uuid',
    'name',
    'ip',
    'online',
    'current_server',
    'join_count',
    'first_seen_at',
    'last_seen_at',
])]
class MinecraftPlayer extends Model
{
    protected function casts(): array
    {
        return [
            'online' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(MinecraftServer::class, 'minecraft_server_id');
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'ip' => $this->ip,
            'online' => (bool) $this->online,
            'current_server' => $this->current_server,
            'join_count' => (int) $this->join_count,
            'first_seen_at' => optional($this->first_seen_at)?->toIso8601String(),
            'last_seen_at' => optional($this->last_seen_at)?->toIso8601String(),
        ];
    }
}
