<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $announcementId,
        public int $projectId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project-announcements.'.$this->projectId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AnnouncementDeleted';
    }

    public function broadcastWith(): array
    {
        return [
            'announcement_id' => $this->announcementId,
        ];
    }
}
