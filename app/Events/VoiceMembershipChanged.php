<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceMembershipChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $action  join|leave
     * @param  array<string, mixed>  $user
     */
    public function __construct(
        public int $projectId,
        public int $channelId,
        public string $action,
        public array $user,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('voice-lobby.'.$this->projectId)];
    }

    public function broadcastAs(): string
    {
        return 'VoiceMembershipChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->channelId,
            'action' => $this->action,
            'user' => $this->user,
        ];
    }
}
