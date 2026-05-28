<?php

namespace App\Support;

use App\Models\Bug;
use App\Models\Project;
use App\Models\ProjectAutomationRule;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;

class ProjectAutomationRunner
{
    public static function onTaskDone(Task $task, User $actor): void
    {
        self::run($task->project, ProjectAutomationRule::TRIGGER_TASK_DONE, [
            'task' => $task,
            'actor' => $actor,
        ]);
    }

    public static function onBugCritical(Bug $bug, User $actor): void
    {
        if ($bug->priority !== Bug::PRIORITY_URGENT) {
            return;
        }

        self::run($bug->project, ProjectAutomationRule::TRIGGER_BUG_CRITICAL, [
            'bug' => $bug,
            'actor' => $actor,
        ]);
    }

    public static function onBugSlaBreach(Bug $bug): void
    {
        self::run($bug->project, ProjectAutomationRule::TRIGGER_BUG_SLA_BREACH, [
            'bug' => $bug,
        ]);
    }

    /** @param  array<string, mixed>  $context */
    private static function run(Project $project, string $trigger, array $context): void
    {
        $rules = ProjectAutomationRule::query()
            ->where('project_id', $project->id)
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            try {
                self::execute($rule, $project, $context);
            } catch (\Throwable $e) {
                Log::warning('Automation rule failed', [
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** @param  array<string, mixed>  $context */
    private static function execute(ProjectAutomationRule $rule, Project $project, array $context): void
    {
        match ($rule->action) {
            ProjectAutomationRule::ACTION_ASSIGN_RANK => self::assignRank($rule, $context),
            ProjectAutomationRule::ACTION_NOTIFY_MANAGER => self::notifyManager($rule, $project, $context),
            ProjectAutomationRule::ACTION_LOG_ACTIVITY => self::logActivity($rule, $project, $context),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $context */
    private static function assignRank(ProjectAutomationRule $rule, array $context): void
    {
        $rankId = (int) ($rule->action_config['rank_id'] ?? 0);
        if ($rankId <= 0) {
            return;
        }

        $bug = $context['bug'] ?? null;
        if ($bug instanceof Bug) {
            $bug->update(['assigned_rank_id' => $rankId]);
        }
    }

    /** @param  array<string, mixed>  $context */
    private static function notifyManager(ProjectAutomationRule $rule, Project $project, array $context): void
    {
        $managers = $project->members()
            ->whereIn('project_user.role', ['owner', 'manager'])
            ->pluck('users.id');

        $title = $rule->name;
        $body = match (true) {
            isset($context['bug']) => 'Automatisation SLA / bug sur '.$project->name,
            isset($context['task']) => 'Tâche terminée : '.($context['task']->title ?? ''),
            default => 'Règle d\'automatisation déclenchée',
        };

        foreach ($managers as $userId) {
            PanelNotifier::send(
                (int) $userId,
                UserNotification::TYPE_BUG_SLA_BREACH,
                $title,
                $body,
                route('projects.show', $project->slug),
                ['project_id' => $project->id],
            );
        }
    }

    /** @param  array<string, mixed>  $context */
    private static function logActivity(ProjectAutomationRule $rule, Project $project, array $context): void
    {
        $actor = $context['actor'] ?? null;
        if (! $actor instanceof User) {
            return;
        }

        $subject = $context['task'] ?? $context['bug'] ?? null;
        ActivityLogger::log(
            $project,
            $actor,
            'automation',
            sprintf('Règle « %s » exécutée', $rule->name),
            $subject,
            ['rule_id' => $rule->id],
        );
    }
}
