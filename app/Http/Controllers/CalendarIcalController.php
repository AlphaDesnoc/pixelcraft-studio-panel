<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Support\CalendarRecurrenceExpander;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class CalendarIcalController extends Controller
{
    public function __invoke(Request $request, Project $project): Response
    {
        ProjectAccess::ensureAccess($request->user(), $project);

        $rangeStart = now()->subMonths(1)->startOfDay();
        $rangeEnd = now()->addMonths(12)->endOfDay();

        $events = CalendarEvent::query()
            ->where('project_id', $project->id)
            ->with('exceptions')
            ->get();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//PixelCraft Studio Panel//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($project->name),
        ];

        foreach ($events as $event) {
            $occurrences = CalendarRecurrenceExpander::expandForRange(
                $event,
                $rangeStart,
                $rangeEnd,
                $event->exceptions,
            );

            foreach ($occurrences as $occurrence) {
                $start = Carbon::parse($occurrence['start_at']);
                $end = Carbon::parse($occurrence['end_at']);
                $uid = sprintf(
                    'event-%d-%s@pixelcraft-panel',
                    $event->id,
                    $occurrence['occurrence_date'] ?? $start->toDateString(),
                );

                $lines[] = 'BEGIN:VEVENT';
                $lines[] = 'UID:'.$uid;
                $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
                $lines[] = 'DTSTART:'.self::formatUtc($start);
                $lines[] = 'DTEND:'.self::formatUtc($end);
                $lines[] = 'SUMMARY:'.self::escape($occurrence['title'] ?? $event->title);

                if (! empty($occurrence['description'] ?? $event->description)) {
                    $lines[] = 'DESCRIPTION:'.self::escape($occurrence['description'] ?? $event->description);
                }

                $lines[] = 'END:VEVENT';
            }
        }

        $lines[] = 'END:VCALENDAR';

        $body = implode("\r\n", $lines)."\r\n";

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$project->slug.'.ics"',
        ]);
    }

    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', ''],
            $value,
        );
    }

    private static function formatUtc(Carbon $date): string
    {
        return $date->copy()->utc()->format('Ymd\THis\Z');
    }
}
