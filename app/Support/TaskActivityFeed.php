<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

class TaskActivityFeed
{
    /** @return array<int, array{rank: array<string, mixed>, logs: array<int, array<string, mixed>>}> */
    public static function groupedForSpace(
        Project $project,
        ProjectSpace $space,
        Collection $ranks,
        int $perRank = 15,
    ): array {
        $logs = ActivityLog::query()
            ->where('project_id', $project->id)
            ->where('subject_type', (new Task)->getMorphClass())
            ->with([
                'user:id,name',
                'subject' => fn ($query) => $query->with('list:id,rank_id'),
            ])
            ->latest()
            ->limit(250)
            ->get();

        return collect(self::visibleRanks($space, $ranks))
            ->map(function (array $rank) use ($logs, $perRank) {
                $rankId = $rank['id'];

                $filtered = $logs
                    ->filter(fn (ActivityLog $log) => self::logRankId($log) === $rankId)
                    ->take($perRank)
                    ->values()
                    ->map(fn (ActivityLog $log) => $log->toPayload())
                    ->all();

                return [
                    'rank' => $rank,
                    'logs' => $filtered,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private static function visibleRanks(ProjectSpace $space, Collection $ranks): array
    {
        if ($space->isGlobal) {
            return [[
                'id' => null,
                'name' => 'Global',
                'color' => '#6366f1',
            ]];
        }

        if ($space->isFull) {
            $items = [[
                'id' => null,
                'name' => 'Global',
                'color' => '#6366f1',
            ]];

            foreach ($ranks as $rank) {
                $items[] = [
                    'id' => (int) $rank['id'],
                    'name' => $rank['label'] ?? $rank['name'] ?? 'Rank',
                    'color' => $rank['color'] ?? '#6366f1',
                ];
            }

            return $items;
        }

        $rank = $ranks->firstWhere('id', $space->rankId);

        if (! $rank) {
            return [];
        }

        return [[
            'id' => (int) $rank['id'],
            'name' => $rank['label'] ?? $rank['name'] ?? 'Rank',
            'color' => $rank['color'] ?? '#6366f1',
        ]];
    }

    private static function logRankId(ActivityLog $log): ?int
    {
        $meta = $log->meta ?? [];

        if (array_key_exists('rank_id', $meta)) {
            return $meta['rank_id'] === null ? null : (int) $meta['rank_id'];
        }

        $task = $log->subject;

        if ($task instanceof Task) {
            return $task->list?->rank_id === null ? null : (int) $task->list->rank_id;
        }

        return null;
    }
}
