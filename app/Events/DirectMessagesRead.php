<?php

namespace App\Events;

use App\Models\DirectConversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DirectConversation $conversation,
        public User $reader,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('direct.'.$this->conversation->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DirectMessagesRead';
    }

    public function broadcastWith(): array
    {
        $readAt = $this->conversation->lastReadAtFor($this->reader);

        return [
            'conversation_id' => $this->conversation->id,
            'reader_id' => $this->reader->id,
            'read_at' => $readAt?->toIso8601String(),
        ];
    }
}
