<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'project_id',
    'name',
    'link_code',
    'token',
    'linked_at',
    'last_synced_at',
    'last_ip',
])]
class MinecraftServer extends Model
{
    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public static function generateToken(): string
    {
        return 'pcs_'.Str::random(56);
    }

    /**
     * Identifiant court tapé par l'admin dans /pixellink <id>. On évite les
     * caractères ambigus (0/O, 1/I/L) et on garantit l'unicité en base.
     */
    public static function generateLinkCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $raw = '';
            for ($i = 0; $i < 8; $i++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::query()->where('link_code', $raw)->exists());

        return $raw;
    }

    /** Format lisible pour l'affichage (ex. ABCD-EFGH). */
    public function formattedLinkCode(): ?string
    {
        if (! $this->link_code) {
            return null;
        }

        return implode('-', str_split($this->link_code, 4));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(MinecraftPlayer::class);
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'link_code' => $this->formattedLinkCode(),
            'linked_at' => optional($this->linked_at)?->toIso8601String(),
            'last_synced_at' => optional($this->last_synced_at)?->toIso8601String(),
            'last_ip' => $this->last_ip,
        ];
    }
}
