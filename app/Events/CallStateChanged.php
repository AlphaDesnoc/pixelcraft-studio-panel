<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Call $call) {}

    public function broadcastOn(): array
    {
        // Sur le canal d'appel (les deux participants) ET les canaux perso
        // pour mettre à jour l'UI même hors du canal d'appel.
        return [
            new PrivateChannel('call.'.$this->call->id),
            new PrivateChannel('App.Models.User.'.$this->call->caller_id),
            new PrivateChannel('App.Models.User.'.$this->call->callee_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallStateChanged';
    }

    public function broadcastWith(): array
    {
        return ['call' => $this->call->toPayload()];
    }
}
