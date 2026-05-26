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
            ProjectPermissions::can($request->user(), $project, $feature),
            403,
            'Accès refusé à cette fonctionnalité du projet.',
        );
    }
}
