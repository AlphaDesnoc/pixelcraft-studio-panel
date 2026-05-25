<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'project_id',
    'responsible_id',
    'name',
    'slug',
    'color',
    'description',
    'manages_bugs',
    'position',
])]
class Rank extends Model
{
    protected function casts(): array
    {
        return [
            'manages_bugs' => 'boolean',
            'position' => 'integer',
        ];
    }

    public static function defaultsFor(int $projectId): array
    {
        return [
            ['name' => 'Config', 'slug' => 'config', 'color' => '#a855f7', 'position' => 0],
            ['name' => 'Modération', 'slug' => 'moderation', 'color' => '#ef4444', 'position' => 1],
            ['name' => 'Animation', 'slug' => 'animation', 'color' => '#f97316', 'position' => 2],
            ['name' => 'Développement', 'slug' => 'developpement', 'color' => '#22d3ee', 'position' => 3, 'manages_bugs' => true],
        ];
    }

    public static function uniqueSlug(int $projectId, string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'rank';
        $candidate = $slug;
        $i = 2;
        while (
            static::query()
                ->where('project_id', $projectId)
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $slug.'-'.$i;
            $i++;
        }
        return $candidate;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function sheets(): HasMany
    {
        return $this->hasMany(Sheet::class);
    }

    public function fileNodes(): HasMany
    {
        return $this->hasMany(FileNode::class);
    }
}
