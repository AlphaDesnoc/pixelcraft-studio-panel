<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAutomationRule extends Model
{
    public const TRIGGER_BUG_CRITICAL = 'bug_critical';

    public const TRIGGER_TASK_DONE = 'task_done';

    public const TRIGGER_BUG_SLA_BREACH = 'bug_sla_breach';

    public const ACTION_ASSIGN_RANK = 'assign_rank';

    public const ACTION_NOTIFY_MANAGER = 'notify_manager';

    public const ACTION_LOG_ACTIVITY = 'log_activity';

    protected $fillable = [
        'project_id',
        'name',
        'trigger',
        'trigger_config',
        'action',
        'action_config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'action_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'trigger' => $this->trigger,
            'trigger_config' => $this->trigger_config ?? [],
            'action' => $this->action,
            'action_config' => $this->action_config ?? [],
            'is_active' => (bool) $this->is_active,
        ];
    }
}
