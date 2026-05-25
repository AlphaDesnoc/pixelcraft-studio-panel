<?php

namespace App\Events;

use App\Models\DirectMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DirectMessage $message) {}

    public function broadcastOn(): array
    {
        $conversation = $this->message->conversation;

        return [
            new PresenceChannel('direct.'.$this->message->direct_conversation_id),
            new PrivateChannel('App.Models.User.'.$conversation->user_one_id),
            new PrivateChannel('App.Models.User.'.$conversation->user_two_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DirectMessageSent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing([
            'user:id,name',
            'conversation.userOne:id,name,email',
            'conversation.userTwo:id,name,email',
        ]);

        $conversation = $this->message->conversation;

        return [
            'message' => $this->message->toPayload(),
            'inbox' => [
                'id' => $conversation->id,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'last_message' => [
                    'id' => $this->message->id,
                    'body' => $this->message->body,
                    'created_at' => $this->message->created_at?->toIso8601String(),
                    'user_id' => $this->message->user_id,
                ],
                'participants' => [
                    [
                        'id' => $conversation->userOne->id,
                        'name' => $conversation->userOne->name,
                        'email' => $conversation->userOne->email,
                    ],
                    [
                        'id' => $conversation->userTwo->id,
                        'name' => $conversation->userTwo->name,
                        'email' => $conversation->userTwo->email,
                    ],
                ],
            ],
        ];
    }
}
