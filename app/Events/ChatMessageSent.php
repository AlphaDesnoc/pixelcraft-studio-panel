<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel(
                'project-chat.'.$this->message->project_id.'.'.$this->message->space_key,
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChatMessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->toPayload(),
        ];
    }
}
