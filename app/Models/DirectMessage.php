<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectMessage extends Model
{
    protected $fillable = [
        'direct_conversation_id',
        'user_id',
        'body',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DirectConversation::class, 'direct_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toPayload(): array
    {
        $this->loadMissing('user:id,name');

        return [
            'id' => $this->id,
            'direct_conversation_id' => $this->direct_conversation_id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
        ];
    }
}
