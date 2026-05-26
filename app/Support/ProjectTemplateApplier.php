<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\TaskTag;

class ProjectTemplateApplier
{
    public static function apply(Project $project, ProjectTemplate $template): void
    {
        $payload = $template->payload ?? [];

        foreach ($payload['ranks'] ?? [] as $index => $rankData) {
            if ($project->ranks()->where('slug', $rankData['slug'] ?? '')->exists()) {
                continue;
            }

            $project->ranks()->create([
                'name' => $rankData['name'],
                'slug' => $rankData['slug'],
                'color' => $rankData['color'] ?? '#7c5cff',
                'position' => $index,
                'manages_bugs' => (bool) ($rankData['manages_bugs'] ?? false),
            ]);
        }

        $ranks = $project->ranks()->orderBy('position')->get(['id']);

        foreach ($payload['tags'] ?? [] as $tagData) {
            foreach ($ranks as $rank) {
                TaskTag::query()->firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'rank_id' => $rank->id,
                        'name' => $tagData['name'],
                    ],
                    ['color' => $tagData['color'] ?? '#7c5cff'],
                );
            }

            TaskTag::query()->firstOrCreate(
                [
                    'project_id' => $project->id,
                    'rank_id' => null,
                    'name' => $tagData['name'],
                ],
                ['color' => $tagData['color'] ?? '#7c5cff'],
            );
        }
    }
}
