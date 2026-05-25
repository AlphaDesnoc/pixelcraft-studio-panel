<?php

namespace App\Events;

use App\Models\BugMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BugMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public BugMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('bug.'.$this->message->bug_id)];
    }

    public function broadcastAs(): string
    {
        return 'BugMessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->toPayload(),
        ];
    }
}
