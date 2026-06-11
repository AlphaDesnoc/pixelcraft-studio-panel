<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementPosted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Announcement $announcement) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project-announcements.'.$this->announcement->project_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AnnouncementPosted';
    }

    public function broadcastWith(): array
    {
        return [
            'announcement' => $this->announcement->toPayload(),
        ];
    }
}
