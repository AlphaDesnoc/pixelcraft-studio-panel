<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\CalendarEvent;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'all_day' => (bool) ($validated['all_day'] ?? false),
            'color' => $validated['color'] ?? '#7c5cff',
            'rank_id' => $validated['rank_id'] ?? null,
        ]);

        return $this->apiOrBack($request, ['event' => $this->eventPayload($event)]);
    }

    public function update(Request $request, Project $project, CalendarEvent $event): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'calendar');
        abort_unless($event->project_id === $project->id, 404);

        $validated = $this->validateData($request);

        $event->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'all_day' => (bool) ($validated['all_day'] ?? false),
            'color' => $validated['color'] ?? $event->color,
        ]);

        return $this->apiOrBack($request, ['event' => $this->eventPayload($event->fresh())]);
    }

    public function destroy(Request $request, Project $project, CalendarEvent $event): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'calendar');
        abort_unless($event->project_id === $project->id, 404);

        $eventId = $event->id;
        $event->delete();

        return $this->apiOrBack($request, ['event_id' => $eventId]);
    }

    /** @return array<string, mixed> */
    private function eventPayload(CalendarEvent $event): array
    {
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
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'all_day' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $request->route('project')->id),
            ],
        ]);
    }

    private function ensureCanEdit(Request $request, Project $project): void
    {
        $user = $request->user();
        $isAdmin = $user->is_admin;
        $isMember = $project->members()->whereKey($user->id)->exists();
        abort_unless($isAdmin || $isMember, 403);
    }
}
