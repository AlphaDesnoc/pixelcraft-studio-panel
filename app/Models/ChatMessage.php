<?php

namespace App\Models;

use App\Support\MentionParser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['project_id', 'user_id', 'rank_id', 'space_key', 'body', 'mentions', 'edited_at'])]
class ChatMessage extends Model
{
    use SoftDeletes;

    public const EDIT_WINDOW_MINUTES = 15;

    protected function casts(): array
    {
        return [
            'mentions' => 'array',
            'edited_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function canEditBy(User $user): bool
    {
        if ($this->user_id !== $user->id) {
            return false;
        }

        return $this->created_at->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES));
    }

    public function toPayload(): array
    {
        $this->loadMissing('user:id,name', 'attachments');

        return [
            'id' => $this->id,
            'body' => $this->body,
            'body_html' => MentionParser::highlightHtml($this->body ?? ''),
            'space_key' => $this->space_key,
            'mentions' => $this->mentions ?? [],
            'edited_at' => $this->edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'attachments' => $this->attachments->map(fn (Attachment $a) => $a->toPayload())->values(),
            'can_edit' => auth()->user()?->id === $this->user_id
                && $this->created_at?->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES)),
        ];
    }
}
