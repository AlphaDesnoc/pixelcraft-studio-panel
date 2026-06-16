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
    'geo_city',
    'geo_postal',
    'geo_region',
    'geo_country',
    'geo_country_code',
    'geo_lat',
    'geo_lon',
    'geo_timezone',
    'geo_isp',
    'geo_org',
    'geo_as',
    'geo_proxy',
    'geo_hosting',
    'geo_mobile',
    'geo_resolved_at',
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
            'geo_lat' => 'float',
            'geo_lon' => 'float',
            'geo_proxy' => 'boolean',
            'geo_hosting' => 'boolean',
            'geo_mobile' => 'boolean',
            'geo_resolved_at' => 'datetime',
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
            'geo' => [
                'city' => $this->geo_city,
                'postal' => $this->geo_postal,
                'region' => $this->geo_region,
                'country' => $this->geo_country,
                'country_code' => $this->geo_country_code,
                'lat' => $this->geo_lat,
                'lon' => $this->geo_lon,
                'timezone' => $this->geo_timezone,
                'isp' => $this->geo_isp,
                'org' => $this->geo_org,
                'as' => $this->geo_as,
                'proxy' => $this->geo_proxy,
                'hosting' => $this->geo_hosting,
                'mobile' => $this->geo_mobile,
            ],
            'online' => (bool) $this->online,
            'current_server' => $this->current_server,
            'join_count' => (int) $this->join_count,
            'first_seen_at' => optional($this->first_seen_at)?->toIso8601String(),
            'last_seen_at' => optional($this->last_seen_at)?->toIso8601String(),
        ];
    }
}
