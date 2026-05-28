<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\User;
use App\Support\CalendarRecurrenceExpander;
use App\Support\ProjectAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_recurrence_expands_occurrences(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $project->members()->attach($user->id, ['role' => ProjectAccess::ROLE_OWNER]);

        $start = Carbon::parse('2026-06-02 10:00:00');
        $event = CalendarEvent::query()->create([
            'project_id' => $project->id,
            'creator_id' => $user->id,
            'title' => 'Stand-up',
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'recurrence' => 'weekly',
            'recurrence_weekdays' => [1, 2, 3, 4, 5],
        ]);

        $occurrences = CalendarRecurrenceExpander::expandForRange(
            $event,
            $start->copy()->startOfDay(),
            $start->copy()->addWeeks(2)->endOfDay(),
        );

        $this->assertGreaterThanOrEqual(5, count($occurrences));
        $this->assertSame('Stand-up', $occurrences[0]['title']);
    }
}
