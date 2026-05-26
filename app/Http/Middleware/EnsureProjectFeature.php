<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Support\ProjectPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $project = $request->route('project');

        if (! $project instanceof Project) {
            abort(404);
        }

        abort_unless(
            ProjectPermissions::can($request->user(), $project, $feature),
            403,
            'Accès refusé à cette fonctionnalité du projet.',
        );

        return $next($request);
    }
}
