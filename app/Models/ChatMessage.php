<?php

namespace App\Models;

use App\Http\Controllers\ChatReactionController;
use App\Support\ChatBodyFormatter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id',
    'user_id',
    'rank_id',
    'space_key',
    'body',
    'mentions',
    'reply_to_id',
    'edited_at',
    'pinned_at',
    'pinned_by',
])]
class ChatMessage extends Model
{
    use SoftDeletes;

    public const EDIT_WINDOW_MINUTES = 15;

    protected function casts(): array
    {
        return [
            'mentions' => 'array',
            'edited_at' => 'datetime',
            'pinned_at' => 'datetime',
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

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChatMessageReaction::class);
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
        $this->loadMissing('user:id,name,avatar_path', 'attachments', 'replyTo.user:id,name');

        $replyPreview = null;
        if ($this->replyTo) {
            $replyPreview = [
                'id' => $this->replyTo->id,
                'body' => str($this->replyTo->body)->limit(120),
                'user_name' => $this->replyTo->user?->name,
            ];
        }

        return [
            'id' => $this->id,
            'body' => $this->body,
            'body_html' => ChatBodyFormatter::toHtml($this->body ?? '', $this->mentions ?? []),
            'space_key' => $this->space_key,
            'mentions' => $this->mentions ?? [],
            'reply_to_id' => $this->reply_to_id,
            'reply_preview' => $replyPreview,
            'reactions' => ChatReactionController::groupedReactions($this),
            'pinned_at' => $this->pinned_at?->toIso8601String(),
            'is_pinned' => (bool) $this->pinned_at,
            'edited_at' => $this->edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ] : null,
            'attachments' => $this->attachments->map(fn (Attachment $a) => $a->toPayload())->values(),
            'can_edit' => auth()->user()?->id === $this->user_id
                && $this->created_at?->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES)),
        ];
    }
}
