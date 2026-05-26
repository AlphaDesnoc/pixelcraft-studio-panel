<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectMessageReaction extends Model
{
    protected $fillable = ['direct_message_id', 'user_id', 'emoji'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(DirectMessage::class, 'direct_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
