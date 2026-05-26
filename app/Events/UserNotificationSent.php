<?php

namespace App\Events;

use App\Models\UserNotification;
use App\Support\PanelNotifier;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public UserNotification $notification) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'UserNotificationSent';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification->toPayload(),
            'unread_count' => PanelNotifier::unreadCount($this->notification->user_id),
        ];
    }
}
