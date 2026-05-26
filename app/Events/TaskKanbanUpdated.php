<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskKanbanUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Project $project,
        public string $action,
        public array $payload,
        public ?int $actorId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project-kanban.'.$this->project->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TaskKanbanUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'payload' => $this->payload,
            'actor_id' => $this->actorId,
            'project_id' => $this->project->id,
        ];
    }
}
