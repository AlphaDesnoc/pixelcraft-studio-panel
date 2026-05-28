<?php

namespace App\Console\Commands;

use App\Models\Bug;
use App\Models\DirectConversation;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\PanelNotifier;
use Illuminate\Console\Command;

class SendDailyDigest extends Command
{
    protected $signature = 'panel:daily-digest';

    protected $description = 'Send daily activity summary to active users';

    public function handle(): int
    {
        $since = now()->subDay();
        $users = User::query()->where('is_active', true)->get();

        foreach ($users as $user) {
            $prefs = is_array($user->notification_preferences) ? $user->notification_preferences : [];
            if (($prefs['digest_frequency'] ?? 'weekly') === 'none') {
                continue;
            }
            if (($prefs['digest_frequency'] ?? '') !== 'daily' && ($prefs['daily_digest'] ?? false) !== true) {
                continue;
            }

            $projectQuery = $user->projects();
            if (($prefs['digest_scope'] ?? 'all') === 'my_projects') {
                $projectQuery = $projectQuery->wherePivotIn('role', ['owner', 'manager']);
            }
            $projectIds = $projectQuery->pluck('projects.id');
            if ($projectIds->isEmpty()) {
                continue;
            }

            $tasksClosed = Task::query()
                ->whereIn('project_id', $projectIds)
                ->where('status', Task::STATUS_DONE)
                ->where('updated_at', '>=', $since)
                ->count();

            $openBugs = Bug::query()
                ->whereIn('project_id', $projectIds)
                ->where('status', '!=', Bug::STATUS_CLOSED)
                ->count();

            $unreadMessages = DirectConversation::query()
                ->where(fn ($q) => $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id))
                ->get()
                ->sum(fn (DirectConversation $c) => $c->unreadCountFor($user));

            if ($tasksClosed === 0 && $openBugs === 0 && $unreadMessages === 0) {
                continue;
            }

            PanelNotifier::send(
                $user->id,
                UserNotification::TYPE_DAILY_DIGEST,
                'Résumé quotidien PixelCraft',
                sprintf('%d tâche(s) clôturée(s) · %d bug(s) ouvert(s) · %d message(s) non lu(s)', $tasksClosed, $openBugs, $unreadMessages),
                route('dashboard'),
                compact('tasksClosed', 'openBugs', 'unreadMessages'),
            );
        }

        return self::SUCCESS;
    }
}
