<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Support\ProjectPermissions;
use Illuminate\Http\Request;

trait EnsuresProjectFeature
{
    protected function ensureFeature(Request $request, Project $project, string $feature): void
    {
        abort_unless(
            ProjectPermissions::canRead($request->user(), $project, $feature),
            403,
            'Accès refusé à cette fonctionnalité du projet.',
        );
    }

    protected function ensureFeatureWrite(Request $request, Project $project, string $feature): void
    {
        $this->ensureFeature($request, $project, $feature);

        abort_unless(
            ProjectPermissions::canWrite($request->user(), $project, $feature),
            403,
            'Modification refusée sur cette fonctionnalité du projet.',
        );
    }
}
