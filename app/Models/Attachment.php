<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'user_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicUrl(): string
    {
        return '/storage/'.ltrim(str_replace('\\', '/', $this->path), '/');
    }

    public function resolveUrl(): string
    {
        $project = $this->resolveProject();
        if ($project) {
            return url(route('projects.attachments.show', [$project->slug, $this->id], false));
        }

        return url($this->publicUrl());
    }

    public function resolveProject(): ?Project
    {
        $this->loadMissing('attachable');

        if ($this->attachable instanceof ChatMessage) {
            return $this->attachable->project()->first();
        }

        if ($this->attachable instanceof Task) {
            return $this->attachable->project()->first();
        }

        if ($this->attachable instanceof Announcement) {
            return $this->attachable->project()->first();
        }

        return null;
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => (int) $this->size,
            'url' => $this->resolveUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
