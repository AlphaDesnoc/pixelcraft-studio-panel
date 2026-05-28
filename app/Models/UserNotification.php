<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    public const TYPE_TASK_ASSIGNED = 'task_assigned';

    public const TYPE_CHAT_MESSAGE = 'chat_message';

    public const TYPE_CHAT_MENTION = 'chat_mention';

    public const TYPE_DIRECT_MESSAGE = 'direct_message';

    public const TYPE_BUG_ASSIGNED = 'bug_assigned';

    public const TYPE_DUE_TOMORROW = 'due_tomorrow';

    public const TYPE_DUE_TODAY = 'due_today';

    public const TYPE_OVERDUE = 'overdue';

    public const TYPE_CALENDAR_REMINDER = 'calendar_reminder';

    public const TYPE_BUG_SLA_BREACH = 'bug_sla_breach';

    public const TYPE_WEEKLY_DIGEST = 'weekly_digest';

    public const TYPE_DAILY_DIGEST = 'daily_digest';

    public const TYPE_TASK_REMINDER = 'task_reminder';

    public const TYPE_TASK_COMMENT_MENTION = 'task_comment_mention';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'url',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'data' => $this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
