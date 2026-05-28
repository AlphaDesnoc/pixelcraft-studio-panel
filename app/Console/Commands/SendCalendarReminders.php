<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\UserNotification;
use App\Support\CalendarRecurrenceExpander;
use App\Support\PanelNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SendCalendarReminders extends Command
{
    protected $signature = 'calendar:send-reminders';

    protected $description = 'Send calendar event reminders (including recurring occurrences)';

    public function handle(): int
    {
        $now = now();
        $windowEnd = $now->copy()->addDay();

        $events = CalendarEvent::query()
            ->whereNotNull('reminder_minutes')
            ->with(['project:id,slug,name', 'exceptions'])
            ->get();

        $sent = 0;

        foreach ($events as $event) {
            $occurrences = CalendarRecurrenceExpander::expandForRange(
                $event,
                $now->copy()->subHour(),
                $windowEnd,
                $event->exceptions,
            );

            foreach ($occurrences as $occurrence) {
                $startAt = Carbon::parse($occurrence['start_at']);
                $remindAt = $startAt->copy()->subMinutes((int) $event->reminder_minutes);

                if ($remindAt->gt($now) || $remindAt->lt($now->copy()->subMinutes(5))) {
                    continue;
                }

                $occurrenceDate = $occurrence['occurrence_date']
                    ?? $startAt->toDateString();

                $alreadySent = DB::table('calendar_event_reminder_logs')
                    ->where('calendar_event_id', $event->id)
                    ->where('occurrence_date', $occurrenceDate)
                    ->where('reminder_minutes', $event->reminder_minutes)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                $url = route('projects.show', $event->project->slug).'?tab=calendar';
                $users = $event->project->members()->get();

                if ($event->project->owner_id) {
                    $owner = $event->project->owner;
                    if ($owner && ! $users->contains('id', $owner->id)) {
                        $users->push($owner);
                    }
                }

                foreach ($users as $user) {
                    PanelNotifier::send(
                        $user,
                        UserNotification::TYPE_CALENDAR_REMINDER,
                        'Rappel calendrier',
                        sprintf(
                            '« %s » dans %d min (%s)',
                            $occurrence['title'],
                            $event->reminder_minutes,
                            $startAt->format('d/m H:i'),
                        ),
                        $url,
                        [
                            'project_id' => $event->project_id,
                            'event_id' => $event->id,
                            'occurrence_date' => $occurrenceDate,
                        ],
                    );
                }

                DB::table('calendar_event_reminder_logs')->insert([
                    'calendar_event_id' => $event->id,
                    'occurrence_date' => $occurrenceDate,
                    'reminder_minutes' => $event->reminder_minutes,
                    'sent_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $sent++;
            }
        }

        $this->info("Sent {$sent} calendar reminder(s).");

        return self::SUCCESS;
    }
}
