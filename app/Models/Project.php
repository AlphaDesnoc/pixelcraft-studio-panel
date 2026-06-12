<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name',
    'slug',
    'description',
    'image',
    'color',
    'access_levels',
    'status',
    'start_date',
    'owner_id',
])]
class Project extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Actif',
        self::STATUS_COMPLETED => 'Terminé',
        self::STATUS_ARCHIVED => 'Archivé',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'access_levels' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Project $project) {
            $project->lists()->createMany(
                collect(TaskList::defaultsFor($project->id))
                    ->map(fn ($l) => collect($l)->except('project_id')->all())
                    ->all()
            );

            $project->sheets()->create([
                'name' => 'Feuille 1',
                'position' => 0,
                'rows' => 50,
                'cols' => 26,
                'data' => new \stdClass,
            ]);

            foreach (Rank::defaultsFor($project->id) as $r) {
                $project->ranks()->create($r);
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class)
            ->withPivot('role', 'joined_at', 'permissions', 'access_level')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class)->orderBy('position');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class)->orderBy('start_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)
            ->orderByDesc('pinned')
            ->orderByDesc('pinned_at')
            ->orderByDesc('created_at');
    }

    public function sheets(): HasMany
    {
        return $this->hasMany(Sheet::class)->orderBy('position');
    }

    public function voiceChannels(): HasMany
    {
        return $this->hasMany(VoiceChannel::class)->orderBy('position');
    }

    public function fileNodes(): HasMany
    {
        return $this->hasMany(FileNode::class)
            ->orderByRaw("CASE WHEN type = 'folder' THEN 0 ELSE 1 END")
            ->orderBy('name');
    }

    public function ranks(): HasMany
    {
        return $this->hasMany(Rank::class)->orderBy('position');
    }

    public function bugs(): HasMany
    {
        return $this->hasMany(Bug::class)->orderByDesc('created_at');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class)->orderByDesc('created_at');
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(TaskTemplate::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->image) {
                return null;
            }

            if (str_starts_with($this->image, 'http')) {
                return $this->image;
            }

            return '/storage/'.ltrim($this->image, '/');
        });
    }
}
