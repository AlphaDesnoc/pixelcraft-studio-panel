<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $kind  offer|answer|ice|hangup
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $callId,
        public int $fromId,
        public string $kind,
        public array $data,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('call.'.$this->callId)];
    }

    public function broadcastAs(): string
    {
        return 'CallSignal';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->callId,
            'from_id' => $this->fromId,
            'kind' => $this->kind,
            'data' => $this->data,
        ];
    }
}
