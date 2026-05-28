<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\CalendarEvent;
use App\Models\CalendarEventException;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CalendarEventController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'calendar');

        $validated = $this->validateData($request);

        $event = $project->events()->create([
            'creator_id' => $request->user()->id,
            ...$this->eventAttributes($validated),
        ]);

        return $this->apiOrBack($request, ['event' => $this->eventPayload($event)]);
    }

    public function update(Request $request, Project $project, CalendarEvent $event): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'calendar');
        abort_unless($event->project_id === $project->id, 404);

        $validated = $this->validateData($request, includeScope: true);

        if (($validated['edit_scope'] ?? 'series') === 'occurrence' && $event->recurrence) {
            $occurrenceDate = Carbon::parse($validated['occurrence_date'])->toDateString();

            $event->exceptions()->updateOrCreate(
                ['occurrence_date' => $occurrenceDate],
                [
                    'type' => CalendarEventException::TYPE_MODIFIED,
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'start_at' => $validated['start_at'],
                    'end_at' => $validated['end_at'],
                    'all_day' => (bool) ($validated['all_day'] ?? false),
                    'color' => $validated['color'] ?? $event->color,
                ],
            );

            return $this->apiOrBack($request, ['event' => $this->eventPayload($event->fresh('exceptions'))]);
        }

        $event->update($this->eventAttributes($validated));

        return $this->apiOrBack($request, ['event' => $this->eventPayload($event->fresh('exceptions'))]);
    }

    public function destroy(Request $request, Project $project, CalendarEvent $event): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'calendar');
        abort_unless($event->project_id === $project->id, 404);

        $scope = $request->input('delete_scope', 'series');
        $occurrenceDate = $request->input('occurrence_date');

        if ($scope === 'occurrence' && $event->recurrence && $occurrenceDate) {
            $event->exceptions()->updateOrCreate(
                ['occurrence_date' => Carbon::parse($occurrenceDate)->toDateString()],
                ['type' => CalendarEventException::TYPE_CANCELLED],
            );

            return $this->apiOrBack($request, [
                'event_id' => $event->id,
                'occurrence_date' => Carbon::parse($occurrenceDate)->toDateString(),
            ]);
        }

        $eventId = $event->id;
        $event->delete();

        return $this->apiOrBack($request, ['event_id' => $eventId]);
    }

    /** @return array<string, mixed> */
    private function eventPayload(CalendarEvent $event): array
    {
        $event->loadMissing('exceptions');

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'start_at' => optional($event->start_at)?->toIso8601String(),
            'end_at' => optional($event->end_at)?->toIso8601String(),
            'all_day' => (bool) $event->all_day,
            'color' => $event->color,
            'creator_id' => $event->creator_id,
            'rank_id' => $event->rank_id,
            'recurrence' => $event->recurrence,
            'recurrence_weekdays' => $event->recurrence_weekdays ?? [],
            'recurrence_until' => optional($event->recurrence_until)?->toDateString(),
            'reminder_minutes' => $event->reminder_minutes,
            'exceptions' => $event->exceptions->map(fn (CalendarEventException $ex) => [
                'occurrence_date' => $ex->occurrence_date->toDateString(),
                'type' => $ex->type,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function eventAttributes(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'all_day' => (bool) ($validated['all_day'] ?? false),
            'color' => $validated['color'] ?? '#7c5cff',
            'rank_id' => $validated['rank_id'] ?? null,
            'recurrence' => $validated['recurrence'] ?? null,
            'recurrence_weekdays' => $validated['recurrence_weekdays'] ?? null,
            'recurrence_until' => $validated['recurrence_until'] ?? null,
            'reminder_minutes' => $validated['reminder_minutes'] ?? null,
        ];
    }

    private function validateData(Request $request, bool $includeScope = false): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'all_day' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'recurrence' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_weekdays' => ['nullable', 'array', 'required_if:recurrence,weekly', 'min:1'],
            'recurrence_weekdays.*' => ['integer', 'min:0', 'max:6'],
            'recurrence_until' => ['nullable', 'date', 'after_or_equal:start_at'],
            'reminder_minutes' => ['nullable', 'integer', Rule::in([5, 10, 15, 30, 60, 120, 1440])],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $request->route('project')->id),
            ],
        ];

        if ($includeScope) {
            $rules['edit_scope'] = ['nullable', 'string', Rule::in(['series', 'occurrence'])];
            $rules['occurrence_date'] = ['nullable', 'date', 'required_if:edit_scope,occurrence'];
        }

        $validated = $request->validate($rules);

        if (empty($validated['recurrence'])) {
            $validated['recurrence'] = null;
            $validated['recurrence_weekdays'] = null;
            $validated['recurrence_until'] = null;
        } elseif ($validated['recurrence'] !== 'weekly') {
            $validated['recurrence_weekdays'] = null;
        }

        return $validated;
    }
}
