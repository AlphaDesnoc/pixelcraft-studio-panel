<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Support\ProjectSpace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait ResolvesProjectSpace
{
    protected function space(Request $request, Project $project): ProjectSpace
    {
        return ProjectSpace::resolve($request, $project, $request->user());
    }

    protected function resolveRankIdFromRequest(Request $request, Project $project): ?int
    {
        $validated = $request->validate([
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
        ]);

        return $validated['rank_id'] ?? null;
    }

    protected function assertEntityInSpace(ProjectSpace $space, ?int $entityRankId): void
    {
        abort_unless($space->owns($entityRankId), 404);
    }
}
