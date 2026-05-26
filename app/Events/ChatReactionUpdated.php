<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatReactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $messageId,
        public int $projectId,
        public string $spaceKey,
        public array $reactions,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("project-chat.{$this->projectId}.{$this->spaceKey}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChatReactionUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'reactions' => $this->reactions,
        ];
    }
}
