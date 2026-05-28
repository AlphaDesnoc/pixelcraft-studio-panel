<?php

namespace App\Console\Commands;

use App\Models\Bug;
use App\Models\UserNotification;
use App\Support\BugSla;
use App\Support\PanelNotifier;
use App\Support\ProjectAutomationRunner;
use Illuminate\Console\Command;

class SendBugSlaAlerts extends Command
{
    protected $signature = 'bugs:notify-sla';

    protected $description = 'Notify users when open bugs breach their SLA deadline';

    public function handle(): int
    {
        $sent = 0;

        Bug::query()
            ->where('status', '!=', Bug::STATUS_CLOSED)
            ->with(['project:id,slug,name', 'assignedRank.members:id', 'reporter:id'])
            ->chunkById(100, function ($bugs) use (&$sent) {
                foreach ($bugs as $bug) {
                    if (! BugSla::isBreached($bug)) {
                        continue;
                    }

                    $url = route('projects.show', $bug->project->slug).'?tab=bugs';
                    $recipients = collect();

                    if ($bug->reporter) {
                        $recipients->push($bug->reporter);
                    }

                    foreach ($bug->assignedRank?->members ?? [] as $member) {
                        $recipients->push($member);
                    }

                    foreach ($bug->project->members()->whereIn('role', ['owner', 'manager'])->get() as $member) {
                        $recipients->push($member);
                    }

                    foreach ($recipients->unique('id') as $user) {
                        $alreadyNotified = UserNotification::query()
                            ->where('user_id', $user->id)
                            ->where('type', UserNotification::TYPE_BUG_SLA_BREACH)
                            ->where('data->bug_id', $bug->id)
                            ->exists();

                        if ($alreadyNotified) {
                            continue;
                        }

                        ProjectAutomationRunner::onBugSlaBreach($bug);

                        PanelNotifier::send(
                            $user,
                            UserNotification::TYPE_BUG_SLA_BREACH,
                            'SLA dépassé',
                            sprintf('Le bug « %s » a dépassé son délai SLA.', $bug->title),
                            $url,
                            [
                                'project_id' => $bug->project_id,
                                'bug_id' => $bug->id,
                            ],
                        );

                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} SLA breach notification(s).");

        return self::SUCCESS;
    }
}
