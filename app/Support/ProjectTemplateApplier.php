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

        foreach ($payload['tags'] ?? [] as $tagData) {
            TaskTag::query()->firstOrCreate(
                [
                    'project_id' => $project->id,
                    'name' => $tagData['name'],
                ],
                ['color' => $tagData['color'] ?? '#7c5cff'],
            );
        }
    }
}
