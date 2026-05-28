<?php

namespace App\Console\Commands;

use App\Models\Bug;
use App\Models\DirectConversation;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\PanelNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendWeeklyDigest extends Command
{
    protected $signature = 'panel:weekly-digest';

    protected $description = 'Send weekly activity summary to active users';

    public function handle(): int
    {
        $since = now()->subWeek();
        $users = User::query()->where('is_active', true)->get();

        foreach ($users as $user) {
            $projectIds = $user->projects()->pluck('projects.id');

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
                ->where(function ($query) use ($user) {
                    $query->where('user_one_id', $user->id)
                        ->orWhere('user_two_id', $user->id);
                })
                ->get()
                ->sum(fn (DirectConversation $conversation) => $conversation->unreadCountFor($user));

            if ($tasksClosed === 0 && $openBugs === 0 && $unreadMessages === 0) {
                continue;
            }

            $body = sprintf(
                '%d tâche(s) clôturée(s) · %d bug(s) ouvert(s) · %d message(s) non lu(s)',
                $tasksClosed,
                $openBugs,
                $unreadMessages,
            );

            PanelNotifier::send(
                $user->id,
                UserNotification::TYPE_WEEKLY_DIGEST,
                'Résumé hebdomadaire PixelCraft',
                $body,
                route('dashboard'),
                [
                    'tasks_closed' => $tasksClosed,
                    'open_bugs' => $openBugs,
                    'unread_messages' => $unreadMessages,
                    'week_start' => $since->toDateString(),
                ],
            );
        }

        $this->info('Weekly digest sent at '.Carbon::now()->toDateTimeString());

        return self::SUCCESS;
    }
}
