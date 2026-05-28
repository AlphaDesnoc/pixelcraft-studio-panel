<?php

namespace App\Support;

use App\Models\CalendarEvent;
use App\Models\CalendarEventException;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarRecurrenceExpander
{
    private const MAX_OCCURRENCES = 500;

    /** @return array<int, array<string, mixed>> */
    public static function expandForRange(
        CalendarEvent $event,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?Collection $exceptions = null,
    ): array {
        if (! $event->recurrence) {
            return [self::basePayload($event)];
        }

        $exceptions ??= $event->relationLoaded('exceptions')
            ? $event->exceptions
            : collect();

        $exceptionsByDate = $exceptions->keyBy(
            fn (CalendarEventException $ex) => $ex->occurrence_date->toDateString(),
        );

        $seriesStart = $event->start_at->copy();
        $durationSeconds = $event->start_at->diffInSeconds($event->end_at);
        $until = $event->recurrence_until
            ? $event->recurrence_until->copy()->startOfDay()
            : $seriesStart->copy()->addYears(2)->startOfDay();

        $occurrences = [];
        $cursor = $rangeStart->copy()->startOfDay();
        $end = $rangeEnd->copy()->startOfDay();
        $guard = 0;

        while ($cursor <= $end && $cursor <= $until && $guard < self::MAX_OCCURRENCES) {
            $guard++;

            if (self::shouldOccurOnDay($cursor, $event, $seriesStart)) {
                $dateKey = $cursor->toDateString();
                $exception = $exceptionsByDate->get($dateKey);

                if ($exception?->type === CalendarEventException::TYPE_CANCELLED) {
                    $cursor->addDay();

                    continue;
                }

                $occurrenceStart = self::occurrenceDateTime($seriesStart, $cursor);
                $occurrenceEnd = $occurrenceStart->copy()->addSeconds($durationSeconds);

                if ($exception?->type === CalendarEventException::TYPE_MODIFIED) {
                    $occurrenceStart = $exception->start_at ?? $occurrenceStart;
                    $occurrenceEnd = $exception->end_at ?? $occurrenceEnd;
                }

                if ($occurrenceEnd >= $rangeStart && $occurrenceStart <= $rangeEnd->copy()->endOfDay()) {
                    $payload = self::basePayload($event, $exception);
                    $payload['start_at'] = $occurrenceStart->toIso8601String();
                    $payload['end_at'] = $occurrenceEnd->toIso8601String();
                    $payload['series_id'] = $event->id;
                    $payload['occurrence_date'] = $dateKey;
                    $payload['id'] = $event->id.'-'.$dateKey;
                    $occurrences[] = $payload;
                }
            }

            $cursor->addDay();
        }

        return $occurrences;
    }

    public static function shouldOccurOnDay(Carbon $day, CalendarEvent $event, Carbon $seriesStart): bool
    {
        $dayStart = $day->copy()->startOfDay();
        $seriesStartDay = $seriesStart->copy()->startOfDay();

        if ($dayStart->lt($seriesStartDay)) {
            return false;
        }

        return match ($event->recurrence) {
            'daily' => true,
            'weekly' => in_array(
                $dayStart->dayOfWeek,
                $event->recurrence_weekdays ?: [$seriesStart->dayOfWeek],
                true,
            ),
            'monthly' => $dayStart->day === $seriesStartDay->day,
            default => false,
        };
    }

    public static function occurrenceDateTime(Carbon $seriesStart, Carbon $day): Carbon
    {
        return $day->copy()->startOfDay()
            ->setTimeFrom($seriesStart);
    }

    /** @return array<string, mixed> */
    public static function basePayload(CalendarEvent $event, ?CalendarEventException $exception = null): array
    {
        return [
            'id' => $event->id,
            'title' => $exception?->title ?? $event->title,
            'description' => $exception?->description ?? $event->description,
            'start_at' => optional($exception?->start_at ?? $event->start_at)?->toIso8601String(),
            'end_at' => optional($exception?->end_at ?? $event->end_at)?->toIso8601String(),
            'all_day' => (bool) ($exception?->all_day ?? $event->all_day),
            'color' => $exception?->color ?? $event->color,
            'creator_id' => $event->creator_id,
            'rank_id' => $event->rank_id,
            'recurrence' => $event->recurrence,
            'recurrence_weekdays' => $event->recurrence_weekdays ?? [],
            'recurrence_until' => optional($event->recurrence_until)?->toDateString(),
            'reminder_minutes' => $event->reminder_minutes,
            'exceptions' => [],
        ];
    }
}
