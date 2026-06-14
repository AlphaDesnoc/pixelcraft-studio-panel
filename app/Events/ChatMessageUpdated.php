<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('project-chat.'.$this->message->project_id.'.'.$this->message->space_key),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChatMessageUpdated';
    }

    public function broadcastWith(): array
    {
        $payload = $this->message->toPayload();
        // Drapeaux par destinataire : recalculés côté client / au polling.
        unset($payload['mentions_me'], $payload['can_edit']);

        return ['message' => $payload];
    }
}
