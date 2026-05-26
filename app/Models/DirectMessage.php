<?php

namespace App\Models;

use App\Http\Controllers\DirectMessageReactionController;
use App\Support\ChatBodyFormatter;
use App\Support\MentionParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DirectMessage extends Model
{
    protected $fillable = [
        'direct_conversation_id',
        'user_id',
        'body',
        'mentions',
        'reply_to_id',
    ];

    protected function casts(): array
    {
        return [
            'mentions' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DirectConversation::class, 'direct_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function toPayload(?User $viewer = null): array
    {
        $this->loadMissing('user:id,name', 'attachments', 'replyTo.user:id,name', 'conversation');

        $replyPreview = null;
        if ($this->replyTo) {
            $replyPreview = [
                'id' => $this->replyTo->id,
                'body' => str($this->replyTo->body)->limit(120),
                'user_name' => $this->replyTo->user?->name,
            ];
        }

        $payload = [
            'id' => $this->id,
            'direct_conversation_id' => $this->direct_conversation_id,
            'body' => $this->body,
            'body_html' => ChatBodyFormatter::toHtml($this->body ?? ''),
            'mentions' => $this->mentions ?? [],
            'reply_to_id' => $this->reply_to_id,
            'reply_preview' => $replyPreview,
            'reactions' => DirectMessageReactionController::groupedReactions($this),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'attachments' => $this->attachments->map(fn (Attachment $a) => $a->toPayload())->values(),
        ];

        if ($viewer && (int) $this->user_id === (int) $viewer->id && $this->conversation) {
            $other = $this->conversation->otherParticipant($viewer);
            $otherLastRead = $other ? $this->conversation->lastReadAtFor($other) : null;
            $isRead = $otherLastRead && $this->created_at
                && $otherLastRead->gte($this->created_at);

            $payload['is_read'] = $isRead;
            $payload['read_at'] = $isRead ? $otherLastRead->toIso8601String() : null;
        }

        return $payload;
    }
}
